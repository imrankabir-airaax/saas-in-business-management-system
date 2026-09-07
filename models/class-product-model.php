<?php
/**
 * Product / inventory model.
 *
 * @package SBMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for products.
 */
class SBMS_Product_Model extends SBMS_Base_Model {

	/**
	 * @var string
	 */
	protected $table = 'products';

	/**
	 * @var array<string,string>
	 */
	protected $columns = array(
		'user_id'             => '%d',
		'name'                => '%s',
		'sku'                 => '%s',
		'description'         => '%s',
		'category'            => '%s',
		'price'               => '%f',
		'cost_price'          => '%f',
		'stock_quantity'      => '%d',
		'low_stock_threshold' => '%d',
		'status'              => '%s',
	);

	/**
	 * Find one of a user's products by SKU.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $sku     Stock-keeping unit.
	 * @return array<string,mixed>|null
	 */
	public function find_by_sku( $user_id, $sku ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND sku = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id,
				(string) $sku
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Products at or below their low-stock threshold for a user.
	 *
	 * Column-to-column comparison, so it cannot use a simple WHERE array;
	 * the user_id is still parameterised and the column names are internal.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function low_stock( $user_id, $limit = 0 ) {
		$wpdb  = $this->db();
		$table = $this->table();
		$limit = (int) $limit;

		$sql = "SELECT * FROM {$table} WHERE user_id = %d AND stock_quantity <= low_stock_threshold ORDER BY stock_quantity ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $limit > 0 ) {
			$results = $wpdb->get_results(
				$wpdb->prepare( $sql . ' LIMIT %d', (int) $user_id, $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare( $sql, (int) $user_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Whole-inventory rollup for a user, computed in a single query.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string,float|int>
	 */
	public function inventory_summary( $user_id ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_products,
					COALESCE(SUM(stock_quantity),0) AS total_units,
					COALESCE(SUM(price * stock_quantity),0) AS retail_value,
					COALESCE(SUM(cost_price * stock_quantity),0) AS cost_value,
					COALESCE(SUM(CASE WHEN stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END),0) AS low_stock,
					COALESCE(SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END),0) AS out_of_stock
				FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id
			),
			ARRAY_A
		);

		return array(
			'total_products' => $row ? (int) $row['total_products'] : 0,
			'total_units'    => $row ? (int) $row['total_units'] : 0,
			'retail_value'   => $row ? (float) $row['retail_value'] : 0.0,
			'cost_value'     => $row ? (float) $row['cost_value'] : 0.0,
			'low_stock'      => $row ? (int) $row['low_stock'] : 0,
			'out_of_stock'   => $row ? (int) $row['out_of_stock'] : 0,
		);
	}

	/**
	 * Atomically adjust stock by a signed delta (never below zero).
	 *
	 * @param int $id    Product ID.
	 * @param int $delta Positive to add stock, negative to remove.
	 * @return int|false Rows affected, or false on failure.
	 */
	public function adjust_stock( $id, $delta ) {
		$wpdb  = $this->db();
		$table = $this->table();
		$now   = $this->now();

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET stock_quantity = GREATEST(0, stock_quantity + %d), updated_at = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $delta,
				$now,
				(int) $id
			)
		);
	}

	/**
	 * Race-safe conditional stock decrement for selling.
	 *
	 * Decrements only if the row belongs to the user AND has enough stock, in a
	 * single atomic UPDATE. Returns the number of rows affected: 1 on success,
	 * 0 when the product is missing, not owned, or has insufficient stock. This
	 * closes the check-then-act race, so concurrent sales can never oversell.
	 *
	 * @param int $id       Product ID.
	 * @param int $user_id  Owner (business isolation).
	 * @param int $quantity Quantity to remove (must be positive).
	 * @return int|false Rows affected (1 success / 0 refused), or false on error.
	 */
	public function decrement_stock( $id, $user_id, $quantity ) {
		$wpdb  = $this->db();
		$table = $this->table();
		$now   = $this->now();
		$qty   = (int) $quantity;

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET stock_quantity = stock_quantity - %d, updated_at = %s WHERE id = %d AND user_id = %d AND stock_quantity >= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$qty,
				$now,
				(int) $id,
				(int) $user_id,
				$qty
			)
		);
	}

	/**
	 * Search a user's products with optional filters, ordering and paging.
	 *
	 * All values are bound through $wpdb->prepare(); the search term is escaped
	 * with esc_like(); ORDER BY column/direction are whitelisted, never taken
	 * raw from input. Always scoped to the given user_id (business isolation).
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $args    search, category, status, low_stock, orderby, order, limit, offset.
	 * @return array<int,array<string,mixed>>
	 */
	public function search( $user_id, array $args = array() ) {
		$wpdb  = $this->db();
		$table = $this->table();

		list( $where_sql, $params ) = $this->build_search_where( $user_id, $args );

		$allowed_orderby = array( 'name', 'sku', 'category', 'price', 'cost_price', 'stock_quantity', 'low_stock_threshold', 'status', 'created_at', 'updated_at', 'id' );
		$orderby = ( isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true ) ) ? $args['orderby'] : 'name';
		$order   = ( isset( $args['order'] ) && 'DESC' === strtoupper( (string) $args['order'] ) ) ? 'DESC' : 'ASC';
		$limit   = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$offset  = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$sql    = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params = array_merge( $params, array( $limit, $offset ) );

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $params ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count a user's products matching the same filters as search().
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $args    Same filter keys as search().
	 * @return int
	 */
	public function search_count( $user_id, array $args = array() ) {
		$wpdb  = $this->db();
		$table = $this->table();

		list( $where_sql, $params ) = $this->build_search_where( $user_id, $args );

		$sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		// $params always contains at least the user_id, so prepare() has a binding.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build the shared WHERE clause + bound params for search()/search_count().
	 *
	 * @param int   $user_id WordPress user ID (always applied — business isolation).
	 * @param array $args    Filters.
	 * @return array{0:string,1:array<int,mixed>} [ where_sql, params ]
	 */
	protected function build_search_where( $user_id, array $args ) {
		$wpdb    = $this->db();
		$clauses = array( 'user_id = %d' );
		$params  = array( (int) $user_id );

		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$clauses[] = '(name LIKE %s OR sku LIKE %s OR category LIKE %s OR description LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
			$params[]  = $like;
			$params[]  = $like;
		}

		if ( ! empty( $args['category'] ) ) {
			$clauses[] = 'category = %s';
			$params[]  = (string) $args['category'];
		}

		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'status = %s';
			$params[]  = (string) $args['status'];
		}

		if ( ! empty( $args['low_stock'] ) ) {
			// Column-to-column comparison; both identifiers are internal.
			$clauses[] = 'stock_quantity <= low_stock_threshold';
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $clauses );

		return array( $where_sql, $params );
	}
}
