<?php
/**
 * Sale service.
 *
 * Owns the critical create-sale workflow. Everything financial is computed on
 * the server from the stored product price — unit prices, subtotals and totals
 * sent by the client are ignored. A sale mutates three tables (sales,
 * sale_items, products) and must be all-or-nothing, so the write path runs
 * inside a DB transaction and rolls back on any failure. Stock is reduced with
 * a race-safe conditional UPDATE, so concurrent sales can never oversell.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sales operations.
 */
class SBMS_Sale_Service {

	const DEFAULT_PER_PAGE = 20;
	const MAX_PER_PAGE     = 100;

	/**
	 * @return SBMS_Sale_Model
	 */
	protected function sales() {
		return new SBMS_Sale_Model();
	}

	/**
	 * @return SBMS_Sale_Item_Model
	 */
	protected function items() {
		return new SBMS_Sale_Item_Model();
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

	/* --------------------------------------------------------------------- *
	 * Read
	 * --------------------------------------------------------------------- */

	/**
	 * List the current user's sales (paginated, newest first).
	 *
	 * @param array<string,mixed> $args page, per_page.
	 * @return array<string,mixed>
	 */
	public function list_sales( array $args ) {
		$user_id = $this->current_user_id();

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$rows = $this->sales()->for_user(
			$user_id,
			array(
				'orderby' => 'id',
				'order'   => 'DESC',
				'limit'   => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
			)
		);

		$total = $this->sales()->count_for_user( $user_id );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->format_sale( $row );
		}

		return array(
			'items'      => $items,
			'pagination' => array(
				'total'       => (int) $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			),
		);
	}

	/**
	 * Fetch one owned sale with its line items.
	 *
	 * @param int $id Sale ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_sale( $id ) {
		$sale = $this->sales()->find( (int) $id );

		if ( ! $this->owned( $sale ) ) {
			return $this->not_found();
		}

		$data          = $this->format_sale( $sale );
		$data['items'] = $this->line_items_for( (int) $id );

		return $data;
	}

	/* --------------------------------------------------------------------- *
	 * Create (transactional)
	 * --------------------------------------------------------------------- */

