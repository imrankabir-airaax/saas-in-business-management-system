<?php
/**
 * User / profile REST controller.
 *
 * Routes (namespace saas-bms/v1):
 *   GET /profile  (authenticated)  current user + business profile
 *   PUT /profile  (authenticated)  update display name / email + business data
 *
 * WP_REST_Server::EDITABLE matches POST, PUT and PATCH, so PUT /profile is
 * handled here. Both routes require authentication; WordPress core enforces the
 * cookie nonce for logged-in REST requests.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the profile routes.
 */
class SBMS_User_API {

	/**
	 * Auth service instance.
	 *
	 * @return SBMS_Auth_Service
	 */
	protected function service() {
		return new SBMS_Auth_Service();
	}

	/**
	 * Register the profile routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => array( 'SBMS_Auth_Middleware', 'require_authentication' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_profile' ),
					'permission_callback' => array( 'SBMS_Auth_Middleware', 'require_authentication' ),
					'args'                => $this->update_args(),
				),
			)
		);
	}

	/**
	 * GET /profile
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_profile( WP_REST_Request $request ) {
		unset( $request );

		$result = $this->service()->current_profile();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * PUT /profile
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_profile( WP_REST_Request $request ) {
		$fields = array();
		foreach ( array( 'display_name', 'email', 'business_name', 'business_email', 'business_phone', 'address', 'currency', 'tax_number' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$fields[ $key ] = $value;
			}
		}

		$result = $this->service()->update_current_profile( $fields );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * Argument schema for PUT /profile. All optional; only provided fields change.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function update_args() {
		return array(
			'display_name'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'email'          => array(
				'type'              => 'string',
				'format'            => 'email',
				'sanitize_callback' => 'sanitize_email',
			),
			'business_name'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'business_email' => array(
				'type'              => 'string',
				'format'            => 'email',
				'sanitize_callback' => 'sanitize_email',
			),
			'business_phone' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'address'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'currency'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'tax_number'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
