<?php
/**
 * Authentication service.
 *
 * Orchestrates the auth flows on top of WordPress's native authentication:
 * wp_insert_user() for registration (core hashes the password), wp_signon()
 * for login (core sets the auth cookie), and wp_logout() for logout. It never
 * stores, logs or returns passwords, and maps failures to generic messages so
 * internal details and user existence are not leaked.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * High-level authentication and profile operations.
 */
class SBMS_Auth_Service extends SBMS_Base_Service {

	/**
	 * Minimum accepted password length.
	 *
	 * @var int
	 */
	const MIN_PASSWORD_LENGTH = 8;

	/**
	 * User model instance.
	 *
	 * @return SBMS_User_Model
	 */
	protected function users() {
		return new SBMS_User_Model();
	}

	/**
	 * Register a new business user.
	 *
	 * @param array<string,mixed> $input Sanitised registration fields.
	 * @return array<string,mixed>|WP_Error Public user payload, or WP_Error.
	 */
	public function register( array $input ) {
		$username = isset( $input['username'] ) ? sanitize_user( $input['username'], true ) : '';
		$email    = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
		$password = isset( $input['password'] ) ? (string) $input['password'] : '';
		$display  = isset( $input['display_name'] ) ? sanitize_text_field( $input['display_name'] ) : '';
		$biz_name = isset( $input['business_name'] ) ? sanitize_text_field( $input['business_name'] ) : '';

		if ( '' === $username || ! validate_username( $username ) ) {
			return new WP_Error( 'sbms_invalid_username', __( 'Please provide a valid username.', 'saas-business-management' ), array( 'status' => 400 ) );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'sbms_invalid_email', __( 'Please provide a valid email address.', 'saas-business-management' ), array( 'status' => 400 ) );
		}
		if ( strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
			return new WP_Error(
				'sbms_weak_password',
				sprintf(
					/* translators: %d: minimum password length. */
					__( 'Password must be at least %d characters long.', 'saas-business-management' ),
					self::MIN_PASSWORD_LENGTH
				),
				array( 'status' => 400 )
			);
		}

		$users = $this->users();

		if ( $users->login_exists( $username ) ) {
			return new WP_Error( 'sbms_username_taken', __( 'That username is already taken.', 'saas-business-management' ), array( 'status' => 409 ) );
		}
		if ( $users->email_owner( $email ) ) {
			return new WP_Error( 'sbms_email_taken', __( 'An account with that email already exists.', 'saas-business-management' ), array( 'status' => 409 ) );
		}

		$result = $users->create_account(
			$username,
			$email,
			$password,
			$display,
			array( 'business_name' => $biz_name, 'business_email' => $email )
		);

		if ( is_wp_error( $result ) ) {
			// Do not surface internal/core error details to the client.
			return new WP_Error( 'sbms_registration_failed', __( 'Registration could not be completed. Please try again.', 'saas-business-management' ), array( 'status' => 400 ) );
		}

