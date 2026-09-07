<?php
/**
 * Sales REST controller.
 *
 * Routes (namespace saas-bms/v1):
 *   GET  /sales        list (paginated)
 *   GET  /sales/{id}   show (with line items)
 *   POST /sales        create (transactional; totals computed server-side)
 *
 * Every route requires business access; per-row ownership is enforced in the
 * service. The client sends only product ids + quantities for a sale — prices
 * and totals are never trusted from the request.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles sales routes.
 */
class SBMS_Sale_API {

	/**
	 * @return SBMS_Sale_Service
	 */
	protected function service() {
		return new SBMS_Sale_Service();
	}

	/**
	 * Register sales routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/sales',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => $this->list_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => $this->create_args(),
				),
			)
		);

		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/sales/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Handlers
	 * --------------------------------------------------------------------- */

	/**
	 * GET /sales
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$result = $this->service()->list_sales(
			array(
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
			)
		);

		return $this->respond( $result, 200 );
	}

	/**
	 * GET /sales/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$result = $this->service()->get_sale( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 200 );
	}

	/**
	 * POST /sales
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$result = $this->service()->create_sale(
			array(
				'items'          => $request->get_param( 'items' ),
				'customer_name'  => $request->get_param( 'customer_name' ),
				'payment_method' => $request->get_param( 'payment_method' ),
				'note'           => $request->get_param( 'note' ),
				'sale_date'      => $request->get_param( 'sale_date' ),
				'tax'            => $request->get_param( 'tax' ),
				'discount'       => $request->get_param( 'discount' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 201 );
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	/**
	 * @param mixed $data   Payload.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	protected function respond( $data, $status ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/* --------------------------------------------------------------------- *
	 * Argument schemas
	 * --------------------------------------------------------------------- */

	/**
	 * @return array<string,mixed>
	 */
	protected function id_arg() {
		return array(
			'required'          => true,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ) {
				return is_numeric( $value ) && (int) $value > 0;
			},
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	protected function list_args() {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => SBMS_Sale_Service::DEFAULT_PER_PAGE,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	protected function create_args() {
		return array(
			'items'          => array(
				'type'              => 'array',
				'required'          => true,
				'validate_callback' => array( $this, 'validate_items' ),
			),
			'customer_name'  => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'payment_method' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'note'           => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'sale_date'      => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'tax'            => array(
				'type'              => 'number',
				'required'          => false,
				'validate_callback' => array( $this, 'validate_non_negative_number' ),
			),
			'discount'       => array(
				'type'              => 'number',
				'required'          => false,
				'validate_callback' => array( $this, 'validate_non_negative_number' ),
			),
		);
	}

	/**
	 * Shallow validation that items is a non-empty array of objects. Deep
	 * validation (product existence/ownership/stock) happens in the service.
	 *
	 * @param mixed $value Items.
	 * @return bool
	 */
	public function validate_items( $value ) {
		return is_array( $value ) && ! empty( $value );
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function validate_non_negative_number( $value ) {
		return is_numeric( $value ) && (float) $value >= 0;
	}
}
