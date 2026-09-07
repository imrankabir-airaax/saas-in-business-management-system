<?php
/**
 * Permission (authorization) middleware for REST routes.
 *
 * Capability and ownership checks. Kept separate from authentication so routes
 * can compose "logged in" + "has capability" + "owns the resource" cleanly.
 *
 * rest_authorization_required_code() returns 401 when the user is logged out
 * and 403 when they are logged in but lack permission, which is exactly the
 * distinction we want for every denial here.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authorization permission callbacks.
 */
class SBMS_Permission_Middleware {

	/**
	 * Require full plugin-management capability (administrators).
	 *
	 * @param WP_REST_Request|null $request Current request (unused).
	 * @return true|WP_Error
	 */
	public static function require_manage( $request = null ) {
		unset( $request );

		return current_user_can( 'manage_sbms' ) ? true : self::denied();
	}

	/**
	 * Require business-app access (business users or administrators).
	 *
	 * @param WP_REST_Request|null $request Current request (unused).
	 * @return true|WP_Error
	 */
	public static function require_access( $request = null ) {
		unset( $request );

		if ( current_user_can( 'sbms_access' ) || current_user_can( 'manage_sbms' ) ) {
			return true;
		}

		return self::denied();
	}

	/**
	 * Ensure the current user owns a user-scoped resource (or can manage all).
	 *
	 * @param int $owner_user_id The user_id the resource belongs to.
	 * @return true|WP_Error
	 */
	public static function require_ownership( $owner_user_id ) {
		$current = get_current_user_id();

		if ( $current && ( (int) $owner_user_id === (int) $current || current_user_can( 'manage_sbms' ) ) ) {
			return true;
		}

		return self::denied();
	}

	/**
	 * Build a standard authorization-denied error with the correct status.
	 *
	 * @return WP_Error
	 */
	protected static function denied() {
		return new WP_Error(
			'sbms_forbidden',
			__( 'You do not have permission to perform this action.', 'saas-business-management' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
