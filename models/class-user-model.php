<?php
/**
 * User model.
 *
 * A thin facade over WordPress's own user system (wp_users / wp_usermeta) that
 * also joins the plugin's business profile row. It deliberately does NOT define
 * its own users table and NEVER stores or returns passwords — WordPress core
 * owns all credential handling. Business/application data lives in the existing
 * business_profiles table via SBMS_Business_Profile_Model.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for users (WordPress core) + their business profile.
 */
class SBMS_User_Model {

	/**
	 * Capability granted to business users so they can use the app.
	 *
	 * @var string
	 */
	const ACCESS_CAP = 'sbms_access';

	/**
	 * Business profile model instance.
	 *
	 * @return SBMS_Business_Profile_Model
	 */
	protected function profiles() {
		return new SBMS_Business_Profile_Model();
	}

	/**
	 * Whether a username already exists.
	 *
	 * @param string $login Username.
	 * @return bool
	 */
	public function login_exists( $login ) {
		return (bool) username_exists( $login );
	}

	/**
	 * Whether an email already belongs to an account.
	 *
	 * @param string $email Email address.
	 * @return int|false User ID when found, false otherwise.
	 */
	public function email_owner( $email ) {
		return email_exists( $email );
	}

	/**
	 * Create a WordPress user account and its business profile.
	 *
	 * The password is passed straight to wp_insert_user(), which hashes it with
	 * WordPress's password hasher — it is never stored or logged in plain text
	 * by this plugin.
	 *
	 * @param string               $username       Login name.
	 * @param string               $email          Email address.
	 * @param string               $password       Plain password (hashed by core).
	 * @param string               $display_name   Optional display name.
	 * @param array<string,mixed>  $profile_fields Optional business profile fields.
	 * @return int|WP_Error New user ID, or WP_Error on failure.
	 */
	public function create_account( $username, $email, $password, $display_name = '', array $profile_fields = array() ) {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => ( '' !== $display_name ) ? $display_name : $username,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = new WP_User( $user_id );
		$user->add_cap( self::ACCESS_CAP );

		$profile_fields['business_email'] = isset( $profile_fields['business_email'] ) ? $profile_fields['business_email'] : $email;
		$this->profiles()->upsert_for_user( (int) $user_id, $profile_fields );

		return (int) $user_id;
	}

	/**
	 * Update a user's core fields and/or business profile.
	 *
	 * @param int                 $user_id WordPress user ID.
	 * @param array<string,mixed> $core    Core wp_update_user() fields (no password here).
	 * @param array<string,mixed> $profile Business profile fields.
	 * @return true|WP_Error
	 */
	public function update_account( $user_id, array $core, array $profile ) {
		if ( ! empty( $core ) ) {
			$core['ID'] = (int) $user_id;
			$result     = wp_update_user( $core );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( ! empty( $profile ) ) {
			$this->profiles()->upsert_for_user( (int) $user_id, $profile );
		}

		return true;
	}

	/**
	 * Build a safe, whitelisted public representation of a user.
	 *
	 * Only non-sensitive fields are included. Password hashes, activation keys,
	 * session tokens and any other credential material are never exposed.
	 *
	 * @param WP_User $user User object.
	 * @return array<string,mixed>
	 */
	public function public_payload( WP_User $user ) {
		$profile = $this->profiles()->find_for_user( $user->ID );

		return array(
			'id'           => (int) $user->ID,
			'username'     => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'roles'        => array_values( (array) $user->roles ),
			'capabilities' => array(
				'sbms_access' => user_can( $user, self::ACCESS_CAP ),
				'manage_sbms' => user_can( $user, 'manage_sbms' ),
			),
			'profile'      => $this->public_profile( $profile ),
		);
	}

	/**
	 * Whitelist the business profile fields safe to return.
	 *
	 * @param array<string,mixed>|null $profile Raw profile row or null.
	 * @return array<string,mixed>
	 */
	protected function public_profile( $profile ) {
		$defaults = array(
			'business_name'  => '',
			'business_email' => '',
			'business_phone' => '',
			'address'        => '',
			'currency'       => 'USD',
			'tax_number'     => '',
		);

		if ( ! is_array( $profile ) ) {
			return $defaults;
		}

		$out = array();
		foreach ( $defaults as $key => $default ) {
			$out[ $key ] = isset( $profile[ $key ] ) ? $profile[ $key ] : $default;
		}

		return $out;
	}
}
