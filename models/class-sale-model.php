<?php
/**
 * Sale (invoice header) model.
 *
 * @package SBMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for sales.
 */
class SBMS_Sale_Model extends SBMS_Base_Model {

	/**
	 * @var string
	 */
	protected $table = 'sales';

	/**
	 * @var array<string,string>
	 */
	protected $columns = array(
		'user_id'        => '%d',
		'invoice_number' => '%s',
		'customer_name'  => '%s',
		'subtotal'       => '%f',
		'tax'            => '%f',
		'discount'       => '%f',
		'total_amount'   => '%f',
		'payment_method' => '%s',
		'status'         => '%s',
		'note'           => '%s',
		'sale_date'      => '%s',
	);

	/**
	 * Sum of total_amount for a user, optionally within a datetime range.
	 *
	 * @param int         $user_id WordPress user ID.
	 * @param string|null $from    Inclusive lower bound (MySQL datetime) or null.
	 * @param string|null $to      Inclusive upper bound (MySQL datetime) or null.
	 * @return float
	 */
	public function total_revenue( $user_id, $from = null, $to = null ) {
		$wpdb  = $this->db();
		$table = $this->table();

		if ( $from && $to ) {
			$sql = $wpdb->prepare(
				"SELECT COALESCE(SUM(total_amount),0) FROM {$table} WHERE user_id = %d AND sale_date BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id,
				(string) $from,
				(string) $to
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT COALESCE(SUM(total_amount),0) FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $user_id
			);
		}

		return (float) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count + sum + average of a user's sales in one query.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array{count:int,total:float,average:float}
	 */
	public function summary( $user_id ) {
		$wpdb  = $this->db();
		$table = $this->table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total, COALESCE(AVG(total_amount),0) AS avg_amount FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * Most recent sales for a user.
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
