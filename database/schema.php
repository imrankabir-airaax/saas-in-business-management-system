<?php
/**
 * Database schema definitions.
 *
 * Single source of truth for the plugin's table structure. It only *describes*
 * the tables; creating and upgrading them is the job of SBMS_Database
 * (includes/class-database.php), which runs each statement through dbDelta().
 *
 * Design notes:
 * - Ownership: every business-owned table carries a `user_id` column that
 *   references the WordPress users table (wp_users.ID). We rely on WordPress's
 *   own authentication and never store passwords here.
 * - Relationships are expressed as indexed integer columns (logical foreign
 *   keys), not DB-level FOREIGN KEY constraints, because dbDelta does not
 *   manage constraints and core WP tables are not guaranteed to be InnoDB.
 * - Timestamps use "NULL DEFAULT NULL" and are written by the model layer, so
 *   the schema is safe on MySQL 8 / strict-mode hosts (no zero-dates).
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the CREATE TABLE statements for every plugin table.
 */
class SBMS_Schema {

	/**
	 * Return every table's CREATE statement, keyed by its unqualified name.
	 *
	 * The SQL satisfies dbDelta()'s strict formatting rules: one field per
	 * line, two spaces before the PRIMARY KEY definition, lowercase types,
	 * and a KEY entry per index.
	 *
	 * @param string $prefix          The full table prefix, e.g. "wp_sbms_".
	 * @param string $charset_collate The result of $wpdb->get_charset_collate().
	 * @return array<string,string> Map of table key => CREATE TABLE SQL.
	 */
	public static function get_tables( $prefix, $charset_collate ) {
		$tables = array();

		// 1. Business users / profile information (one row per WordPress user).
		//    Stores business/application data only — NEVER login credentials.
		$tables['business_profiles'] = "CREATE TABLE {$prefix}business_profiles (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			business_name varchar(191) NOT NULL DEFAULT '',
			business_email varchar(191) NOT NULL DEFAULT '',
			business_phone varchar(50) NOT NULL DEFAULT '',
			address varchar(255) NOT NULL DEFAULT '',
			currency varchar(10) NOT NULL DEFAULT 'USD',
			tax_number varchar(100) NOT NULL DEFAULT '',
			created_at datetime NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id)
		) {$charset_collate};";

		// 2. Products / inventory.
		$tables['products'] = "CREATE TABLE {$prefix}products (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			sku varchar(100) NOT NULL DEFAULT '',
			description text,
			category varchar(191) NOT NULL DEFAULT '',
			price decimal(15,2) NOT NULL DEFAULT 0.00,
			cost_price decimal(15,2) NOT NULL DEFAULT 0.00,
			stock_quantity int(11) NOT NULL DEFAULT 0,
			low_stock_threshold int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY sku (sku),
			KEY category (category),
			KEY status (status)
		) {$charset_collate};";

		// 2b. Inventory movements (supporting audit trail for stock changes).
		$tables['inventory_movements'] = "CREATE TABLE {$prefix}inventory_movements (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			change_type varchar(20) NOT NULL DEFAULT 'adjustment',
			quantity int(11) NOT NULL DEFAULT 0,
			reference varchar(191) NOT NULL DEFAULT '',
			note text,
			created_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY product_id (product_id),
			KEY change_type (change_type)
		) {$charset_collate};";

		// 3. Sales (invoice header).
		$tables['sales'] = "CREATE TABLE {$prefix}sales (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			invoice_number varchar(100) NOT NULL DEFAULT '',
			customer_name varchar(191) NOT NULL DEFAULT '',
			subtotal decimal(15,2) NOT NULL DEFAULT 0.00,
			tax decimal(15,2) NOT NULL DEFAULT 0.00,
			discount decimal(15,2) NOT NULL DEFAULT 0.00,
			total_amount decimal(15,2) NOT NULL DEFAULT 0.00,
			payment_method varchar(50) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'completed',
			note text,
			sale_date datetime NULL DEFAULT NULL,
			created_at datetime NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY sale_date (sale_date),
			KEY status (status),
			KEY invoice_number (invoice_number)
		) {$charset_collate};";

		// 4. Sale items (invoice lines). Owned via their parent sale.
		$tables['sale_items'] = "CREATE TABLE {$prefix}sale_items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sale_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			quantity int(11) NOT NULL DEFAULT 0,
			unit_price decimal(15,2) NOT NULL DEFAULT 0.00,
			subtotal decimal(15,2) NOT NULL DEFAULT 0.00,
			PRIMARY KEY  (id),
			KEY sale_id (sale_id),
			KEY product_id (product_id)
		) {$charset_collate};";

		// 5. Expenses.
		$tables['expenses'] = "CREATE TABLE {$prefix}expenses (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			category varchar(191) NOT NULL DEFAULT '',
			amount decimal(15,2) NOT NULL DEFAULT 0.00,
			description text,
			vendor varchar(191) NOT NULL DEFAULT '',
			payment_method varchar(50) NOT NULL DEFAULT '',
			expense_date datetime NULL DEFAULT NULL,
			note text,
			created_at datetime NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY category (category),
			KEY expense_date (expense_date)
		) {$charset_collate};";

		// 6. AI reports (insights & recommendations generated by the AI service).
		$tables['ai_reports'] = "CREATE TABLE {$prefix}ai_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			report_type varchar(50) NOT NULL DEFAULT '',
			context longtext,
			result longtext,
			metadata longtext,
			model varchar(100) NOT NULL DEFAULT '',
			created_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY report_type (report_type)
		) {$charset_collate};";

		return $tables;
	}
}
