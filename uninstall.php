<?php
/**
 * Uninstall handler for SaaS Business Management System.
 *
 * Runs ONLY when the plugin is deleted from the WordPress admin
 * (Plugins → Delete), never on deactivation. It removes the plugin's
 * database tables, its options, and the capabilities it granted, so an
 * uninstall leaves no residue.
 *
 * Deactivation remains completely non-destructive (see class-deactivator.php);
 * business data is only removed here, on an explicit delete.
 *
 * @package SBMS
 */

// If uninstall is not called by WordPress, bail immediately.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$sbms_dir = plugin_dir_path( __FILE__ );

// Load just the schema + database helpers (the main plugin is NOT bootstrapped
// during uninstall). These files only define classes and reference no bootstrap
// path constants, so they are safe to load standalone.
$sbms_schema_file   = $sbms_dir . 'database/schema.php';
$sbms_database_file = $sbms_dir . 'includes/class-database.php';

if ( file_exists( $sbms_schema_file ) ) {
	require_once $sbms_schema_file;
}
if ( file_exists( $sbms_database_file ) ) {
	require_once $sbms_database_file;
}

// Drop the plugin's tables (also deletes the sbms_db_version option).
if ( class_exists( 'SBMS_Database' ) && class_exists( 'SBMS_Schema' ) ) {
	SBMS_Database::drop_tables();
}

// Remove remaining plugin options.
delete_option( 'sbms_settings' );
delete_option( 'sbms_version' );

// Remove the capabilities granted on activation.
$sbms_role = get_role( 'administrator' );
if ( $sbms_role instanceof WP_Role ) {
	$sbms_role->remove_cap( 'manage_sbms' );
	$sbms_role->remove_cap( 'sbms_access' );
}
