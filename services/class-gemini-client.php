<?php
/**
 * Gemini HTTP client.
 *
 * The ONLY place the Gemini API key is used. The key is:
 *   - read from the server-side option (never from the request),
 *   - sent to Google as the x-goog-api-key HEADER (never in the URL, so it
 *     can't leak via access logs or referrers),
 *   - never returned to the caller, never logged, never placed in any WP_Error
 *     message that reaches the browser.
 *
 * All provider/network failures are mapped to generic, safe WP_Error objects.
 * Raw provider bodies are only written to the PHP error log (server-side), and
 * even then the key is never part of that body.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the Gemini generateContent endpoint.
 */
class SBMS_Gemini_Client {

	const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * Default Gemini model.
	 *
	 * gemini-3.6-flash is a stable, generally available model that works on
	 * the free tier and fully supports responseSchema. The previous default
	 * gemini-1.5-flash was shut down by Google in September 2025.
	 */
	const DEFAULT_MODEL = 'gemini-3.6-flash';

	/**
	 * Known-good models tried (in order) when the configured model returns a
	 * 404 because Google retired it or does not offer it to the API key.
	 *
	 * @var array<int,string>
	 */
	const FALLBACK_MODELS = array( 'gemini-3.5-flash', 'gemini-2.5-flash' );


	/**
	 * @var string
	 */
	private $api_key;

	/**
	 * @var string
	 */
	private $model;

	/**
	 * @var int
	 */
	private $timeout;

	/**
	 * @param string $api_key Gemini API key (server-side only).
	 * @param string $model   Model name (retired families fall back to the default).
	 * @param int    $timeout Request timeout in seconds.
	 */
	public function __construct( $api_key, $model = self::DEFAULT_MODEL, $timeout = 30 ) {
		$this->api_key = is_string( $api_key ) ? trim( $api_key ) : '';
		$this->model   = self::normalize_model( $model );
		$this->timeout = max( 5, (int) $timeout );
	}

	/**
	 * Sanitize a model name and replace retired families with the default.
	 *
	 * Google shut the Gemini 1.5 family down in September 2025 and the 2.0
	 * family in June 2026, so any value from those families is swapped for
	 * the current default instead of failing with a 404.
	 *
	 * @param string $model Raw model name (may be empty or unsanitized).
	 * @return string Safe model name.
	 */
	public static function normalize_model( $model ) {
		$model = (string) preg_replace( '/[^a-zA-Z0-9._\-]/', '', trim( (string) $model ) );
		
		if ( '' === $model || self::is_dead_model( $model ) ) {
			return self::DEFAULT_MODEL;
		}
		
		return $model;
	}

	/**
	 * Whether the model belongs to a family Google has already shut down.
	 *
	 * @param string $model Model name.
	 * @return bool
	 */
	public static function is_dead_model( $model ) {
		return (bool) preg_match( '/^gemini-(1\.[05]|2\.0)-/i', (string) $model );
	}


