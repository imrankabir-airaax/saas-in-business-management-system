<?php
/**
 * Plugin Name:       SaaS in Business Management System
 * Plugin URI:        https://example.com/saas-business-management
 * Description:       AI-powered business management system for small and medium-sized businesses. Manages products and inventory, sales, expenses, business performance, and delivers AI-powered insights and recommendations.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            CSE4104-7B-T03 – Stack Builders
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       saas-business-management
 * Domain Path:       /languages
 *
 * @package SBMS
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * -------------------------------------------------------------------------
 * Plugin constants.
 * -------------------------------------------------------------------------
 * Every constant is defined once, guarded, so that a second load of this
 * file (or a naming clash with another plugin) can never trigger a fatal
 * "constant already defined" error.
 */
if ( ! defined( 'SBMS_VERSION' ) ) {
	define( 'SBMS_VERSION', '1.1.0' );
}

if ( ! defined( 'SBMS_DB_VERSION' ) ) {
	define( 'SBMS_DB_VERSION', '1.1.0' );
}

if ( ! defined( 'SBMS_PLUGIN_FILE' ) ) {
	define( 'SBMS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'SBMS_PLUGIN_DIR' ) ) {
	define( 'SBMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SBMS_PLUGIN_URL' ) ) {
	define( 'SBMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'SBMS_PLUGIN_BASENAME' ) ) {
	define( 'SBMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'SBMS_INCLUDES_DIR' ) ) {
	define( 'SBMS_INCLUDES_DIR', SBMS_PLUGIN_DIR . 'includes/' );
}

if ( ! defined( 'SBMS_ADMIN_DIR' ) ) {
	define( 'SBMS_ADMIN_DIR', SBMS_PLUGIN_DIR . 'admin/' );
}

if ( ! defined( 'SBMS_API_DIR' ) ) {
	define( 'SBMS_API_DIR', SBMS_PLUGIN_DIR . 'api/' );
}

if ( ! defined( 'SBMS_MODELS_DIR' ) ) {
	define( 'SBMS_MODELS_DIR', SBMS_PLUGIN_DIR . 'models/' );
}

if ( ! defined( 'SBMS_SERVICES_DIR' ) ) {
	define( 'SBMS_SERVICES_DIR', SBMS_PLUGIN_DIR . 'services/' );
}

if ( ! defined( 'SBMS_MIDDLEWARE_DIR' ) ) {
	define( 'SBMS_MIDDLEWARE_DIR', SBMS_PLUGIN_DIR . 'middleware/' );
}

if ( ! defined( 'SBMS_DATABASE_DIR' ) ) {
	define( 'SBMS_DATABASE_DIR', SBMS_PLUGIN_DIR . 'database/' );
}

if ( ! defined( 'SBMS_ADMIN_URL' ) ) {
	define( 'SBMS_ADMIN_URL', SBMS_PLUGIN_URL . 'admin/' );
}

if ( ! defined( 'SBMS_ASSETS_URL' ) ) {
	define( 'SBMS_ASSETS_URL', SBMS_PLUGIN_URL . 'assets/' );
}

if ( ! defined( 'SBMS_REST_NAMESPACE' ) ) {
	define( 'SBMS_REST_NAMESPACE', 'saas-bms/v1' );
}

/**
 * Safely require a plugin file.
 *
 * Wraps require_once with an existence check so that a missing or renamed
 * file degrades into an admin notice instead of a white-screen fatal error.
 *
 * @param string $relative_path Path relative to the plugin root, e.g. "includes/class-plugin.php".
 * @return bool True when the file was found and loaded, false otherwise.
 */
function sbms_require_file( $relative_path ) {
	$full_path = SBMS_PLUGIN_DIR . ltrim( $relative_path, '/' );

	if ( is_readable( $full_path ) ) {
		require_once $full_path;
		return true;
	}

	// Record the missing file and surface it to admins instead of crashing.
	add_action(
		'admin_notices',
		static function () use ( $relative_path ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: missing plugin file path. */
						__( 'SaaS Business Management: required file "%s" is missing. Please reinstall the plugin.', 'saas-business-management' ),
						$relative_path
					)
				)
			);
		}
	);

	return false;
}

/*
 * -------------------------------------------------------------------------
 * Core bootstrap dependencies.
 * -------------------------------------------------------------------------
 * These four files are needed before WordPress fires any of the plugin's
 * runtime hooks: the database + activator/deactivator classes must exist so
 * the (de)activation hooks below can reference them, and the main plugin
 * class is the orchestrator loaded on plugins_loaded.
 */
sbms_require_file( 'database/schema.php' );
sbms_require_file( 'includes/class-database.php' );
sbms_require_file( 'includes/class-activator.php' );
sbms_require_file( 'includes/class-deactivator.php' );
sbms_require_file( 'includes/class-plugin.php' );

/**
 * Run on plugin activation.
 *
 * @return void
 */
function sbms_activate_plugin() {
	if ( class_exists( 'SBMS_Activator' ) ) {
		SBMS_Activator::activate();
	}
}
register_activation_hook( SBMS_PLUGIN_FILE, 'sbms_activate_plugin' );

/**
 * Run on plugin deactivation.
 *
 * @return void
 */
function sbms_deactivate_plugin() {
	if ( class_exists( 'SBMS_Deactivator' ) ) {
		SBMS_Deactivator::deactivate();
	}
}
register_deactivation_hook( SBMS_PLUGIN_FILE, 'sbms_deactivate_plugin' );

/**
 * Boot the plugin once all other plugins are loaded.
 *
 * @return void
 */
function sbms_run_plugin() {
	if ( class_exists( 'SBMS_Plugin' ) ) {
		SBMS_Plugin::instance()->run();
	}
}
add_action( 'plugins_loaded', 'sbms_run_plugin' );
