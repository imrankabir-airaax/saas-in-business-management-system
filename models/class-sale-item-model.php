<?php
/**
 * Sale item (invoice line) model. Owned indirectly via its parent sale.
 *
 * @package SBMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for sale_items.
 */
class SBMS_Sale_Item_Model extends SBMS_Base_Model {

	/**
	 * @var string
	 */
	protected $table = 'sale_items';

	/**
	 * Owned through the parent sale, not directly by a user.
	 *
	 * @var string|null
	 */
	protected $user_column = null;

	/**
	 * This table carries no timestamps.
	 *
	 * @var string|null
	 */
	protected $created_column = null;

	/**
	 * @var string|null
	 */
	protected $updated_column = null;

	/**
	 * @var array<string,string>
	 */
	protected $columns = array(
		'sale_id'    => '%d',
		'product_id' => '%d',
		'quantity'   => '%d',
		'unit_price' => '%f',
		'subtotal'   => '%f',
	);

	/**
	 * All line items for a sale.
	 *
	 * @param int $sale_id Sale ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_sale( $sale_id ) {
		return $this->all(
			array(
				'where'   => array( 'sale_id' => (int) $sale_id ),
				'orderby' => 'id',
				'order'   => 'ASC',
				'limit'   => 1000,
			)
		);
	}

	/**
	 * Delete every line item belonging to a sale.
	 *
	 * @param int $sale_id Sale ID.
	 * @return int|false Rows deleted, or false on failure.
	 */
	public function delete_for_sale( $sale_id ) {
		$wpdb = $this->db();

		return $wpdb->delete(
			$this->table(),
			array( 'sale_id' => (int) $sale_id ),
			array( '%d' )
		);
	}
}
