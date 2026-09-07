<?php
/**
 * Authentication middleware for REST routes.
 *
 * Authentication (who are you?) only. Authorization (what may you do?) lives in
 * SBMS_Permission_Middleware. These static methods are used directly as the
 * `permission_callback` of REST routes.
 *
 * Note on nonces: WordPress core protects cookie-authenticated REST requests
 * with the `wp_rest` nonce (sent as the X-WP-Nonce header). A logged-in request
 * without a valid nonce is treated as logged-out by core, so require_authentication()
 * below will correctly reject it. The /login response returns a fresh nonce for
 * the frontend to use on subsequent authenticated calls.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authentication permission callbacks.
 */
class SBMS_Auth_Middleware {

	/**
	 * Allow any caller (public endpoint). Used for /register and /login.
	 *
	 * @param WP_REST_Request|null $request Current request (unused).
	 * @return true
	 */
	public static function allow_public( $request = null ) {
		unset( $request );

		return true;
	}

	/**
	 * Require an authenticated user.
	 *
	 * @param WP_REST_Request|null $request Current request (unused).
	 * @return true|WP_Error True when logged in, WP_Error (401) otherwise.
	 */
	public static function require_authentication( $request = null ) {
		unset( $request );

		if ( is_user_logged_in() ) {
			return true;
		}

		return new WP_Error(
			'sbms_unauthorized',
			__( 'Authentication is required to access this resource.', 'saas-business-management' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Backwards-compatible alias for require_authentication().
	 *
	 * @param WP_REST_Request|null $request Current request (unused).
	 * @return true|WP_Error
	 */
	public static function require_login( $request = null ) {
		return self::require_authentication( $request );
	}
}
