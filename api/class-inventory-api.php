<?php
/**
 * Inventory REST controller.
 *
 * Routes (namespace saas-bms/v1):
 *   GET    /inventory        list (search + filters + paging)
 *   POST   /inventory        create
 *   GET    /inventory/{id}   show
 *   PUT    /inventory/{id}   update
 *   DELETE /inventory/{id}   delete
 *
 * Every route requires business access (require_access); per-row ownership is
 * enforced in the service. Args carry sanitize/validate callbacks; handlers are
 * thin and return a consistent { success, data } envelope (errors are WP_Error
 * with the right HTTP status).
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles inventory routes.
 */
class SBMS_Inventory_API {

	/**
	 * Inventory service.
	 *
	 * @return SBMS_Inventory_Service
	 */
	protected function service() {
		return new SBMS_Inventory_Service();
	}

	/**
	 * Register inventory routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/inventory',
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
					'args'                => $this->write_args( true ),
				),
			)
		);

		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/inventory/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => array( 'id' => $this->id_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => array_merge( array( 'id' => $this->id_arg() ), $this->write_args( false ) ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'destroy' ),
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
	 * GET /inventory
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$result = $this->service()->list_products(
			array(
				'search'    => $request->get_param( 'search' ),
				'category'  => $request->get_param( 'category' ),
				'status'    => $request->get_param( 'status' ),
				'low_stock' => $request->get_param( 'low_stock' ),
				'page'      => $request->get_param( 'page' ),
				'per_page'  => $request->get_param( 'per_page' ),
				'orderby'   => $request->get_param( 'orderby' ),
				'order'     => $request->get_param( 'order' ),
			)
		);

		return $this->respond( $result, 200 );
	}

	/**
	 * GET /inventory/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$result = $this->service()->get_product( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 200 );
	}

	/**
	 * POST /inventory
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$result = $this->service()->create_product( $this->collect_fields( $request ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 201 );
	}

	/**
	 * PUT /inventory/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update( WP_REST_Request $request ) {
		$result = $this->service()->update_product(
			(int) $request->get_param( 'id' ),
			$this->collect_fields( $request )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 200 );
	}

	/**
	 * DELETE /inventory/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy( WP_REST_Request $request ) {
		$result = $this->service()->delete_product( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 200 );
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Wrap data in the standard success envelope.
	 *
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

	/**
	 * Collect only provided writable product fields from the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	protected function collect_fields( WP_REST_Request $request ) {
		$fields = array();

		foreach ( array( 'name', 'sku', 'category', 'description', 'price', 'cost_price', 'stock_quantity', 'low_stock_threshold', 'status' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$fields[ $key ] = $value;
			}
		}

		return $fields;
	}

	/* --------------------------------------------------------------------- *
	 * Argument schemas
	 * --------------------------------------------------------------------- */

	/**
	 * The {id} path argument.
	 *
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
	 * Arguments for GET /inventory.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function list_args() {
		return array(
			'search'    => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category'  => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'    => array(
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'active', 'inactive' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'low_stock' => array(
				'type'              => 'boolean',
				'required'          => false,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'page'      => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'  => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => SBMS_Inventory_Service::DEFAULT_PER_PAGE,
				'sanitize_callback' => 'absint',
			),
			'orderby'   => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'name',
				'enum'              => array( 'name', 'sku', 'category', 'price', 'cost_price', 'stock_quantity', 'low_stock_threshold', 'status', 'created_at', 'updated_at', 'id' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order'     => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'ASC',
				'enum'              => array( 'ASC', 'DESC', 'asc', 'desc' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Arguments for create/update.
	 *
	 * @param bool $is_create Whether name is required.
	 * @return array<string,array<string,mixed>>
	 */
	protected function write_args( $is_create ) {
		return array(
			'name'                => array(
				'type'              => 'string',
				'required'          => (bool) $is_create,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'sku'                 => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category'            => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'         => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'status'              => array(
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( 'active', 'inactive' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'price'               => array(
				'type'              => 'number',
				'required'          => false,
				'validate_callback' => array( $this, 'validate_non_negative_number' ),
			),
			'cost_price'          => array(
				'type'              => 'number',
				'required'          => false,
				'validate_callback' => array( $this, 'validate_non_negative_number' ),
			),
			'stock_quantity'      => array(
				'type'              => 'integer',
				'required'          => false,
				'validate_callback' => array( $this, 'validate_integer' ),
			),
			'low_stock_threshold' => array(
				'type'              => 'integer',
				'required'          => false,
				'validate_callback' => array( $this, 'validate_integer' ),
			),
		);
	}

	/**
	 * Validate a non-negative numeric value.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function validate_non_negative_number( $value ) {
		return is_numeric( $value ) && (float) $value >= 0;
	}

	/**
	 * Validate an integer-ish value (sign handled/clamped by the service).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function validate_integer( $value ) {
		return is_numeric( $value ) && (float) $value === floor( (float) $value );
	}
}
