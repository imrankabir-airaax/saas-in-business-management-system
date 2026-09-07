<?php
/**
 * Inventory service.
 *
 * Business logic for products/inventory. Enforces two invariants everywhere:
 *   1. Ownership isolation — every read/write is scoped to the current user's
 *      user_id, so one business can never see or touch another's products.
 *   2. Stock is never negative — quantities are clamped at 0 on the way in, and
 *      the model's atomic adjust uses GREATEST(0, ...).
 *
 * All values are sanitised/validated here (in addition to the REST arg schema),
 * and failures return WP_Error with an appropriate HTTP status — never raw DB
 * errors.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product/inventory operations.
 */
class SBMS_Inventory_Service {

	/**
	 * Default and maximum page sizes for listing.
	 */
	const DEFAULT_PER_PAGE = 20;
	const MAX_PER_PAGE     = 100;

	/**
	 * Product model.
	 *
	 * @return SBMS_Product_Model
	 */
	protected function products() {
		return new SBMS_Product_Model();
	}

	/**
	 * Inventory movement model.
	 *
	 * @return SBMS_Inventory_Movement_Model
	 */
	protected function movements() {
		return new SBMS_Inventory_Movement_Model();
	}

	/**
	 * Current user ID (resource owner).
	 *
	 * @return int
	 */
	protected function current_user_id() {
		return (int) get_current_user_id();
	}

	/* --------------------------------------------------------------------- *
	 * Read
	 * --------------------------------------------------------------------- */

	/**
	 * List the current user's products with search, filters and pagination.
	 *
	 * @param array<string,mixed> $args Query args from the request.
	 * @return array<string,mixed> items + pagination.
	 */
	public function list_products( array $args ) {
		$user_id = $this->current_user_id();

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = array(
			'search'    => isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '',
			'category'  => isset( $args['category'] ) ? sanitize_text_field( (string) $args['category'] ) : '',
			'status'    => isset( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : '',
			'low_stock' => ! empty( $args['low_stock'] ),
			'orderby'   => isset( $args['orderby'] ) ? (string) $args['orderby'] : 'name',
			'order'     => isset( $args['order'] ) ? (string) $args['order'] : 'ASC',
			'limit'     => $per_page,
			'offset'    => $offset,
		);

		$model = $this->products();
		$items = $model->search( $user_id, $query );
		$total = $model->search_count( $user_id, $query );

		return array(
			'items'      => array_map( array( $this, 'format_product' ), $items ),
			'pagination' => array(
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			),
		);
	}

	/**
	 * Fetch a single owned product.
	 *
	 * @param int $id Product ID.
	 * @return array<string,mixed>|WP_Error Formatted product, or 404 WP_Error.
	 */
	public function get_product( $id ) {
		$product = $this->products()->find( (int) $id );

		if ( ! $this->owned( $product ) ) {
			return $this->not_found();
		}

		return $this->format_product( $product );
	}

	/**
	 * The current user's low-stock products.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function low_stock_products() {
		$rows = $this->products()->low_stock( $this->current_user_id() );

		return array_map( array( $this, 'format_product' ), $rows );
	}

	/* --------------------------------------------------------------------- *
	 * Write
	 * --------------------------------------------------------------------- */

	/**
	 * Create a product owned by the current user.
	 *
	 * @param array<string,mixed> $input Raw fields from the request.
	 * @return array<string,mixed>|WP_Error Formatted product, or WP_Error.
	 */
	public function create_product( array $input ) {
		$data = $this->sanitize( $input, true );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$user_id            = $this->current_user_id();
		$data['user_id']    = $user_id;

		if ( '' !== $data['sku'] && $this->products()->find_by_sku( $user_id, $data['sku'] ) ) {
			return new WP_Error( 'sbms_duplicate_sku', __( 'A product with that SKU already exists.', 'saas-business-management' ), array( 'status' => 409 ) );
		}

		$id = $this->products()->create( $data );
		if ( ! $id ) {
			return new WP_Error( 'sbms_create_failed', __( 'The product could not be created.', 'saas-business-management' ), array( 'status' => 500 ) );
		}

		if ( $data['stock_quantity'] > 0 ) {
			$this->log_movement( $user_id, (int) $id, 'initial', (int) $data['stock_quantity'], 'create' );
		}

		return $this->get_product( $id );
	}

	/**
	 * Update an owned product.
	 *
	 * @param int                 $id    Product ID.
	 * @param array<string,mixed> $input Raw fields from the request.
	 * @return array<string,mixed>|WP_Error Formatted product, or WP_Error.
	 */
	public function update_product( $id, array $input ) {
		$product = $this->products()->find( (int) $id );
		if ( ! $this->owned( $product ) ) {
			return $this->not_found();
		}

		$data = $this->sanitize( $input, false );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data ) ) {
			// Nothing to change; return the current state.
			return $this->format_product( $product );
		}

		$user_id = (int) $product['user_id'];

		if ( isset( $data['sku'] ) && '' !== $data['sku'] ) {
			$existing = $this->products()->find_by_sku( $user_id, $data['sku'] );
			if ( $existing && (int) $existing['id'] !== (int) $id ) {
				return new WP_Error( 'sbms_duplicate_sku', __( 'A product with that SKU already exists.', 'saas-business-management' ), array( 'status' => 409 ) );
			}
		}

		$old_stock = (int) $product['stock_quantity'];

		$updated = $this->products()->update( (int) $id, $data );
		if ( false === $updated ) {
			return new WP_Error( 'sbms_update_failed', __( 'The product could not be updated.', 'saas-business-management' ), array( 'status' => 500 ) );
		}

		if ( array_key_exists( 'stock_quantity', $data ) && (int) $data['stock_quantity'] !== $old_stock ) {
			$this->log_movement( $user_id, (int) $id, 'adjustment', (int) $data['stock_quantity'] - $old_stock, 'update' );
		}

