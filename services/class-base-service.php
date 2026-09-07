<?php
/**
 * Abstract base service.
 *
 * Services hold business logic that sits above the models — for example the
 * Gemini AI service that will generate insights and recommendations. Concrete
 * services extend this class; none is implemented yet.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for all service objects.
 */
abstract class SBMS_Base_Service {

	/**
	 * Read a plugin setting.
	 *
	 * @param string $key     Setting key inside the "sbms_settings" option.
	 * @param mixed  $default Value returned when the key is absent.
	 * @return mixed
	 */
	protected function get_setting( $key, $default = null ) {
		$settings = get_option( 'sbms_settings', array() );

		if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Whether AI features are enabled and an API key is configured.
	 *
	 * @return bool
	 */
	protected function is_ai_ready() {
		$enabled = (bool) $this->get_setting( 'enable_ai', false );
		$api_key = (string) $this->get_setting( 'gemini_api_key', '' );

		return $enabled && '' !== trim( $api_key );
	}
}
