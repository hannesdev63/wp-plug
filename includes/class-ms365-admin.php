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
		add_action( 'admin_post_wp_ms365_request_new_token', array( $this, 'handle_request_new_token' ) );
		add_action( 'admin_post_wp_ms365_disconnect', array( $this, 'handle_request_new_token' ) );
		add_action( 'wp_ajax_wp_ms365_sp_site_drives', array( $this, 'ajax_get_sharepoint_site_drives' ) );
	}

	// ------------------------------------------------------------------
	// Menu
	// ------------------------------------------------------------------

	/**
	 * Returns the plugin icon as a base64-encoded SVG data URI (for menu use).
	 *
	 * @return string
	 */
	public static function get_menu_icon_uri() {
		$svg_file = WP_MS365_PLUGIN_DIR . 'assets/images/icon.svg';
		if ( ! file_exists( $svg_file ) ) {
			return 'dashicons-microsoft';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$svg = file_get_contents( $svg_file );
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Returns the URL to the plugin icon SVG for use in <img> tags.
	 *
	 * @return string
	 */
	public static function get_icon_url() {
		return WP_MS365_PLUGIN_URL . 'assets/images/icon.svg';
	}

	/**
	 * Register top-level admin menu item.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Entra ID Connect', 'wp-ms365-graph' ),
			__( 'Entra ID Connect', 'wp-ms365-graph' ),
			'manage_options',
			'wp-ms365-graph',
			array( $this, 'render_main_page' ),
			self::get_menu_icon_uri(),
			80
		);

		add_submenu_page(
			'wp-ms365-graph',
			__( 'Dashboard', 'wp-ms365-graph' ),
			__( 'Dashboard', 'wp-ms365-graph' ),
			'manage_options',
			'wp-ms365-graph',
			array( $this, 'render_main_page' )
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
			'custom_css'    => __( 'Custom CSS (Calendar/OneDrive)', 'wp-ms365-graph' ),
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

		add_settings_section(
			'wp_ms365_wording',
			__( 'Shortcode Wording', 'wp-ms365-graph' ),
			array( $this, 'section_wording_intro' ),
			'wp-ms365-wording'
		);

		$wording_fields = array(
			'calendar_empty_text'     => __( 'Calendar: No items found', 'wp-ms365-graph' ),
			'calendar_header_date'    => __( 'Calendar: Header Date', 'wp-ms365-graph' ),
			'calendar_header_event'   => __( 'Calendar: Header Event', 'wp-ms365-graph' ),
			'calendar_header_duration'=> __( 'Calendar: Header Duration', 'wp-ms365-graph' ),
			'calendar_header_location'=> __( 'Calendar: Header Location', 'wp-ms365-graph' ),
			'files_empty_text'        => __( 'Files: No items found', 'wp-ms365-graph' ),
			'files_header_file'       => __( 'Files: Header File', 'wp-ms365-graph' ),
			'files_header_size'       => __( 'Files: Header Size', 'wp-ms365-graph' ),
			'files_header_modified'   => __( 'Files: Header Modified', 'wp-ms365-graph' ),
		);

		foreach ( $wording_fields as $key => $label ) {
			add_settings_field(
				'wp_ms365_' . $key,
				$label,
				array( $this, 'render_text_field' ),
				'wp-ms365-wording',
				'wp_ms365_wording',
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
		$current = WP_MS365_Auth::get_settings();
		$clean   = $current;

		if ( isset( $input['tenant_id'] ) ) {
			$clean['tenant_id'] = sanitize_text_field( $input['tenant_id'] );
		}

		if ( isset( $input['client_id'] ) ) {
			$clean['client_id'] = sanitize_text_field( $input['client_id'] );
		}

		if ( isset( $input['client_secret'] ) ) {
			$clean['client_secret'] = sanitize_text_field( $input['client_secret'] );
		}

		if ( isset( $input['specific_user'] ) ) {
			$clean['specific_user'] = sanitize_text_field( $input['specific_user'] );
		}

		if ( isset( $input['custom_css'] ) ) {
			$clean['custom_css'] = sanitize_textarea_field( $input['custom_css'] );
		}

		if ( isset( $input['calendar_empty_text'] ) ) {
			$clean['calendar_empty_text'] = sanitize_text_field( $input['calendar_empty_text'] );
		}

		if ( isset( $input['calendar_header_date'] ) ) {
			$clean['calendar_header_date'] = sanitize_text_field( $input['calendar_header_date'] );
		}

		if ( isset( $input['calendar_header_event'] ) ) {
			$clean['calendar_header_event'] = sanitize_text_field( $input['calendar_header_event'] );
		}

		if ( isset( $input['calendar_header_duration'] ) ) {
			$clean['calendar_header_duration'] = sanitize_text_field( $input['calendar_header_duration'] );
		}

		if ( isset( $input['calendar_header_location'] ) ) {
			$clean['calendar_header_location'] = sanitize_text_field( $input['calendar_header_location'] );
		}

		if ( isset( $input['files_empty_text'] ) ) {
			$clean['files_empty_text'] = sanitize_text_field( $input['files_empty_text'] );
		}

		if ( isset( $input['files_header_file'] ) ) {
			$clean['files_header_file'] = sanitize_text_field( $input['files_header_file'] );
		}

		if ( isset( $input['files_header_size'] ) ) {
			$clean['files_header_size'] = sanitize_text_field( $input['files_header_size'] );
		}

		if ( isset( $input['files_header_modified'] ) ) {
			$clean['files_header_modified'] = sanitize_text_field( $input['files_header_modified'] );
		}

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
	 * Render the main plugin page with tab navigation.
	 */
	public function render_main_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$tabs = array(
			'dashboard'  => __( 'Dashboard', 'wp-ms365-graph' ),
			'settings'   => __( 'Settings', 'wp-ms365-graph' ),
			'sp-explorer'=> __( 'SP Explorer', 'wp-ms365-graph' ),
			'wording'    => __( 'Wording', 'wp-ms365-graph' ),
			'diagnostics'=> __( 'Diagnostics', 'wp-ms365-graph' ),
		);

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		echo '<div class="wrap">';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab_key => $tab_label ) {
			$tab_url = add_query_arg(
				array(
					'page' => 'wp-ms365-graph',
					'tab'  => $tab_key,
				),
				admin_url( 'admin.php' )
			);
			$tab_class = ( $tab_key === $tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url( $tab_url ),
				esc_attr( $tab_class ),
				esc_html( $tab_label )
			);
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'settings':
				$this->render_page();
				break;
			case 'sp-explorer':
				$this->render_sharepoint_explorer();
				break;
			case 'wording':
				$this->render_wording_page();
				break;
			case 'diagnostics':
				$this->render_diagnostics();
				break;
			case 'dashboard':
			default:
				$this->render_dashboard();
				break;
		}

		echo '</div>';
	}

	/**
	 * Render the main settings / connect page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		$this->render_specific_user_required_notice();
		include WP_MS365_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the wording customization page.
	 */
	public function render_wording_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		include WP_MS365_PLUGIN_DIR . 'admin/views/wording.php';
	}

	/**
	 * Render the data dashboard page.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		$this->render_specific_user_required_notice();
		include WP_MS365_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the diagnostics page.
	 */
	public function render_diagnostics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		$this->render_specific_user_required_notice();
		include WP_MS365_PLUGIN_DIR . 'admin/views/diagnostics.php';
	}

	/**
	 * Render the SharePoint Explorer page.
	 */
	public function render_sharepoint_explorer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		include WP_MS365_PLUGIN_DIR . 'admin/views/sharepoint-explorer.php';
	}

	/**
	 * Show a warning when Specific User is missing in app-only mode.
	 *
	 * @return void
	 */
	private function render_specific_user_required_notice() {
		$settings        = WP_MS365_Auth::get_settings();
		$has_credentials = ! empty( $settings['tenant_id'] ) && ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] );

		if ( ! $has_credentials || ! empty( $settings['specific_user'] ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>'
			. esc_html__( 'Specific User is required for app-only mode. Set a UPN (user@domain.com) or object ID in Entra ID Connect settings to enable profile, calendar, and OneDrive queries.', 'wp-ms365-graph' )
			. '</p></div>';
	}

	/**
	 * Section description for Azure app registration.
	 */
	public function section_azure_intro() {
		echo '<p>'
			. esc_html__( 'Enter your Entra ID application registration credentials. You can create an app registration at ', 'wp-ms365-graph' )
			. '<a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'portal.azure.com', 'wp-ms365-graph' )
			. '</a>.'
			. '</p>';
	}

	/**
	 * Section description for wording customization.
	 */
	public function section_wording_intro() {
		echo '<p>'
			. esc_html__( 'Override shortcode table headings and empty-state text. Leave any field blank to use the built-in translated default.', 'wp-ms365-graph' )
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

		if ( 'custom_css' === $key ) {
			printf(
				'<textarea id="wp_ms365_%s" name="wp_ms365_settings[%s]" class="large-text code" rows="10" placeholder=".ms365-calendar { ... }&#10;.ms365-files { ... }">%s</textarea>',
				esc_attr( $key ),
				esc_attr( $key ),
				esc_textarea( $value )
			);
			echo '<p class="description">'
				. esc_html__( 'Optional CSS loaded on the frontend for shortcode markup. Useful selectors: .ms365-calendar, .ms365-calendar__item, .ms365-files, .ms365-files__item.', 'wp-ms365-graph' )
				. '</p>';
			return;
		}

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
				. esc_html__( 'Required for app-only mode. Use a Microsoft user principal name (for example user@contoso.com) or object ID. The app registration must have Microsoft Graph application permissions User.Read.All, Calendars.Read, and Files.Read.All (grant admin consent in Azure).', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( false !== strpos( $key, 'header_' ) || false !== strpos( $key, 'empty_text' ) ) {
			echo '<p class="description">'
				. esc_html__( 'Optional override. Leave blank to use the translated default text.', 'wp-ms365-graph' )
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
		$allowed_hooks = array(
			'toplevel_page_wp-ms365-graph',
		);
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
	 * Handle request for a fresh token by clearing cached token state.
	 */
	public function handle_request_new_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-ms365-graph' ) );
		}

		check_admin_referer( 'wp_ms365_request_new_token' );
		WP_MS365_Auth::disconnect();

		wp_redirect(
			add_query_arg(
				array(
					'page'            => 'wp-ms365-graph',
					'tab'             => 'settings',
					'token_requested' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX: Retrieve SharePoint document libraries for a site.
	 *
	 * @return void
	 */
	public function ajax_get_sharepoint_site_drives() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message'       => __( 'Insufficient permissions.', 'wp-ms365-graph' ),
					'access_denied' => true,
				),
				403
			);
		}

		check_ajax_referer( 'wp_ms365_sp_explorer', 'nonce' );

		$site_id = isset( $_POST['site_id'] ) ? sanitize_text_field( wp_unslash( $_POST['site_id'] ) ) : '';
		if ( '' === trim( $site_id ) ) {
			wp_send_json_error(
				array(
					'message'       => __( 'SharePoint site ID is required.', 'wp-ms365-graph' ),
					'access_denied' => false,
				),
				400
			);
		}

		$drives = WP_MS365_Graph::get_sharepoint_site_drives( $site_id );
		if ( is_wp_error( $drives ) ) {
			$error_data  = $drives->get_error_data();
			$status_code = ( is_array( $error_data ) && isset( $error_data['status'] ) ) ? (int) $error_data['status'] : 400;
			$message     = $drives->get_error_message();
			$message_lc  = strtolower( (string) $message );

			$is_access_denied = ( 403 === $status_code )
				|| ( false !== strpos( $message_lc, 'access is denied' ) )
				|| ( false !== strpos( $message_lc, 'insufficient privileges' ) )
				|| ( false !== strpos( $message_lc, 'requestdenied' ) );

			wp_send_json_error(
				array(
					'message'       => $message,
					'access_denied' => $is_access_denied,
				),
				max( 400, $status_code )
			);
		}

		$normalized = array();
		if ( ! empty( $drives['value'] ) && is_array( $drives['value'] ) ) {
			foreach ( $drives['value'] as $drive ) {
				$normalized[] = array(
					'id'        => isset( $drive['id'] ) ? (string) $drive['id'] : '',
					'name'      => isset( $drive['name'] ) ? (string) $drive['name'] : '',
					'driveType' => isset( $drive['driveType'] ) ? (string) $drive['driveType'] : '',
					'webUrl'    => isset( $drive['webUrl'] ) ? (string) $drive['webUrl'] : '',
				);
			}
		}

		wp_send_json_success(
			array(
				'drives' => $normalized,
			)
		);
	}
}
