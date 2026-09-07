<?php
/**
 * Expense model.
 *
 * @package SBMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for expenses.
 */
class SBMS_Expense_Model extends SBMS_Base_Model {

	/**
	 * @var string
	 */
	protected $table = 'expenses';

	/**
	 * @var array<string,string>
	 */
	protected $columns = array(
		'user_id'        => '%d',
		'category'       => '%s',
		'amount'         => '%f',
		'description'    => '%s',
		'vendor'         => '%s',
		'payment_method' => '%s',
		'expense_date'   => '%s',
		'note'           => '%s',
	);

	/**
	 * Sum of expense amounts for a user, optionally within a datetime range.
	 *
	 * @param int         $user_id WordPress user ID.
	 * @param string|null $from    Inclusive lower bound (MySQL datetime) or null.
	 * @param string|null $to      Inclusive upper bound (MySQL datetime) or null.
	 * @return float
	 */
	public function total_expenses( $user_id, $from = null, $to = null ) {
		$wpdb  = $this->db();
		$table = $this->table();

		if ( $from && $to ) {
			$sql = $wpdb->prepare(
				"SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE user_id = %d AND expense_date BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id,
				(string) $from,
				(string) $to
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id
			);
		}

		return (float) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Search a user's expenses with optional filters, ordering and paging.
	 *
	 * Prepared throughout; search term escaped with esc_like; ORDER BY column
	 * and direction whitelisted; always scoped to user_id (business isolation).
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $args    search, category, from, to, orderby, order, limit, offset.
	 * @return array<int,array<string,mixed>>
	 */
	public function search( $user_id, array $args = array() ) {
		$wpdb  = $this->db();
		$table = $this->table();

		list( $where_sql, $params ) = $this->build_search_where( $user_id, $args );

		$allowed = array( 'category', 'amount', 'vendor', 'expense_date', 'created_at', 'id' );
		$orderby = ( isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed, true ) ) ? $args['orderby'] : 'expense_date';
		$order   = ( isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';
		$limit   = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$offset  = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		// Secondary sort by id keeps paging stable when many rows share a date.
		$sql    = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
		$params = array_merge( $params, array( $limit, $offset ) );

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $params ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count a user's expenses matching the same filters as search().
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $args    Same filter keys as search().
	 * @return int
	 */
	public function search_count( $user_id, array $args = array() ) {
		$wpdb  = $this->db();
		$table = $this->table();

		list( $where_sql, $params ) = $this->build_search_where( $user_id, $args );

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Shared WHERE builder for search()/search_count().
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $args    Filters.
	 * @return array{0:string,1:array<int,mixed>}
	 */
	protected function build_search_where( $user_id, array $args ) {
		$wpdb    = $this->db();
		$clauses = array( 'user_id = %d' );
		$params  = array( (int) $user_id );

		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$clauses[] = '(category LIKE %s OR description LIKE %s OR vendor LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
			$params[]  = $like;
		}
		if ( ! empty( $args['category'] ) ) {
			$clauses[] = 'category = %s';
			$params[]  = (string) $args['category'];
		}
		if ( ! empty( $args['from'] ) ) {
			$clauses[] = 'expense_date >= %s';
			$params[]  = (string) $args['from'];
		}
		if ( ! empty( $args['to'] ) ) {
			$clauses[] = 'expense_date <= %s';
			$params[]  = (string) $args['to'];
		}

		return array( 'WHERE ' . implode( ' AND ', $clauses ), $params );
	}

	/**
	 * Count + sum + average of a user's expenses in one query.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array{count:int,total:float,average:float}
	 */
	public function summary( $user_id ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total, COALESCE(AVG(amount),0) AS avg_amount FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id
			),
			ARRAY_A
		);

		return array(
			'count'   => $row ? (int) $row['cnt'] : 0,
			'total'   => $row ? (float) $row['total'] : 0.0,
			'average' => $row ? (float) $row['avg_amount'] : 0.0,
		);
	}

	/**
	 * Totals grouped by category (largest first).
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $limit   Max categories.
	 * @return array<int,array<string,mixed>>
	 */
	public function by_category( $user_id, $limit = 10 ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT category, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM {$table} WHERE user_id = %d GROUP BY category ORDER BY total DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id,
				max( 1, (int) $limit )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Most recent expenses for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $limit   How many.
	 * @return array<int,array<string,mixed>>
	 */
	public function recent( $user_id, $limit = 5 ) {
		return $this->for_user(
			$user_id,
			array(
				'orderby' => 'id',
				'order'   => 'DESC',
				'limit'   => max( 1, (int) $limit ),
			)
		);
	}
}
