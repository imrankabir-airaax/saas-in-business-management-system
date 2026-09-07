<?php
/**
 * REST API controller.
 *
 * Registers the plugin's REST namespace and its foundation routes. Module
 * routes (products, sales, expenses, AI) will be registered from here or from
 * dedicated controllers hooked into the same namespace as the plugin grows.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST routes for the plugin.
 */
class SBMS_REST_API {

	/**
	 * Register every route on the plugin namespace.
	 *
	 * Called on the `rest_api_init` action.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			SBMS_REST_NAMESPACE,
			'/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( 'SBMS_Permission_Middleware', 'require_manage' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Return the current status of the plugin's foundation.
	 *
	 * Reports live values (versions, table state, AI configuration) so the
	 * endpoint doubles as a health check for the install.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response
	 */
	public function get_status( WP_REST_Request $request ) {
		unset( $request );

		$table_keys = array( 'business_profiles', 'products', 'inventory_movements', 'sales', 'sale_items', 'expenses', 'ai_reports' );
		$tables     = array();

		if ( class_exists( 'SBMS_Database' ) ) {
			foreach ( $table_keys as $key ) {
				$tables[ $key ] = SBMS_Database::table_exists( $key );
			}
		}

		$settings = get_option( 'sbms_settings', array() );
		$ai_ready = is_array( $settings )
			&& ! empty( $settings['enable_ai'] )
			&& ! empty( $settings['gemini_api_key'] );

		$data = array(
			'plugin'     => 'SaaS in Business Management System',
			'version'    => SBMS_VERSION,
			'db_version' => get_option( SBMS_Database::DB_VERSION_OPTION, null ),
			'namespace'  => SBMS_REST_NAMESPACE,
			'tables'     => $tables,
			'ai_ready'   => (bool) $ai_ready,
			'timestamp'  => current_time( 'mysql' ),
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}
}