		return $this->get_product( $id );
	}

	/**
	 * Delete an owned product.
	 *
	 * @param int $id Product ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function delete_product( $id ) {
		$product = $this->products()->find( (int) $id );
		if ( ! $this->owned( $product ) ) {
			return $this->not_found();
		}

		$deleted = $this->products()->delete( (int) $id );
		if ( false === $deleted ) {
			return new WP_Error( 'sbms_delete_failed', __( 'The product could not be deleted.', 'saas-business-management' ), array( 'status' => 500 ) );
		}

		return array(
			'id'      => (int) $id,
			'deleted' => true,
		);
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Whether a fetched row exists and belongs to the current user.
	 *
	 * @param array<string,mixed>|null $product Product row or null.
	 * @return bool
	 */
	protected function owned( $product ) {
		return is_array( $product )
			&& isset( $product['user_id'] )
			&& (int) $product['user_id'] === $this->current_user_id();
	}

	/**
	 * Standard 404 (also used when a product exists but is not owned, so
	 * existence is never revealed across businesses).
	 *
	 * @return WP_Error
	 */
	protected function not_found() {
		return new WP_Error( 'sbms_not_found', __( 'Product not found.', 'saas-business-management' ), array( 'status' => 404 ) );
	}

	/**
	 * Record an inventory movement (best-effort; never blocks the main op).
	 *
	 * @param int    $user_id    Owner.
	 * @param int    $product_id Product.
	 * @param string $type       Movement type.
	 * @param int    $quantity   Signed quantity.
	 * @param string $reference  Reference tag.
	 * @return void
	 */
	protected function log_movement( $user_id, $product_id, $type, $quantity, $reference ) {
		$this->movements()->create(
			array(
				'user_id'     => (int) $user_id,
				'product_id'  => (int) $product_id,
				'change_type' => $type,
				'quantity'    => (int) $quantity,
				'reference'   => $reference,
			)
		);
	}

	/**
	 * Sanitise and validate product input.
	 *
	 * @param array<string,mixed> $input   Raw input.
	 * @param bool                $is_create Whether this is a create (name required, defaults filled).
	 * @return array<string,mixed>|WP_Error Clean data, or WP_Error (400).
	 */
	protected function sanitize( array $input, $is_create ) {
		$out = array();

		// Name.
		if ( $is_create || array_key_exists( 'name', $input ) ) {
			$name = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';
			if ( '' === $name ) {
				return new WP_Error( 'sbms_invalid_name', __( 'Product name is required.', 'saas-business-management' ), array( 'status' => 400 ) );
			}
			$out['name'] = $name;
		}

		// Free-text strings.
		if ( array_key_exists( 'sku', $input ) ) {
			$out['sku'] = sanitize_text_field( (string) $input['sku'] );
		}
		if ( array_key_exists( 'category', $input ) ) {
			$out['category'] = sanitize_text_field( (string) $input['category'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$out['description'] = sanitize_textarea_field( (string) $input['description'] );
		}

		// Status (whitelisted).
		if ( array_key_exists( 'status', $input ) ) {
			$status        = sanitize_text_field( (string) $input['status'] );
			$out['status'] = in_array( $status, array( 'active', 'inactive' ), true ) ? $status : 'active';
		}

		// Prices (>= 0).
		foreach ( array( 'price', 'cost_price' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				if ( ! is_numeric( $input[ $field ] ) ) {
					return new WP_Error( 'sbms_invalid_price', __( 'Prices must be numeric.', 'saas-business-management' ), array( 'status' => 400 ) );
				}
				$value = (float) $input[ $field ];
				if ( $value < 0 ) {
					return new WP_Error( 'sbms_invalid_price', __( 'Prices cannot be negative.', 'saas-business-management' ), array( 'status' => 400 ) );
				}
				$out[ $field ] = round( $value, 2 );
			}
		}

		// Integer quantities (clamped at 0 — stock never negative).
		foreach ( array( 'stock_quantity', 'low_stock_threshold' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				if ( ! is_numeric( $input[ $field ] ) ) {
					return new WP_Error( 'sbms_invalid_quantity', __( 'Quantities must be whole numbers.', 'saas-business-management' ), array( 'status' => 400 ) );
				}
				$value         = (int) $input[ $field ];
				$out[ $field ] = ( $value < 0 ) ? 0 : $value;
			}
		}

		// Fill defaults on create so every column has a sane value.
		if ( $is_create ) {
			$out += array(
				'sku'                 => '',
				'category'            => '',
				'description'         => '',
				'status'              => 'active',
				'price'               => 0.0,
				'cost_price'          => 0.0,
				'stock_quantity'      => 0,
				'low_stock_threshold' => 0,
			);
		}

		return $out;
	}

	/**
	 * Shape a DB row into the public product payload (adds low_stock flag).
	 *
	 * @param array<string,mixed> $row Product row.
	 * @return array<string,mixed>
	 */
	public function format_product( array $row ) {
		$stock     = (int) $row['stock_quantity'];
		$threshold = (int) $row['low_stock_threshold'];

		return array(
			'id'                  => (int) $row['id'],
			'name'                => (string) $row['name'],
			'sku'                 => (string) $row['sku'],
			'description'         => (string) $row['description'],
			'category'            => (string) $row['category'],
			'price'               => (float) $row['price'],
			'cost_price'          => (float) $row['cost_price'],
			'stock_quantity'      => $stock,
			'low_stock_threshold' => $threshold,
			'low_stock'           => ( $stock <= $threshold ),
			'status'              => (string) $row['status'],
			'created_at'          => $row['created_at'],
			'updated_at'          => $row['updated_at'],
		);
	}
}