	/**
	 * Whether a key is present.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->api_key;
	}

	/**
	 * Request a JSON object back from the model.
	 *
	 * Tries the configured model first. When Google answers 404 (model
	 * retired or not offered to this API key), the known-good fallback
	 * models are tried in order, so a retired model name can never take
	 * the AI feature down completely.
	 *
	 * @param string                    $prompt System+data prompt.
	 * @param array<string,mixed>|null  $schema Optional responseSchema.
	 * @return array<string,mixed>|WP_Error Parsed JSON, or a safe WP_Error.
	 */
	public function generate_json( $prompt, $schema = null ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'sbms_ai_not_configured', __( 'AI is not configured yet.', 'saas-business-management' ), array( 'status' => 503 ) );
		}
		
		$result = null;
		
		foreach ( $this->model_candidates() as $model ) {
			$result = $this->request_json( $model, $prompt, $schema );
			
			// 404 means Google retired this model or does not offer it to this
			// API key; only then do we try the next known-good model.
			if ( is_wp_error( $result ) && 'sbms_ai_model_unavailable' === $result->get_error_code() ) {
				$this->log( 'model_unavailable', $model . ' is not available for this API key; trying fallback model' );
				continue;
			}
			
			return $result;
		}
		
		return $result;
	}

	/**
	 * Single generateContent request against one model.
	 *
	 * @param string                   $model  Model name.
	 * @param string                   $prompt System+data prompt.
	 * @param array<string,mixed>|null $schema Optional responseSchema.
	 * @return array<string,mixed>|WP_Error Parsed JSON, or a safe WP_Error.
	 */
	private function request_json( $model, $prompt, $schema ) {
		$url = self::ENDPOINT . rawurlencode( $model ) . ':generateContent';
		
		$generation = array(
			'responseMimeType' => 'application/json',
			);
		
		// The temperature sampling parameter is deprecated on Gemini 3.x
		// models (July 2026); only send it to the 2.5 family and older.
		if ( ! preg_match( '/^gemini-3/i', $model ) ) {
			$generation['temperature'] = 0.4;
		}
		
		if ( is_array( $schema ) ) {
			$generation['responseSchema'] = $schema;
		}
		
		$body = array(
			'contents'         => array(
				array(
					'parts' => array(
						array( 'text' => (string) $prompt ),
					),
				),
			),
			'generationConfig' => $generation,
		);
		
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'Content-Type'    => 'application/json',
					// Key travels in the header, not the URL.
					'x-goog-api-key'  => $this->api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		
		// Network failure or timeout.
		if ( is_wp_error( $response ) ) {
			$this->log( 'network', $response->get_error_message() );
			return new WP_Error( 'sbms_ai_unavailable', __( 'The AI service is temporarily unavailable. Please try again in a moment.', 'saas-business-management' ), array( 'status' => 503 ) );
		}
		
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		
		if ( 200 !== $code ) {
			return $this->map_http_error( $code, $raw );
		}
		
		$decoded = json_decode( $raw, true );
		$text    = $this->extract_text( $decoded );
		
		if ( null === $text ) {
			$this->log( 'bad_response', substr( $raw, 0, 300 ) );
			return new WP_Error( 'sbms_ai_bad_response', __( 'The AI service returned a response we could not read.', 'saas-business-management' ), array( 'status' => 502 ) );
		}
		
		$parsed = json_decode( $text, true );
		
		// If the model returned prose instead of JSON, wrap it as a summary so
		// the caller still gets something structured.
		if ( ! is_array( $parsed ) ) {
			return array( 'summary' => trim( (string) $text ) );
		}
		
		return $parsed;
	}

	/**
	 * The configured model plus the fallback models, deduplicated.
	 *
	 * @return array<int,string>
	 */
	private function model_candidates() {
		$candidates = array( $this->model );
		
		foreach ( self::FALLBACK_MODELS as $fallback ) {
			if ( ! in_array( $fallback, $candidates, true ) ) {
				$candidates[] = $fallback;
			}
		}
		
		return $candidates;
	}


	/**
	 * Map a non-200 provider status to a safe, generic WP_Error.
	 *
	 * The raw body is logged server-side only; the key is never in it (it is a
	 * request header, not part of the response), and nothing provider-specific
	 * is surfaced to the browser.
	 *
	 * @param int    $code HTTP status.
	 * @param string $raw  Raw response body.
	 * @return WP_Error
	 */
	private function map_http_error( $code, $raw ) {
		$this->log( 'http_' . $code, substr( (string) $raw, 0, 300 ) );

		if ( 404 === $code ) {
			// Google retired the model or does not offer it to this API key.
			return new WP_Error( 'sbms_ai_model_unavailable', __( 'The configured AI model is not available for this API key. An administrator should choose a supported model (e.g. gemini-3.6-flash) in Settings.', 'saas-business-management' ), array( 'status' => 502 ) );
		}

		if ( 400 === $code || 401 === $code || 403 === $code ) {
			// Usually an invalid/misconfigured API key. Don't hint at internals.
			return new WP_Error( 'sbms_ai_rejected', __( 'The AI request was rejected. An administrator should verify the API key in Settings.', 'saas-business-management' ), array( 'status' => 502 ) );
		}
		if ( 429 === $code ) {
			return new WP_Error( 'sbms_ai_rate_limited', __( 'The AI service is busy right now. Please try again shortly.', 'saas-business-management' ), array( 'status' => 429 ) );
		}
		if ( $code >= 500 ) {
			return new WP_Error( 'sbms_ai_server_error', __( 'The AI service had a problem. Please try again.', 'saas-business-management' ), array( 'status' => 502 ) );
		}

		return new WP_Error( 'sbms_ai_failed', __( 'The AI request could not be completed.', 'saas-business-management' ), array( 'status' => 502 ) );
	}

	/**
	 * Pull the model's text out of a Gemini response envelope.
	 *
	 * @param mixed $data Decoded response.
	 * @return string|null
	 */
	private function extract_text( $data ) {
		if ( ! is_array( $data ) || empty( $data['candidates'][0]['content']['parts'] ) || ! is_array( $data['candidates'][0]['content']['parts'] ) ) {
			return null;
		}
		
		// Thinking models can return several parts; use the first one that
		// actually carries text.
		foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
			if ( is_array( $part ) && isset( $part['text'] ) && '' !== (string) $part['text'] ) {
				return (string) $part['text'];
			}
		}
		
		return null;
	}


	/**
	 * Server-side only diagnostic logging (never contains the key).
	 *
	 * @param string $tag     Short tag.
	 * @param string $message Detail.
	 * @return void
	 */
	private function log( $tag, $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'SBMS Gemini [' . $tag . ']: ' . $message );
		}
	}
}
