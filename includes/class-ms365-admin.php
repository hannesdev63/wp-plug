<?php
/**
 * Admin settings & dashboard for WP Microsoft 365 Graph.
 *
 * Registers the admin menu, settings fields, and handles the disconnect action.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Admin {

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wp_ms365_disconnect', array( $this, 'handle_disconnect' ) );
	}

	// ------------------------------------------------------------------
	// Menu
	// ------------------------------------------------------------------

	/**
	 * Register top-level admin menu item.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Microsoft 365', 'wp-ms365-graph' ),
			__( 'Microsoft 365', 'wp-ms365-graph' ),
			'manage_options',
			'wp-ms365-graph',
			array( $this, 'render_page' ),
			'dashicons-microsoft',
			80
		);

		add_submenu_page(
			'wp-ms365-graph',
			__( 'Settings', 'wp-ms365-graph' ),
			__( 'Settings', 'wp-ms365-graph' ),
			'manage_options',
			'wp-ms365-graph',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			'wp-ms365-graph',
			__( 'Dashboard', 'wp-ms365-graph' ),
			__( 'Dashboard', 'wp-ms365-graph' ),
			'manage_options',
			'wp-ms365-dashboard',
			array( $this, 'render_dashboard' )
		);
	}

	// ------------------------------------------------------------------
	// Settings API
	// ------------------------------------------------------------------

	/**
	 * Register settings, sections, and fields via the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'wp_ms365_settings_group',
			'wp_ms365_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'wp_ms365_azure_app',
			__( 'Azure App Registration', 'wp-ms365-graph' ),
			array( $this, 'section_azure_intro' ),
			'wp-ms365-graph'
		);

		$fields = array(
			'tenant_id'     => __( 'Directory (Tenant) ID', 'wp-ms365-graph' ),
			'client_id'     => __( 'Application (Client) ID', 'wp-ms365-graph' ),
			'client_secret' => __( 'Client Secret', 'wp-ms365-graph' ),
			'specific_user' => __( 'Specific User (UPN or ID)', 'wp-ms365-graph' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'wp_ms365_' . $key,
				$label,
				array( $this, 'render_text_field' ),
				'wp-ms365-graph',
				'wp_ms365_azure_app',
				array( 'key' => $key, 'label' => $label )
			);
		}
	}

	/**
	 * Sanitize and validate settings input.
	 *
	 * @param  array $input Raw $_POST values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$clean = array();

		$clean['tenant_id']     = isset( $input['tenant_id'] )     ? sanitize_text_field( $input['tenant_id'] )     : '';
		$clean['client_id']     = isset( $input['client_id'] )     ? sanitize_text_field( $input['client_id'] )     : '';
		$clean['client_secret'] = isset( $input['client_secret'] ) ? sanitize_text_field( $input['client_secret'] ) : '';
		$clean['specific_user'] = isset( $input['specific_user'] ) ? sanitize_text_field( $input['specific_user'] ) : '';

		// Basic UUID format validation for tenant/client IDs.
		$uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
		if ( ! empty( $clean['tenant_id'] ) && ! preg_match( $uuid_pattern, $clean['tenant_id'] ) ) {
			add_settings_error(
				'wp_ms365_settings',
				'invalid_tenant_id',
				__( 'Tenant ID does not look like a valid GUID.', 'wp-ms365-graph' )
			);
		}

		if ( ! empty( $clean['client_id'] ) && ! preg_match( $uuid_pattern, $clean['client_id'] ) ) {
			add_settings_error(
				'wp_ms365_settings',
				'invalid_client_id',
				__( 'Client ID does not look like a valid GUID.', 'wp-ms365-graph' )
			);
		}

		return $clean;
	}

	// ------------------------------------------------------------------
	// Render callbacks
	// ------------------------------------------------------------------

	/**
	 * Render the main settings / connect page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		include WP_MS365_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the data dashboard page.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		include WP_MS365_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Section description for Azure app registration.
	 */
	public function section_azure_intro() {
		echo '<p>'
			. esc_html__( 'Enter your Azure Active Directory application credentials. You can create an app registration at ', 'wp-ms365-graph' )
			. '<a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'portal.azure.com', 'wp-ms365-graph' )
			. '</a>.'
			. '</p>';
	}

	/**
	 * Render a single text/password field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$settings = WP_MS365_Auth::get_settings();
		$key      = $args['key'];
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$type     = ( 'client_secret' === $key ) ? 'password' : 'text';

		printf(
			'<input type="%s" id="wp_ms365_%s" name="wp_ms365_settings[%s]" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( $type ),
			esc_attr( $key ),
			esc_attr( $key ),
			esc_attr( $value )
		);

		if ( 'specific_user' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Optional. Use a Microsoft user principal name (for example user@contoso.com) or object ID. Leave empty to use the signed-in user.', 'wp-ms365-graph' )
				. '</p>';
		}
	}

	// ------------------------------------------------------------------
	// Assets
	// ------------------------------------------------------------------

	/**
	 * Enqueue admin CSS on our plugin pages.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		$allowed_hooks = array( 'toplevel_page_wp-ms365-graph', 'microsoft-365_page_wp-ms365-dashboard' );
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-ms365-admin',
			WP_MS365_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			WP_MS365_VERSION
		);
	}

	// ------------------------------------------------------------------
	// Actions
	// ------------------------------------------------------------------

	/**
	 * Handle the disconnect (token revocation) form submission.
	 */
	public function handle_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-ms365-graph' ) );
		}

		check_admin_referer( 'wp_ms365_disconnect' );
		WP_MS365_Auth::disconnect();

		wp_redirect(
			add_query_arg(
				array( 'page' => 'wp-ms365-graph', 'disconnected' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
