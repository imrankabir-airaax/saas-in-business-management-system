<?php
/**
 * Dashboard REST controller.
 *
 * GET /wp-json/saas-bms/v1/dashboard — real, per-user metrics assembled by the
 * dashboard service. Read-only; requires business access.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the dashboard route.
 */
class SBMS_Dashboard_API {

	/**
	 * @return SBMS_Dashboard_Service
	 */
	protected function service() {
		return new SBMS_Dashboard_Service();
	}

	/**
	 * Register the dashboard route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/dashboard',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_access' ),
					'args'                => array(
						'recent' => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 5,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * GET /dashboard
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$data = $this->service()->overview( (int) $request->get_param( 'recent' ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}
}
