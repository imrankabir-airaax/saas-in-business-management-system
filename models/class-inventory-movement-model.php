<?php
/**
 * Inventory movement model (stock in / out / adjustment audit trail).
 *
 * @package SBMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access for inventory_movements.
 */
class SBMS_Inventory_Movement_Model extends SBMS_Base_Model {

	/**
	 * @var string
	 */
	protected $table = 'inventory_movements';

	/**
	 * This table records creation time only.
	 *
	 * @var string|null
	 */
	protected $updated_column = null;

	/**
	 * @var array<string,string>
	 */
	protected $columns = array(
		'user_id'     => '%d',
		'product_id'  => '%d',
		'change_type' => '%s',
		'quantity'    => '%d',
		'reference'   => '%s',
		'note'        => '%s',
	);

	/**
	 * All movements for a product, newest first.
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit      Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_product( $product_id, $limit = 100 ) {
		return $this->all(
			array(
				'where' => array( 'product_id' => (int) $product_id ),
				'limit' => (int) $limit,
			)
		);
	}
}
