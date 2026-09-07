<?php
/**
 * The core plugin class.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates the whole plugin: loads dependencies and wires up the
 * WordPress hooks for the admin area and the REST API.
 */
final class SBMS_Plugin {

	/**
	 * Single shared instance.
	 *
	 * @var SBMS_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Admin controller.
	 *
	 * @var SBMS_Admin|null
	 */
	protected $admin = null;

	/**
	 * REST API controllers registered on rest_api_init.
	 *
	 * @var array<int,object>
	 */
	protected $rest_api = array();

	/**
	 * Whether run() has already wired up the hooks.
	 *
	 * @var bool
	 */
	protected $booted = false;

	/**
	 * Retrieve the shared instance, creating it on first use.
	 *
	 * @return SBMS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use SBMS_Plugin::instance().
	 */
	private function __construct() {
		$this->version = defined( 'SBMS_VERSION' ) ? SBMS_VERSION : '0.0.0';
		$this->load_dependencies();
	}

	/**
	 * Load every class the plugin needs at runtime.
	 *
	 * The activation/deactivation and database classes are already loaded by
	 * the bootstrap file, so this only loads the runtime layers: base model,
	 * base service, middleware, REST API and admin.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$files = array(
			SBMS_MODELS_DIR . 'class-base-model.php',
			SBMS_MODELS_DIR . 'class-business-profile-model.php',
			SBMS_MODELS_DIR . 'class-product-model.php',
			SBMS_MODELS_DIR . 'class-inventory-movement-model.php',
			SBMS_MODELS_DIR . 'class-sale-model.php',
			SBMS_MODELS_DIR . 'class-sale-item-model.php',
			SBMS_MODELS_DIR . 'class-expense-model.php',
			SBMS_MODELS_DIR . 'class-ai-report-model.php',
			SBMS_MODELS_DIR . 'class-user-model.php',
			SBMS_SERVICES_DIR . 'class-base-service.php',
			SBMS_SERVICES_DIR . 'class-auth-service.php',
			SBMS_SERVICES_DIR . 'class-inventory-service.php',
			SBMS_SERVICES_DIR . 'class-sale-service.php',
			SBMS_SERVICES_DIR . 'class-expense-service.php',
			SBMS_SERVICES_DIR . 'class-dashboard-service.php',
			SBMS_SERVICES_DIR . 'class-gemini-client.php',
			SBMS_SERVICES_DIR . 'class-ai-service.php',
			SBMS_MIDDLEWARE_DIR . 'class-auth-middleware.php',
			SBMS_MIDDLEWARE_DIR . 'class-permission-middleware.php',
			SBMS_API_DIR . 'class-rest-api.php',
			SBMS_API_DIR . 'class-auth-api.php',
			SBMS_API_DIR . 'class-user-api.php',
			SBMS_API_DIR . 'class-inventory-api.php',
			SBMS_API_DIR . 'class-sale-api.php',
			SBMS_API_DIR . 'class-expense-api.php',
			SBMS_API_DIR . 'class-dashboard-api.php',
			SBMS_API_DIR . 'class-ai-api.php',
			SBMS_ADMIN_DIR . 'class-admin.php',
		);

		foreach ( $files as $file ) {
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Register all WordPress hooks. Idempotent.
	 *
	 * @return void
	 */
	public function run() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Internationalisation.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Apply any pending schema upgrade when an admin loads the dashboard.
		if ( class_exists( 'SBMS_Database' ) ) {
			add_action( 'admin_init', array( 'SBMS_Database', 'maybe_upgrade' ) );
		}

		// Heal stored settings (e.g. a Gemini model family Google retired).
		add_action( 'admin_init', array( $this, 'maybe_upgrade_settings' ) );

		$this->register_admin_hooks();
		$this->register_rest_hooks();
	}

	/**
	 * Load the plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'saas-business-management',
			false,
			dirname( SBMS_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Settings self-heal, run whenever an admin page loads.
	 *
	 * If the stored Gemini model belongs to a family Google has already
	 * shut down (1.5 / 2.0), it is rewritten to the current default so
	 * the AI feature keeps working after a plugin update. Valid model
	 * choices are never touched, and nothing is written once the stored
	 * value is healthy.
	 *
	 * @return void
	 */
	public function maybe_upgrade_settings() {
		if ( ! class_exists( 'SBMS_Gemini_Client' ) ) {
			return;
		}

		$settings = get_option( 'sbms_settings', array() );

		if ( ! is_array( $settings ) || ! isset( $settings['gemini_model'] ) ) {
			return;
		}

		if ( SBMS_Gemini_Client::is_dead_model( $settings['gemini_model'] ) ) {
			$settings['gemini_model'] = SBMS_Gemini_Client::DEFAULT_MODEL;
			update_option( 'sbms_settings', $settings );
		}
	}

	/**
	 * Wire up admin-area hooks.
	 *
	 * @return void
	 */
	private function register_admin_hooks() {
		if ( ! is_admin() || ! class_exists( 'SBMS_Admin' ) ) {
			return;
		}

		$this->admin = new SBMS_Admin( $this->version );

		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
		add_action( 'admin_post_sbms_save_settings', array( $this->admin, 'handle_settings_save' ) );
	}

	/**
	 * Wire up REST API hooks.
	 *
	 * @return void
	 */
	private function register_rest_hooks() {
		$controllers = array();

		if ( class_exists( 'SBMS_REST_API' ) ) {
			$controllers[] = new SBMS_REST_API();
		}
		if ( class_exists( 'SBMS_Auth_API' ) ) {
			$controllers[] = new SBMS_Auth_API();
		}
		if ( class_exists( 'SBMS_User_API' ) ) {
			$controllers[] = new SBMS_User_API();
		}
		if ( class_exists( 'SBMS_Inventory_API' ) ) {
			$controllers[] = new SBMS_Inventory_API();
		}
		if ( class_exists( 'SBMS_Sale_API' ) ) {
			$controllers[] = new SBMS_Sale_API();
		}
		if ( class_exists( 'SBMS_Expense_API' ) ) {
			$controllers[] = new SBMS_Expense_API();
		}
		if ( class_exists( 'SBMS_Dashboard_API' ) ) {
			$controllers[] = new SBMS_Dashboard_API();
		}
		if ( class_exists( 'SBMS_AI_API' ) ) {
			$controllers[] = new SBMS_AI_API();
		}

		foreach ( $controllers as $controller ) {
			add_action( 'rest_api_init', array( $controller, 'register_routes' ) );
		}

		$this->rest_api = $controllers;
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get the admin controller (may be null outside wp-admin).
	 *
	 * @return SBMS_Admin|null
	 */
	public function get_admin() {
		return $this->admin;
	}

	/**
	 * Get the registered REST API controllers.
	 *
	 * @return array<int,object>
	 */
	public function get_rest_api() {
		return $this->rest_api;
	}

	/**
	 * Prevent cloning of the singleton.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the singleton.
	 *
	 * @return void
	 */
	public function __wakeup() {}
}
