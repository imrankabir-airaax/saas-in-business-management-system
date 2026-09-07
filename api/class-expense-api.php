<?php
/**
 * Expenses REST controller.
 *
 * Routes (namespace saas-bms/v1):
 *   GET    /expenses        list (search + filters + paging)
 *   POST   /expenses        create
 *   GET    /expenses/{id}   show
 *   PUT    /expenses/{id}   update
 *   DELETE /expenses/{id}   delete
 *
 * Every route requires business access; per-row ownership is enforced in the
 * service. Consistent { success, data } envelope; errors are WP_Error.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles expense routes.
 */
class SBMS_Expense_API {

	/**
	 * @return SBMS_Expense_Service
	 */
	protected function service() {
		return new SBMS_Expense_Service();
	}

	/**
	 * Register expense routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/expenses',
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
			'/expenses/(?P<id>\d+)',
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
	 * GET /expenses
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$result = $this->service()->list_expenses(
			array(
				'search'   => $request->get_param( 'search' ),
				'category' => $request->get_param( 'category' ),
				'from'     => $request->get_param( 'from' ),
				'to'       => $request->get_param( 'to' ),
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
				'orderby'  => $request->get_param( 'orderby' ),
				'order'    => $request->get_param( 'order' ),
			)
		);

		return $this->respond( $result, 200 );
	}

	/**
	 * GET /expenses/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$result = $this->service()->get_expense( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 200 );
	}

	/**
	 * POST /expenses
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$result = $this->service()->create_expense( $this->collect_fields( $request ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 201 );
	}

	/**
	 * PUT /expenses/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update( WP_REST_Request $request ) {
		$result = $this->service()->update_expense(
			(int) $request->get_param( 'id' ),
			$this->collect_fields( $request )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 200 );
	}

	/**
	 * DELETE /expenses/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy( WP_REST_Request $request ) {
		$result = $this->service()->delete_expense( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 200 );
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

	/**
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	protected function collect_fields( WP_REST_Request $request ) {
		$fields = array();

		foreach ( array( 'category', 'amount', 'description', 'vendor', 'payment_method', 'expense_date', 'note' ) as $key ) {
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
			'search'   => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'from'     => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'to'       => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'page'     => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => SBMS_Expense_Service::DEFAULT_PER_PAGE,
				'sanitize_callback' => 'absint',
			),
			'orderby'  => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'expense_date',
				'enum'              => array( 'category', 'amount', 'vendor', 'expense_date', 'created_at', 'id' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order'    => array(
				'type'              => 'string',
				'required'          => false,
				'default'           => 'DESC',
				'enum'              => array( 'ASC', 'DESC', 'asc', 'desc' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * @param bool $is_create Whether amount is required.
	 * @return array<string,array<string,mixed>>
	 */
	protected function write_args( $is_create ) {
		return array(
			'amount'         => array(
				'type'              => 'number',
				'required'          => (bool) $is_create,
				'validate_callback' => array( $this, 'validate_non_negative_number' ),
			),
			'category'       => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'    => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'vendor'         => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'payment_method' => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'expense_date'   => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'note'           => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function validate_non_negative_number( $value ) {
		return is_numeric( $value ) && (float) $value >= 0;
	}
}