	/**
	 * Create a sale: validate, compute totals server-side, then atomically
	 * write the sale, its items and the stock reductions.
	 *
	 * @param array<string,mixed> $input Raw request data.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_sale( array $input ) {
		$user_id = $this->current_user_id();

		// 1. Validate the line items and load + verify every product.
		$prepared = $this->prepare_line_items( $input, $user_id );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		// 2. Compute all money on the server.
		$subtotal = 0.0;
		foreach ( $prepared as $line ) {
			$subtotal += $line['subtotal'];
		}
		$tax      = $this->non_negative_money( isset( $input['tax'] ) ? $input['tax'] : 0 );
		$discount = $this->non_negative_money( isset( $input['discount'] ) ? $input['discount'] : 0 );
		if ( $discount > $subtotal + $tax ) {
			$discount = $subtotal + $tax; // never produce a negative total.
		}
		$total = round( $subtotal + $tax - $discount, 2 );

		$meta = $this->sanitize_meta( $input );

		global $wpdb;

		// 3. Begin the transaction (InnoDB). Autocommit is restored implicitly
		//    by COMMIT/ROLLBACK below.
		$wpdb->query( 'START TRANSACTION' );

		// 4. Reduce stock first, race-safely. If any line can't be satisfied,
		//    roll the whole thing back — nothing is left half-applied.
		foreach ( $prepared as $line ) {
			$done = $this->products()->decrement_stock( $line['product_id'], $user_id, $line['quantity'] );
			if ( 1 !== (int) $done ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error(
					'sbms_insufficient_stock',
					sprintf(
						/* translators: %s: product name */
						__( 'Not enough stock for "%s".', 'saas-business-management' ),
						$line['name']
					),
					array( 'status' => 409 )
				);
			}
		}

		// 5. Create the sale header.
		$sale_id = $this->sales()->create(
			array(
				'user_id'        => $user_id,
				'invoice_number' => '',
				'customer_name'  => $meta['customer_name'],
				'subtotal'       => $subtotal,
				'tax'            => $tax,
				'discount'       => $discount,
				'total_amount'   => $total,
				'payment_method' => $meta['payment_method'],
				'status'         => 'completed',
				'note'           => $meta['note'],
				'sale_date'      => $meta['sale_date'],
			)
		);

		if ( ! $sale_id ) {
			$wpdb->query( 'ROLLBACK' );
			return $this->write_failed();
		}

		// 6. Assign a human invoice number derived from the new ID.
		$this->sales()->update(
			(int) $sale_id,
			array( 'invoice_number' => 'INV-' . str_pad( (string) $sale_id, 6, '0', STR_PAD_LEFT ) )
		);

		// 7. Create the line items.
		foreach ( $prepared as $line ) {
			$item_id = $this->items()->create(
				array(
					'sale_id'    => (int) $sale_id,
					'product_id' => $line['product_id'],
					'quantity'   => $line['quantity'],
					'unit_price' => $line['unit_price'],
					'subtotal'   => $line['subtotal'],
				)
			);

			if ( ! $item_id ) {
				$wpdb->query( 'ROLLBACK' );
				return $this->write_failed();
			}
		}

		// 8. All good — commit.
		$wpdb->query( 'COMMIT' );

		return $this->get_sale( (int) $sale_id );
	}

	/* --------------------------------------------------------------------- *
	 * Validation helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Validate items, load each product, verify existence/ownership/quantity,
	 * and attach the server-side unit price + line subtotal.
	 *
	 * Does NOT check stock availability here beyond a read-time sanity check —
	 * the authoritative availability guarantee is the conditional UPDATE in
	 * create_sale(). Aggregates duplicate product lines so availability is
	 * assessed on the combined quantity.
	 *
	 * @param array<string,mixed> $input   Raw request data.
	 * @param int                 $user_id Current user.
	 * @return array<int,array<string,mixed>>|WP_Error Prepared lines or error.
	 */
	protected function prepare_line_items( array $input, $user_id ) {
		$raw = isset( $input['items'] ) ? $input['items'] : null;

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return new WP_Error( 'sbms_no_items', __( 'A sale must include at least one item.', 'saas-business-management' ), array( 'status' => 400 ) );
		}

		// Aggregate requested quantity per product id.
		$requested = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['product_id'] ) ) {
				return new WP_Error( 'sbms_invalid_item', __( 'Each item needs a product and a quantity.', 'saas-business-management' ), array( 'status' => 400 ) );
			}

			$pid = (int) $item['product_id'];
			$qty = isset( $item['quantity'] ) ? $item['quantity'] : 0;

			if ( ! is_numeric( $qty ) || (int) $qty < 1 || (float) $qty !== floor( (float) $qty ) ) {
				return new WP_Error( 'sbms_invalid_quantity', __( 'Quantity must be a whole number of at least 1.', 'saas-business-management' ), array( 'status' => 400 ) );
			}

			if ( $pid < 1 ) {
				return new WP_Error( 'sbms_invalid_product', __( 'Invalid product.', 'saas-business-management' ), array( 'status' => 400 ) );
			}

			if ( ! isset( $requested[ $pid ] ) ) {
				$requested[ $pid ] = 0;
			}
			$requested[ $pid ] += (int) $qty;
		}

		$prepared = array();
		foreach ( $requested as $pid => $qty ) {
			$product = $this->products()->find( $pid );

			// Exists + belongs to this business.
			if ( ! is_array( $product ) || (int) $product['user_id'] !== (int) $user_id ) {
				return new WP_Error(
					'sbms_invalid_product',
					/* translators: %d: product id */
					sprintf( __( 'Product %d is not available.', 'saas-business-management' ), $pid ),
					array( 'status' => 404 )
				);
			}

			// Read-time availability check (fast, friendly error). The atomic
			// guarantee is still the conditional decrement in create_sale().
			if ( (int) $product['stock_quantity'] < $qty ) {
				return new WP_Error(
					'sbms_insufficient_stock',
					sprintf(
						/* translators: 1: product name, 2: available quantity */
						__( 'Not enough stock for "%1$s" (%2$d available).', 'saas-business-management' ),
						$product['name'],
						(int) $product['stock_quantity']
					),
					array( 'status' => 409 )
				);
			}

			$unit_price = round( (float) $product['price'], 2 );
			$line_total = round( $unit_price * $qty, 2 );

			$prepared[] = array(
				'product_id' => (int) $pid,
				'name'       => (string) $product['name'],
				'quantity'   => (int) $qty,
				'unit_price' => $unit_price,
				'subtotal'   => $line_total,
			);
		}

		return $prepared;
	}

	/**
	 * Sanitise the non-financial sale metadata.
	 *
	 * @param array<string,mixed> $input Raw request data.
	 * @return array<string,string>
	 */
	protected function sanitize_meta( array $input ) {
		$customer = isset( $input['customer_name'] ) ? sanitize_text_field( (string) $input['customer_name'] ) : '';
		$payment  = isset( $input['payment_method'] ) ? sanitize_text_field( (string) $input['payment_method'] ) : '';
		$note     = isset( $input['note'] ) ? sanitize_textarea_field( (string) $input['note'] ) : '';

		return array(
			'customer_name'  => $customer,
			'payment_method' => $payment,
			'note'           => $note,
			'sale_date'      => $this->normalize_date( isset( $input['sale_date'] ) ? $input['sale_date'] : '' ),
		);
	}

	/**
	 * Normalise a sale date to Y-m-d H:i:s, defaulting to now.
	 *
	 * @param mixed $value Raw date.
	 * @return string
	 */
	protected function normalize_date( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' !== $value ) {
			$ts = strtotime( $value );
			if ( false !== $ts ) {
				return gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		return current_time( 'mysql' );
	}

	/**
	 * Coerce a money-ish value to a non-negative, 2dp float.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	protected function non_negative_money( $value ) {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}
		$n = round( (float) $value, 2 );

		return $n < 0 ? 0.0 : $n;
	}

	/* --------------------------------------------------------------------- *
	 * Shaping
	 * --------------------------------------------------------------------- */

	/**
	 * Line items for a sale, enriched with the product name for display.
	 *
	 * @param int $sale_id Sale ID.
	 * @return array<int,array<string,mixed>>
	 */
	protected function line_items_for( $sale_id ) {
		$rows = $this->items()->for_sale( (int) $sale_id );
		$out  = array();

		foreach ( $rows as $row ) {
			$product = $this->products()->find( (int) $row['product_id'] );

			$out[] = array(
				'id'         => (int) $row['id'],
				'product_id' => (int) $row['product_id'],
				'name'       => is_array( $product ) ? (string) $product['name'] : '',
				'quantity'   => (int) $row['quantity'],
				'unit_price' => (float) $row['unit_price'],
				'subtotal'   => (float) $row['subtotal'],
			);
		}

		return $out;
	}

	/**
	 * Shape a sale row for output.
	 *
	 * @param array<string,mixed> $row Sale row.
	 * @return array<string,mixed>
	 */
	protected function format_sale( array $row ) {
		return array(
			'id'             => (int) $row['id'],
			'invoice_number' => (string) $row['invoice_number'],
			'customer_name'  => (string) $row['customer_name'],
			'subtotal'       => (float) $row['subtotal'],
			'tax'            => (float) $row['tax'],
			'discount'       => (float) $row['discount'],
			'total_amount'   => (float) $row['total_amount'],
			'payment_method' => (string) $row['payment_method'],
			'status'         => (string) $row['status'],
			'note'           => (string) $row['note'],
			'sale_date'      => $row['sale_date'],
			'created_at'     => $row['created_at'],
		);
	}

	/* --------------------------------------------------------------------- *
	 * Small helpers
	 * --------------------------------------------------------------------- */

	/**
	 * @param array<string,mixed>|null $sale Sale row.
	 * @return bool
	 */
	protected function owned( $sale ) {
		return is_array( $sale )
			&& isset( $sale['user_id'] )
			&& (int) $sale['user_id'] === $this->current_user_id();
	}

	/**
	 * @return WP_Error
	 */
	protected function not_found() {
		return new WP_Error( 'sbms_not_found', __( 'Sale not found.', 'saas-business-management' ), array( 'status' => 404 ) );
	}

	/**
	 * @return WP_Error
	 */
	protected function write_failed() {
		return new WP_Error( 'sbms_sale_failed', __( 'The sale could not be completed. No changes were made.', 'saas-business-management' ), array( 'status' => 500 ) );
	}
}
