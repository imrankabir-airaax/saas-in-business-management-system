<?php
/**
 * Fired during plugin deactivation.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs cleanup that should happen when the plugin is deactivated.
 *
 * Deactivation deliberately preserves all user data (tables, settings). Data
 * removal belongs to an uninstall routine, not to deactivation.
 */
class SBMS_Deactivator {

	/**
	 * Deactivation entry point.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// No current_user_can() gate: keep cleanup consistent under WP-CLI /
		// programmatic deactivation, which run with no logged-in user.

		// Clear any cron event the plugin may have scheduled.
		$timestamp = wp_next_scheduled( 'sbms_daily_maintenance' );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'sbms_daily_maintenance' );
		}
		wp_clear_scheduled_hook( 'sbms_daily_maintenance' );

		// Rewrite rules were flushed on activation for our routes; flush again
		// on the way out so nothing stale is left behind.
		flush_rewrite_rules();
	}
}