		return $users->public_payload( new WP_User( $result ) );
	}

	/**
	 * Authenticate a user and establish their session.
	 *
	 * @param string $login    Username or email.
	 * @param string $password Plain password.
	 * @param bool   $remember Whether to persist the session.
	 * @return array<string,mixed>|WP_Error Public payload (plus a REST nonce), or WP_Error.
	 */
	public function login( $login, $password, $remember = false ) {
		$login    = is_string( $login ) ? trim( $login ) : '';
		$password = (string) $password;

		if ( '' === $login || '' === $password ) {
			return new WP_Error( 'sbms_missing_credentials', __( 'Username and password are required.', 'saas-business-management' ), array( 'status' => 400 ) );
		}

		// Make the freshly-issued logged-in cookie readable within THIS request.
		// wp_signon() only sends a Set-Cookie header, so $_COOKIE is not yet
		// populated; without this, wp_get_session_token() (used by
		// wp_create_nonce below) would return an empty token and the REST nonce
		// we hand back would fail validation on the client's next request.
		add_action( 'set_logged_in_cookie', array( $this, 'capture_logged_in_cookie' ) );

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => (bool) $remember,
			),
			is_ssl()
		);

		remove_action( 'set_logged_in_cookie', array( $this, 'capture_logged_in_cookie' ) );

		if ( is_wp_error( $user ) ) {
			// Single generic message prevents username/email enumeration.
			return new WP_Error( 'sbms_invalid_credentials', __( 'Invalid username or password.', 'saas-business-management' ), array( 'status' => 401 ) );
		}

		wp_set_current_user( $user->ID );

		$payload          = $this->users()->public_payload( $user );
		// Fresh REST cookie nonce so the SPA can call authenticated endpoints.
		$payload['nonce'] = wp_create_nonce( 'wp_rest' );

		return $payload;
	}

	/**
	 * Capture the logged-in auth cookie into $_COOKIE during login.
	 *
	 * Hooked onto set_logged_in_cookie only for the duration of wp_signon() so
	 * that wp_get_session_token() can read the real session token in the same
	 * request, letting wp_create_nonce( 'wp_rest' ) produce a nonce that will
	 * validate on subsequent authenticated requests.
	 *
	 * @param string $logged_in_cookie The logged-in cookie value.
	 * @return void
	 */
	public function capture_logged_in_cookie( $logged_in_cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
	}

	/**
	 * Log the current user out (clears the auth cookie and session tokens).
	 *
	 * @return true
	 */
	public function logout() {
		wp_logout();

		return true;
	}

	/**
	 * The current user's public profile.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function current_profile() {
		$user = wp_get_current_user();

		if ( ! ( $user instanceof WP_User ) || 0 === (int) $user->ID ) {
			return new WP_Error( 'sbms_unauthorized', __( 'Authentication is required.', 'saas-business-management' ), array( 'status' => 401 ) );
		}

		return $this->users()->public_payload( $user );
	}

	/**
	 * Update the current user's profile (core display name / email + business data).
	 *
	 * @param array<string,mixed> $input Sanitised profile fields.
	 * @return array<string,mixed>|WP_Error Refreshed public payload, or WP_Error.
	 */
	public function update_current_profile( array $input ) {
		$user = wp_get_current_user();

		if ( ! ( $user instanceof WP_User ) || 0 === (int) $user->ID ) {
			return new WP_Error( 'sbms_unauthorized', __( 'Authentication is required.', 'saas-business-management' ), array( 'status' => 401 ) );
		}

		$core = array();

		if ( isset( $input['display_name'] ) ) {
			$display = sanitize_text_field( $input['display_name'] );
			if ( '' !== $display ) {
				$core['display_name'] = $display;
			}
		}

		if ( isset( $input['email'] ) ) {
			$email = sanitize_email( $input['email'] );
			if ( '' === $email || ! is_email( $email ) ) {
				return new WP_Error( 'sbms_invalid_email', __( 'Please provide a valid email address.', 'saas-business-management' ), array( 'status' => 400 ) );
			}
			$owner = email_exists( $email );
			if ( $owner && (int) $owner !== (int) $user->ID ) {
				return new WP_Error( 'sbms_email_taken', __( 'That email is already in use.', 'saas-business-management' ), array( 'status' => 409 ) );
			}
			$core['user_email'] = $email;
		}

		$profile = array();
		foreach ( array( 'business_name', 'business_phone', 'address', 'currency', 'tax_number' ) as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$profile[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( isset( $input['business_email'] ) ) {
			$biz_email = sanitize_email( $input['business_email'] );
			if ( '' !== $biz_email && ! is_email( $biz_email ) ) {
				return new WP_Error( 'sbms_invalid_business_email', __( 'Please provide a valid business email.', 'saas-business-management' ), array( 'status' => 400 ) );
			}
			$profile['business_email'] = $biz_email;
		}

		$result = $this->users()->update_account( (int) $user->ID, $core, $profile );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'sbms_update_failed', __( 'Your profile could not be updated. Please try again.', 'saas-business-management' ), array( 'status' => 400 ) );
		}

		return $this->users()->public_payload( wp_get_current_user() );
	}
}
