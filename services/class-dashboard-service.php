<?php
/**
 * Dashboard service.
 *
 * Assembles the dashboard payload entirely from real, per-user database
 * aggregates — nothing is hardcoded. Uses single-pass SUM/COUNT/AVG and
 * GROUP BY queries plus a couple of small LIMITed "recent" reads, so the whole
 * dashboard is a handful of indexed queries with no row-by-row work.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only dashboard aggregates.
 */
class SBMS_Dashboard_Service {

	/**
	 * @return SBMS_Sale_Model
	 */
	protected function sales() {
		return new SBMS_Sale_Model();
	}

	/**
	 * @return SBMS_Expense_Model
	 */
	protected function expenses() {
		return new SBMS_Expense_Model();
	}

	/**
	 * @return SBMS_Product_Model
	 */
	protected function products() {
		return new SBMS_Product_Model();
	}

	/**
	 * @return int
	 */
	protected function current_user_id() {
		return (int) get_current_user_id();
	}

	/**
	 * Build the full dashboard overview for the current user.
	 *
	 * @param int $recent How many recent rows to include (1–20).
	 * @return array<string,mixed>
	 */
	public function overview( $recent = 5 ) {
		$user_id = $this->current_user_id();
		$recent  = max( 1, min( 20, (int) $recent ) );

		$sales_summary   = $this->sales()->summary( $user_id );
		$expense_summary = $this->expenses()->summary( $user_id );
		$inventory       = $this->products()->inventory_summary( $user_id );

		$total_sales    = (float) $sales_summary['total'];
		$total_expenses = (float) $expense_summary['total'];

		return array(
			// Headline metrics.
			'totals'            => array(
				'total_sales'        => round( $total_sales, 2 ),
				'total_expenses'     => round( $total_expenses, 2 ),
				'net_profit'         => round( $total_sales - $total_expenses, 2 ),
				'total_products'     => (int) $inventory['total_products'],
				'low_stock_products' => (int) $inventory['low_stock'],
			),

			// Summaries.
			'sales_summary'     => array(
				'count'   => (int) $sales_summary['count'],
				'total'   => round( (float) $sales_summary['total'], 2 ),
				'average' => round( (float) $sales_summary['average'], 2 ),
			),
			'expense_summary'   => array(
				'count'        => (int) $expense_summary['count'],
				'total'        => round( (float) $expense_summary['total'], 2 ),
				'average'      => round( (float) $expense_summary['average'], 2 ),
				'by_category'  => $this->expense_categories( $user_id ),
			),
			'inventory_summary' => array(
				'total_products' => (int) $inventory['total_products'],
				'total_units'    => (int) $inventory['total_units'],
				'retail_value'   => round( (float) $inventory['retail_value'], 2 ),
				'cost_value'     => round( (float) $inventory['cost_value'], 2 ),
				'low_stock'      => (int) $inventory['low_stock'],
				'out_of_stock'   => (int) $inventory['out_of_stock'],
			),

			// Recent activity + attention list.
			'recent_sales'      => $this->recent_sales( $user_id, $recent ),
			'recent_expenses'   => $this->recent_expenses( $user_id, $recent ),
			'low_stock_list'    => $this->low_stock_products( $user_id, $recent ),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Sections
	 * --------------------------------------------------------------------- */

	/**
	 * @param int $user_id User.
	 * @return array<int,array<string,mixed>>
	 */
	protected function expense_categories( $user_id ) {
		$rows = $this->expenses()->by_category( $user_id, 8 );
		$out  = array();

		foreach ( $rows as $row ) {
			$out[] = array(
				'category' => ( '' === (string) $row['category'] ) ? __( 'Uncategorised', 'saas-business-management' ) : (string) $row['category'],
				'count'    => (int) $row['cnt'],
				'total'    => round( (float) $row['total'], 2 ),
			);
		}

		return $out;
	}

	/**
	 * @param int $user_id User.
	 * @param int $limit   How many.
	 * @return array<int,array<string,mixed>>
	 */
	protected function recent_sales( $user_id, $limit ) {
		$out = array();

		foreach ( $this->sales()->recent( $user_id, $limit ) as $row ) {
			$out[] = array(
				'id'             => (int) $row['id'],
				'invoice_number' => (string) $row['invoice_number'],
				'customer_name'  => (string) $row['customer_name'],
				'total_amount'   => (float) $row['total_amount'],
				'sale_date'      => $row['sale_date'],
			);
		}

		return $out;
	}

	/**
	 * @param int $user_id User.
	 * @param int $limit   How many.
	 * @return array<int,array<string,mixed>>
	 */
	protected function recent_expenses( $user_id, $limit ) {
		$out = array();

		foreach ( $this->expenses()->recent( $user_id, $limit ) as $row ) {
			$out[] = array(
				'id'           => (int) $row['id'],
				'category'     => (string) $row['category'],
				'amount'       => (float) $row['amount'],
				'vendor'       => (string) $row['vendor'],
				'expense_date' => $row['expense_date'],
			);
		}

		return $out;
	}

	/**
	 * @param int $user_id User.
	 * @param int $limit   How many.
	 * @return array<int,array<string,mixed>>
	 */
	protected function low_stock_products( $user_id, $limit ) {
		$out = array();

		foreach ( $this->products()->low_stock( $user_id, $limit ) as $row ) {
			$out[] = array(
				'id'                  => (int) $row['id'],
				'name'                => (string) $row['name'],
				'sku'                 => (string) $row['sku'],
				'stock_quantity'      => (int) $row['stock_quantity'],
				'low_stock_threshold' => (int) $row['low_stock_threshold'],
			);
		}

		return $out;
	}
}
