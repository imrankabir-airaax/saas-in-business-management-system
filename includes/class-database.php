<?php
/**
 * Database manager.
 *
 * Owns creation, versioning and upgrading of the plugin's tables. It reads
 * the schema from SBMS_Schema and executes it through WordPress's dbDelta().
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central database access and migration helper.
 */
class SBMS_Database {

	/**
	 * Option key that stores the installed schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'sbms_db_version';

	/**
	 * Build a fully-qualified table name from its short key.
	 *
	 * @param string $name Unqualified table key, e.g. "products".
	 * @return string Fully-qualified table name, e.g. "wp_sbms_products".
	 */
	public static function table_name( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'sbms_' . $name;
	}

	/**
	 * Return the full prefix used by every plugin table ("wp_sbms_").
	 *
	 * @return string
	 */
	public static function table_prefix() {
		global $wpdb;

		return $wpdb->prefix . 'sbms_';
	}

	/**
	 * Create (or update) all plugin tables and record the schema version.
	 *
	 * Safe to call repeatedly: dbDelta only applies the differences.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		// dbDelta() lives in this admin include and is not loaded by default.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( ! class_exists( 'SBMS_Schema' ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = SBMS_Schema::get_tables( self::table_prefix(), $charset_collate );

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::DB_VERSION_OPTION, SBMS_DB_VERSION );
	}

	/**
	 * Create/upgrade tables only when the stored version is out of date.
	 *
	 * Hooked on admin_init so schema changes shipped in an update are applied
	 * without the user having to deactivate and reactivate the plugin.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::DB_VERSION_OPTION );

		if ( SBMS_DB_VERSION !== $installed ) {
			self::create_tables();
		}
	}

	/**
	 * Whether a given plugin table currently exists in the database.
	 *
	 * @param string $name Unqualified table key, e.g. "products".
	 * @return bool
	 */
	public static function table_exists( $name ) {
		global $wpdb;

		$table = self::table_name( $name );
		// Escape LIKE wildcards ("_" and "%") so the match is exact, then
		// confirm the returned name equals the one we asked for.
		$like  = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $found === $table;
	}

	/**
	 * Drop every plugin table.
	 *
	 * Intentionally NOT called on deactivation (which must preserve user data).
	 * It exists for a future uninstall.php routine that removes all traces of
	 * the plugin when the user deletes it.
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		if ( ! class_exists( 'SBMS_Schema' ) ) {
			return;
		}

		$tables = SBMS_Schema::get_tables( self::table_prefix(), '' );

		foreach ( array_keys( $tables ) as $key ) {
			$table = self::table_name( $key );
			// Table identifiers cannot be bound as prepared parameters; the value
			// is built from our own constant prefix + a hard-coded key, never user input.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}

		delete_option( self::DB_VERSION_OPTION );
	}
}
