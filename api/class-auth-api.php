<?php
/**
 * Authentication REST controller.
 *
 * Routes (namespace saas-bms/v1):
 *   POST /register  (public)         create account + business profile
 *   POST /login     (public)         authenticate, set cookie, return nonce
 *   POST /logout    (authenticated)  end the session
 *
 * Every route declares its HTTP method, a permission_callback, and argument
 * schemas with sanitize/validate callbacks. Handlers stay thin: parse, call the
 * service, and format a consistent response.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the authentication routes.
 */
class SBMS_Auth_API {

	/**
	 * Auth service instance.
	 *
	 * @return SBMS_Auth_Service
	 */
	protected function service() {
		return new SBMS_Auth_Service();
	}

	/**
	 * Register all authentication routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/register',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register' ),
					'permission_callback' => array( 'SBMS_Auth_Middleware', 'allow_public' ),
					'args'                => $this->register_args(),
				),
			)
		);

		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/login',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'login' ),
					'permission_callback' => array( 'SBMS_Auth_Middleware', 'allow_public' ),
					'args'                => $this->login_args(),
				),
			)
		);

		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/logout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'logout' ),
					'permission_callback' => array( 'SBMS_Auth_Middleware', 'require_authentication' ),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Handlers
	 * --------------------------------------------------------------------- */

	/**
	 * POST /register
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register( WP_REST_Request $request ) {
		$result = $this->service()->register(
			array(
				'username'      => $request->get_param( 'username' ),
				'email'         => $request->get_param( 'email' ),
				'password'      => $request->get_param( 'password' ),
				'display_name'  => $request->get_param( 'display_name' ),
				'business_name' => $request->get_param( 'business_name' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			201
		);
	}

	/**
	 * POST /login
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function login( WP_REST_Request $request ) {
		$result = $this->service()->login(
			$request->get_param( 'username' ),
			(string) $request->get_param( 'password' ),
			(bool) $request->get_param( 'remember' )
		);

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
	 * POST /logout
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function logout( WP_REST_Request $request ) {
		unset( $request );

		$this->service()->logout();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array( 'message' => __( 'Logged out.', 'saas-business-management' ) ),
			),
			200
		);
	}

	/* --------------------------------------------------------------------- *
	 * Argument schemas (sanitize + validate)
	 * --------------------------------------------------------------------- */

	/**
	 * Argument schema for /register.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function register_args() {
		return array(
			'username'      => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_user',
				'validate_callback' => array( $this, 'validate_non_empty' ),
			),
			'email'         => array(
				'required'          => true,
				'type'              => 'string',
				'format'            => 'email',
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => array( $this, 'validate_email' ),
			),
			'password'      => array(
				// Intentionally NOT sanitized: altering a password changes it.
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => array( $this, 'validate_password' ),
			),
			'display_name'  => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'business_name' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Argument schema for /login.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function login_args() {
		return array(
			'username' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_non_empty' ),
			),
			'password' => array(
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => array( $this, 'validate_non_empty' ),
			),
			'remember' => array(
				'required' => false,
				'type'     => 'boolean',
				'default'  => false,
			),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Validators
	 * --------------------------------------------------------------------- */

	/**
	 * Value must be a non-empty string.
	 *
	 * @param mixed $value Parameter value.
	 * @return bool
	 */
	public function validate_non_empty( $value ) {
		return is_string( $value ) && '' !== trim( $value );
	}

	/**
	 * Value must be a valid email (post-sanitisation).
	 *
	 * @param mixed $value Parameter value.
	 * @return bool
	 */
	public function validate_email( $value ) {
		return is_string( $value ) && false !== is_email( $value );
	}

	/**
	 * Value must meet the minimum password length.
	 *
	 * @param mixed $value Parameter value.
	 * @return bool
	 */
	public function validate_password( $value ) {
		return is_string( $value ) && strlen( $value ) >= SBMS_Auth_Service::MIN_PASSWORD_LENGTH;
	}
}
