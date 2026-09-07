<?php
/**
 * Admin-area controller.
 *
 * Registers the SaaS Business Management menu + submenus, renders each screen's
 * shell (data is loaded client-side from the REST API), enqueues per-screen
 * assets, and handles the settings form save server-side.
 *
 * Security note: the Gemini API key is stored in the sbms_settings option on the
 * server and is NEVER localized to JavaScript. The Settings screen posts to
 * admin-post.php (nonce + capability checked); the key value is never printed
 * back into the page.
 *
 * @package SBMS
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the plugin's WordPress admin menu, screens and assets.
 */
class SBMS_Admin {

	/**
	 * Plugin version, used for cache-busting enqueued assets.
	 *
	 * @var string
	 */
	private $version;

	const MENU_SLUG      = 'sbms-dashboard';
	const INVENTORY_SLUG = 'sbms-inventory';
	const SALES_SLUG     = 'sbms-sales';
	const EXPENSES_SLUG  = 'sbms-expenses';
	const AI_SLUG        = 'sbms-ai-insights';
	const PROFILE_SLUG   = 'sbms-profile';
	const SETTINGS_SLUG  = 'sbms-settings';

	const SETTINGS_OPTION = 'sbms_settings';

	/**
	 * Page hook suffixes returned when registering our screens.
	 *
	 * @var array<string,string>
	 */
	private $hooks = array();

	/**
	 * Constructor.
	 *
	 * @param string $version Plugin version.
	 */
	public function __construct( $version = SBMS_VERSION ) {
		$this->version = $version;
	}

	/**
	 * Register the admin menu and its subpages.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = 'manage_sbms';

		$this->hooks['dashboard'] = add_menu_page(
			__( 'SaaS Business Management', 'saas-business-management' ),
			__( 'Business Mgmt', 'saas-business-management' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			25
		);

		$submenus = array(
			array( self::MENU_SLUG, __( 'Dashboard', 'saas-business-management' ), 'render_dashboard', 'dashboard' ),
			array( self::INVENTORY_SLUG, __( 'Inventory', 'saas-business-management' ), 'render_inventory', 'inventory' ),
			array( self::SALES_SLUG, __( 'Sales', 'saas-business-management' ), 'render_sales', 'sales' ),
			array( self::EXPENSES_SLUG, __( 'Expenses', 'saas-business-management' ), 'render_expenses', 'expenses' ),
			array( self::AI_SLUG, __( 'AI Insights', 'saas-business-management' ), 'render_ai', 'ai' ),
			array( self::PROFILE_SLUG, __( 'Profile', 'saas-business-management' ), 'render_profile', 'profile' ),
			array( self::SETTINGS_SLUG, __( 'Settings', 'saas-business-management' ), 'render_settings', 'settings' ),
		);

		foreach ( $submenus as $item ) {
			list( $slug, $label, $callback, $key ) = $item;
			$hook = add_submenu_page( self::MENU_SLUG, $label, $label, $cap, $slug, array( $this, $callback ) );
			if ( $hook ) {
				$this->hooks[ $key ] = $hook;
			}
		}
	}

	/* --------------------------------------------------------------------- *
	 * Screen renderers (each just loads a view; the cap is re-checked).
	 * --------------------------------------------------------------------- */

	/**
	 * @return void
	 */
	public function render_dashboard() {
		$this->render_view( 'dashboard.php' );
	}

	/**
	 * @return void
	 */
	public function render_inventory() {
		$this->render_view( 'inventory.php' );
	}

	/**
	 * @return void
	 */
	public function render_sales() {
		$this->render_view( 'sales.php' );
	}

	/**
	 * @return void
	 */
	public function render_expenses() {
		$this->render_view( 'expenses.php' );
	}

	/**
	 * @return void
	 */
	public function render_ai() {
		$this->render_view( 'ai-insights.php' );
	}

	/**
	 * @return void
	 */
	public function render_profile() {
		$this->render_view( 'profile.php' );
	}

