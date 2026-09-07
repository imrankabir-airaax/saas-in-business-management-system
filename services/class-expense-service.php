<?php
/**
 * Expense service.
 *
 * CRUD for expenses. Every read/write is scoped to the current user's user_id
 * (business isolation), amounts are validated and never negative, and failures
 * return WP_Error with an appropriate HTTP status rather than raw DB errors.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expense operations.
 */
class SBMS_Expense_Service {

	const DEFAULT_PER_PAGE = 20;
	const MAX_PER_PAGE     = 100;

	/**
	 * @return SBMS_Expense_Model
	 */
	protected function expenses() {
		return new SBMS_Expense_Model();
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
	 * List the current user's expenses with search, filters and pagination.
	 *
	 * @param array<string,mixed> $args Request args.
	 * @return array<string,mixed>
	 */
	public function list_expenses( array $args ) {
		$user_id = $this->current_user_id();

		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$query = array(
			'search'   => isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '',
			'category' => isset( $args['category'] ) ? sanitize_text_field( (string) $args['category'] ) : '',
			'from'     => isset( $args['from'] ) ? $this->normalize_date( $args['from'], '' ) : '',
			'to'       => isset( $args['to'] ) ? $this->normalize_date( $args['to'], '' ) : '',
			'orderby'  => isset( $args['orderby'] ) ? (string) $args['orderby'] : 'expense_date',
			'order'    => isset( $args['order'] ) ? (string) $args['order'] : 'DESC',
			'limit'    => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
		);

		$model = $this->expenses();
		$items = array_map( array( $this, 'format_expense' ), $model->search( $user_id, $query ) );
		$total = $model->search_count( $user_id, $query );

		return array(
			'items'      => $items,
			'pagination' => array(
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			),
		);
	}

	/**
	 * Fetch one owned expense.
	 *
	 * @param int $id Expense ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_expense( $id ) {
		$expense = $this->expenses()->find( (int) $id );

		if ( ! $this->owned( $expense ) ) {
			return $this->not_found();
		}

		return $this->format_expense( $expense );
	}

	/* --------------------------------------------------------------------- *
	 * Write
	 * --------------------------------------------------------------------- */

	/**
	 * Create an expense owned by the current user.
	 *
	 * @param array<string,mixed> $input Raw fields.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_expense( array $input ) {
		$data = $this->sanitize( $input, true );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$data['user_id'] = $this->current_user_id();

		$id = $this->expenses()->create( $data );
		if ( ! $id ) {
			return new WP_Error( 'sbms_create_failed', __( 'The expense could not be created.', 'saas-business-management' ), array( 'status' => 500 ) );
		}

		return $this->get_expense( $id );
	}

	/**
	 * Update an owned expense.
	 *
	 * @param int                 $id    Expense ID.
	 * @param array<string,mixed> $input Raw fields.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_expense( $id, array $input ) {
		$expense = $this->expenses()->find( (int) $id );
		if ( ! $this->owned( $expense ) ) {
			return $this->not_found();
		}

		$data = $this->sanitize( $input, false );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data ) ) {
			return $this->format_expense( $expense );
		}

		$updated = $this->expenses()->update( (int) $id, $data );
		if ( false === $updated ) {
			return new WP_Error( 'sbms_update_failed', __( 'The expense could not be updated.', 'saas-business-management' ), array( 'status' => 500 ) );
		}

		return $this->get_expense( $id );
	}

	/**
	 * Delete an owned expense.
	 *
	 * @param int $id Expense ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function delete_expense( $id ) {
		$expense = $this->expenses()->find( (int) $id );
		if ( ! $this->owned( $expense ) ) {
			return $this->not_found();
		}

		$deleted = $this->expenses()->delete( (int) $id );
		if ( false === $deleted ) {
			return new WP_Error( 'sbms_delete_failed', __( 'The expense could not be deleted.', 'saas-business-management' ), array( 'status' => 500 ) );
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
	 * @param array<string,mixed>|null $expense Row.
	 * @return bool
	 */
	protected function owned( $expense ) {
		return is_array( $expense )
			&& isset( $expense['user_id'] )
			&& (int) $expense['user_id'] === $this->current_user_id();
	}

	/**
	 * @return WP_Error
	 */
	protected function not_found() {
		return new WP_Error( 'sbms_not_found', __( 'Expense not found.', 'saas-business-management' ), array( 'status' => 404 ) );
	}

	/**
	 * Sanitise + validate expense input.
	 *
	 * @param array<string,mixed> $input     Raw input.
	 * @param bool                $is_create Whether amount is required.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function sanitize( array $input, $is_create ) {
		$out = array();

		// Amount (required on create; never negative; numeric).
		if ( $is_create || array_key_exists( 'amount', $input ) ) {
			if ( ! isset( $input['amount'] ) || ! is_numeric( $input['amount'] ) ) {
				return new WP_Error( 'sbms_invalid_amount', __( 'A numeric amount is required.', 'saas-business-management' ), array( 'status' => 400 ) );
			}
			$amount = round( (float) $input['amount'], 2 );
			if ( $amount < 0 ) {
				return new WP_Error( 'sbms_invalid_amount', __( 'Amount cannot be negative.', 'saas-business-management' ), array( 'status' => 400 ) );
			}
			$out['amount'] = $amount;
		}

		if ( array_key_exists( 'category', $input ) ) {
			$out['category'] = sanitize_text_field( (string) $input['category'] );
		}
		if ( array_key_exists( 'vendor', $input ) ) {
			$out['vendor'] = sanitize_text_field( (string) $input['vendor'] );
		}
		if ( array_key_exists( 'payment_method', $input ) ) {
			$out['payment_method'] = sanitize_text_field( (string) $input['payment_method'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$out['description'] = sanitize_textarea_field( (string) $input['description'] );
		}
		if ( array_key_exists( 'note', $input ) ) {
			$out['note'] = sanitize_textarea_field( (string) $input['note'] );
		}
		if ( $is_create || array_key_exists( 'expense_date', $input ) ) {
			$out['expense_date'] = $this->normalize_date( isset( $input['expense_date'] ) ? $input['expense_date'] : '', current_time( 'mysql' ) );
		}

		// Defaults on create.
		if ( $is_create ) {
			$out += array(
				'category'       => '',
				'vendor'         => '',
				'payment_method' => '',
				'description'    => '',
				'note'           => '',
			);
		}

		return $out;
	}

	/**
	 * Normalise a date to Y-m-d H:i:s, or return $fallback when blank/invalid.
	 *
	 * @param mixed  $value    Raw date.
	 * @param string $fallback Value to use when empty/unparseable.
	 * @return string
	 */
	protected function normalize_date( $value, $fallback ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return $fallback;
		}
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return $fallback;
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Shape an expense row for output.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return array<string,mixed>
	 */
	public function format_expense( array $row ) {
		return array(
			'id'             => (int) $row['id'],
			'category'       => (string) $row['category'],
			'amount'         => (float) $row['amount'],
			'description'    => (string) $row['description'],
			'vendor'         => (string) $row['vendor'],
			'payment_method' => (string) $row['payment_method'],
			'expense_date'   => $row['expense_date'],
			'note'           => (string) $row['note'],
			'created_at'     => $row['created_at'],
			'updated_at'     => $row['updated_at'],
		);
	}
}
