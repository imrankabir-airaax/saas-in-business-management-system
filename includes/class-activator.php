<?php
/**
 * Fired during plugin activation.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the one-time setup needed when the plugin is activated.
 */
class SBMS_Activator {

	/**
	 * Activation entry point.
	 *
	 * @return void
	 */
	public static function activate() {
		// No current_user_can() gate here: WordPress already restricts who can
		// reach the activation flow, and gating on a capability would silently
		// skip setup under WP-CLI / programmatic activation (which run with no
		// logged-in user), leaving the tables uncreated.
		self::create_schema();
		self::set_default_options();
		self::register_capabilities();

		update_option( 'sbms_version', SBMS_VERSION );

		// Refresh rewrite rules so REST routes registered later resolve cleanly.
		flush_rewrite_rules();
	}

	/**
	 * Create the database tables.
	 *
	 * @return void
	 */
	private static function create_schema() {
		if ( class_exists( 'SBMS_Database' ) ) {
			SBMS_Database::create_tables();
		}
	}

	/**
	 * Seed default settings without overwriting anything a user already saved.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		if ( false === get_option( 'sbms_settings' ) ) {
			add_option(
				'sbms_settings',
				array(
					'currency'        => 'USD',
					'currency_symbol' => '$',
					'tax_rate'        => 0,
					'enable_ai'       => false,
					'gemini_api_key'  => '',
					'gemini_model'    => 'gemini-3.6-flash',
				)
			);
		}
	}

	/**
	 * Grant the plugin's management capability to administrators.
	 *
	 * All plugin admin screens and REST routes are gated on `manage_sbms`
	 * rather than a generic core capability, so access can be delegated to
	 * other roles later without code changes.
	 *
	 * @return void
	 */
	private static function register_capabilities() {
		$role = get_role( 'administrator' );

		if ( $role instanceof WP_Role ) {
			// manage_sbms  = full plugin administration.
			// sbms_access  = may use the business app for their own data.
			// Administrators get both; business users (created via /register)
			// receive sbms_access only.
			if ( ! $role->has_cap( 'manage_sbms' ) ) {
				$role->add_cap( 'manage_sbms' );
			}
			if ( ! $role->has_cap( 'sbms_access' ) ) {
				$role->add_cap( 'sbms_access' );
			}
		}
	}
}