	/**
	 * Settings screen (exposes a couple of view variables).
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_sbms' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'saas-business-management' ) );
		}

		$settings   = $this->get_settings();
		$has_key    = '' !== (string) $settings['gemini_api_key'];
		$saved      = isset( $_GET['sbms_settings'] ) && 'saved' === $_GET['sbms_settings']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view       = SBMS_ADMIN_DIR . 'views/settings.php';

		if ( is_readable( $view ) ) {
			require $view;
		}
	}

	/**
	 * Shared view loader with capability check.
	 *
	 * @param string $file View filename under admin/views/.
	 * @return void
	 */
	private function render_view( $file ) {
		if ( ! current_user_can( 'manage_sbms' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'saas-business-management' ) );
		}

		$view = SBMS_ADMIN_DIR . 'views/' . $file;

		if ( is_readable( $view ) ) {
			require $view;
		}
	}

	/* --------------------------------------------------------------------- *
	 * Assets
	 * --------------------------------------------------------------------- */

	/**
	 * Enqueue admin styles and scripts, only on the plugin's own screens.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->hooks, true ) ) {
			return;
		}

		// Shared design system + legacy base styles on every plugin screen.
		// Shared design system for every plugin screen.
		wp_enqueue_style( 'sbms-app', SBMS_ADMIN_URL . 'css/app.css', array(), $this->version );

		$is = function ( $key ) use ( $hook_suffix ) {
			return isset( $this->hooks[ $key ] ) && $hook_suffix === $this->hooks[ $key ];
		};

		if ( $is( 'dashboard' ) ) {
			$this->enqueue_page(
				'dashboard',
				array( 'dashboardUrl' => rest_url( SBMS_REST_NAMESPACE . '/dashboard' ) )
			);
		}

		if ( $is( 'inventory' ) ) {
			wp_enqueue_style( 'sbms-inventory', SBMS_ADMIN_URL . 'css/inventory.css', array( 'sbms-app' ), $this->version );
			$this->enqueue_page(
				'inventory',
				array( 'restBase' => rest_url( SBMS_REST_NAMESPACE . '/inventory' ) ),
				'SBMS_Inventory'
			);
		}

		if ( $is( 'sales' ) ) {
			wp_enqueue_style( 'sbms-sales', SBMS_ADMIN_URL . 'css/sales.css', array( 'sbms-app' ), $this->version );
			$this->enqueue_page(
				'sales',
				array(
					'restBase'     => rest_url( SBMS_REST_NAMESPACE . '/sales' ),
					'inventoryUrl' => rest_url( SBMS_REST_NAMESPACE . '/inventory' ),
				),
				'SBMS_Sales'
			);
		}

		if ( $is( 'expenses' ) ) {
			wp_enqueue_style( 'sbms-expenses', SBMS_ADMIN_URL . 'css/expenses.css', array( 'sbms-app' ), $this->version );
			$this->enqueue_page(
				'expenses',
				array( 'restBase' => rest_url( SBMS_REST_NAMESPACE . '/expenses' ) ),
				'SBMS_Expenses'
			);
		}

		if ( $is( 'profile' ) ) {
			$this->enqueue_page(
				'profile',
				array( 'restBase' => rest_url( SBMS_REST_NAMESPACE . '/profile' ) ),
				'SBMS_Profile'
			);
		}

		if ( $is( 'ai' ) ) {
			wp_enqueue_script( 'sbms-ai', SBMS_ADMIN_URL . 'js/ai.js', array(), $this->version, true );

			wp_localize_script(
				'sbms-ai',
				'SBMS_AI',
				array(
					'analyzeUrl'        => esc_url_raw( rest_url( SBMS_REST_NAMESPACE . '/ai/analyze' ) ),
					'recommendationUrl' => esc_url_raw( rest_url( SBMS_REST_NAMESPACE . '/ai/recommendation' ) ),
					'reportsUrl'        => esc_url_raw( rest_url( SBMS_REST_NAMESPACE . '/ai/reports' ) ),
					'restNonce'         => wp_create_nonce( 'wp_rest' ),
					// A boolean only — never the key itself.
					'configured'        => $this->is_ai_configured(),
				)
			);
		}

		// Settings needs no page script beyond the shared styles.
	}

	/**
	 * Whether a Gemini key is configured. Returns a boolean only; the key value
	 * is never exposed.
	 *
	 * @return bool
	 */
	private function is_ai_configured() {
		$settings = $this->get_settings();

		return '' !== (string) $settings['gemini_api_key'] && ! empty( $settings['enable_ai'] );
	}

	/**
	 * Enqueue a page script and localize its config (nonce + currency + urls).
	 *
	 * The localized object never contains secrets — only public REST URLs, the
	 * cookie REST nonce, and the display currency.
	 *
	 * @param string              $slug   Asset base name (js/<slug>.js).
	 * @param array<string,mixed> $extra  Extra config (URLs).
	 * @param string              $object JS global name.
	 * @return void
	 */
	private function enqueue_page( $slug, array $extra = array(), $object = 'SBMS_Dashboard' ) {
		$handle = 'sbms-' . $slug;

		wp_enqueue_script( $handle, SBMS_ADMIN_URL . 'js/' . $slug . '.js', array(), $this->version, true );

		$config = array_merge(
			array(
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'currency'  => $this->currency_symbol(),
			),
			array_map( 'esc_url_raw', $extra )
		);

		wp_localize_script( $handle, $object, $config );
	}

	/* --------------------------------------------------------------------- *
	 * Settings
	 * --------------------------------------------------------------------- */

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	private function get_settings() {
		$defaults = array(
			'currency_symbol' => '$',
			'enable_ai'       => true,
			'gemini_api_key'  => '',
			'gemini_model'    => 'gemini-3.6-flash',
		);

		$saved = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( $defaults, $saved );
	}

	/**
	 * Display currency symbol.
	 *
	 * @return string
	 */
	private function currency_symbol() {
		$settings = $this->get_settings();

		return '' !== (string) $settings['currency_symbol'] ? (string) $settings['currency_symbol'] : '$';
	}

	/**
	 * Handle the Settings form POST (admin-post.php?action=sbms_save_settings).
	 *
	 * Verifies nonce + capability, sanitizes input, and persists the settings.
	 * The Gemini key is only overwritten when a new one is provided (or removed
	 * on request), and it is never echoed back to the browser.
	 *
	 * @return void
	 */
	public function handle_settings_save() {
		if ( ! current_user_can( 'manage_sbms' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to do this.', 'saas-business-management' ) );
		}

		check_admin_referer( 'sbms_save_settings' );

		$current = $this->get_settings();

		$currency = isset( $_POST['currency_symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['currency_symbol'] ) ) : '$';
		$model    = SBMS_Gemini_Client::normalize_model( isset( $_POST['gemini_model'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_model'] ) ) : '' );
		$enable   = ! empty( $_POST['enable_ai'] );

		// Key handling: blank field = keep existing; "remove" checkbox = clear.
		$key = $current['gemini_api_key'];
		if ( ! empty( $_POST['remove_gemini_api_key'] ) ) {
			$key = '';
		} elseif ( isset( $_POST['gemini_api_key'] ) && '' !== trim( (string) wp_unslash( $_POST['gemini_api_key'] ) ) ) {
			$key = sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) );
		}

		update_option(
			self::SETTINGS_OPTION,
			array(
				'currency_symbol' => '' !== $currency ? $currency : '$',
				'enable_ai'       => (bool) $enable,
				'gemini_api_key'  => $key,
				'gemini_model'    => '' !== $model ? $model : 'gemini-3.6-flash',
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => self::SETTINGS_SLUG,
					'sbms_settings' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
