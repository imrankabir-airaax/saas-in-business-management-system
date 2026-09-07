<?php
/**
 * AI REST controller.
 *
 * Routes (namespace saas-bms/v1):
 *   POST /ai/analyze         run an analysis (type + optional question)
 *   POST /ai/recommendation  recommendations + risks + actions
 *   GET  /ai/reports         list this user's stored reports
 *
 * All routes require business access; reports are scoped to the current user in
 * the service. Responses never contain the API key, and provider/internal
 * errors are already reduced to safe messages before they reach here.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles AI routes.
 */
class SBMS_AI_API {

	/**
	 * @return SBMS_AI_Service
	 */
	protected function service() {
		return new SBMS_AI_Service();
	}

	/**
	 * Register AI routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/ai/analyze',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'analyze' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => array(
						'type'     => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'business',
							'enum'              => array( 'business', 'sales', 'expense', 'inventory' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'question' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/ai/recommendation',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'recommendation' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => array(
						'question' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/ai/reports',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'reports' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => array(
						'type'  => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit' => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Handlers
	 * --------------------------------------------------------------------- */

	/**
	 * POST /ai/analyze
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function analyze( WP_REST_Request $request ) {
		$result = $this->service()->analyze(
			(string) $request->get_param( 'type' ),
			array( 'question' => $request->get_param( 'question' ) )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 201 );
	}

	/**
	 * POST /ai/recommendation
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function recommendation( WP_REST_Request $request ) {
		$result = $this->service()->recommendation(
			array( 'question' => $request->get_param( 'question' ) )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->respond( $result, 201 );
	}

	/**
	 * GET /ai/reports
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function reports( WP_REST_Request $request ) {
		$result = $this->service()->reports(
			array(
				'type'  => $request->get_param( 'type' ),
				'limit' => $request->get_param( 'limit' ),
			)
		);

		return $this->respond( $result, 200 );
	}

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
}
