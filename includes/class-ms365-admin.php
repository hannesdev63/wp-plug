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
	const SETTINGS_EXPORT_FILENAME_PREFIX = 'wp-ms365-settings';
	const SETTINGS_EXPORT_FORMAT          = 'wp-ms365-encrypted-settings';
	const SETTINGS_EXPORT_VERSION         = 1;

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wp_ms365_request_new_token', array( $this, 'handle_request_new_token' ) );
		add_action( 'admin_post_wp_ms365_export_settings', array( $this, 'handle_export_settings' ) );
		add_action( 'admin_post_wp_ms365_import_settings', array( $this, 'handle_import_settings' ) );
		add_action( 'admin_post_wp_ms365_disconnect', array( $this, 'handle_request_new_token' ) );
		add_action( 'admin_post_wp_ms365_reset_shortcode_counts', array( $this, 'handle_reset_shortcode_counts' ) );
		add_action( 'wp_ajax_wp_ms365_sp_site_drives', array( $this, 'ajax_get_sharepoint_site_drives' ) );
		add_action( 'wp_ajax_wp_ms365_sso_test_config', array( $this, 'ajax_sso_test_config' ) );
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
			__( 'MS Graph Connect', 'wp-ms365-graph' ),
			__( 'MS Graph Connect', 'wp-ms365-graph' ),
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
			'wp_ms365_teams',
			__( 'Teams Settings', 'wp-ms365-graph' ),
			array( $this, 'section_teams_intro' ),
			'wp-ms365-graph'
		);

		$teams_fields = array(
			'teams_team_id' => __( 'Default Teams Team ID', 'wp-ms365-graph' ),
			'teams_channel_id' => __( 'Default Teams Channel ID', 'wp-ms365-graph' ),
			'teams_workflow_url' => __( 'Teams Workflow Endpoint URL', 'wp-ms365-graph' ),
			'teams_rate_limit_max' => __( 'Teams Form Rate Limit: Max Requests', 'wp-ms365-graph' ),
			'teams_rate_limit_window' => __( 'Teams Form Rate Limit: Window (seconds)', 'wp-ms365-graph' ),
			'teams_min_submit_seconds' => __( 'Teams Form: Minimum Submit Time (seconds)', 'wp-ms365-graph' ),
		);

		foreach ( $teams_fields as $key => $label ) {
			add_settings_field(
				'wp_ms365_' . $key,
				$label,
				array( $this, 'render_text_field' ),
				'wp-ms365-graph',
				'wp_ms365_teams',
				array( 'key' => $key, 'label' => $label )
			);
		}

		// ---- WordPress sign-in (SSO) section ----
		add_settings_section(
			'wp_ms365_sso',
			__( 'WordPress Sign-In (Microsoft Tenant)', 'wp-ms365-graph' ),
			array( $this, 'section_sso_intro' ),
			'wp-ms365-graph'
		);

		add_settings_field(
			'wp_ms365_sso_enabled',
			__( 'Enable Tenant Sign-In', 'wp-ms365-graph' ),
			array( $this, 'render_checkbox_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_enabled', 'label' => __( 'Show a "Sign in with Microsoft" button on wp-login.php and enable the shortcode.', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_force_redirect',
			__( 'Force Entra Sign-In on wp-login.php', 'wp-ms365-graph' ),
			array( $this, 'render_checkbox_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_force_redirect', 'label' => __( 'Skip the default WordPress login form and redirect directly to Microsoft Entra ID. Append ?ms365_local_login=1 to wp-login.php to bypass when needed.', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_auto_create',
			__( 'Auto-Create Users', 'wp-ms365-graph' ),
			array( $this, 'render_checkbox_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_auto_create', 'label' => __( 'Automatically create a WordPress account for new Microsoft users.', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_use_ms_avatar',
			__( 'Use Microsoft Profile Picture', 'wp-ms365-graph' ),
			array( $this, 'render_checkbox_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_use_ms_avatar', 'label' => __( 'Use the Microsoft Entra profile picture as the WordPress avatar for linked users.', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_default_role',
			__( 'Default Role for New Users', 'wp-ms365-graph' ),
			array( $this, 'render_select_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array(
				'key'     => 'sso_default_role',
				'options' => $this->get_wp_roles_for_select(),
			)
		);

		add_settings_field(
			'wp_ms365_sso_allowed_domains',
			__( 'Allowed Email Domains', 'wp-ms365-graph' ),
			array( $this, 'render_text_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_allowed_domains', 'label' => __( 'Allowed Email Domains', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_redirect_url',
			__( 'Post-Login Redirect URL', 'wp-ms365-graph' ),
			array( $this, 'render_text_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_redirect_url', 'label' => __( 'Post-Login Redirect URL', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_prompt',
			__( 'OAuth Prompt Behavior', 'wp-ms365-graph' ),
			array( $this, 'render_select_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array(
				'key'     => 'sso_prompt',
				'options' => array(
					''               => __( 'Default', 'wp-ms365-graph' ),
					'select_account' => 'select_account',
					'login'          => 'login',
					'consent'        => 'consent',
					'none'           => 'none',
				),
			)
		);

		add_settings_field(
			'wp_ms365_sso_domain_hint',
			__( 'Domain Hint', 'wp-ms365-graph' ),
			array( $this, 'render_text_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_domain_hint', 'label' => __( 'Optional Entra domain hint (for example: contoso.com or organizations).', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_login_hint',
			__( 'Login Hint', 'wp-ms365-graph' ),
			array( $this, 'render_text_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_login_hint', 'label' => __( 'Optional login hint (usually user email/UPN) to prefill the account.', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_extra_scopes',
			__( 'Additional OAuth Scopes', 'wp-ms365-graph' ),
			array( $this, 'render_text_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array( 'key' => 'sso_extra_scopes', 'label' => __( 'Optional space- or comma-separated delegated scopes to request in addition to openid email profile.', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_login_log_retention_days',
			__( 'Login Log Retention (days)', 'wp-ms365-graph' ),
			array( $this, 'render_number_field' ),
			'wp-ms365-graph',
			'wp_ms365_sso',
			array(
				'key'   => 'login_log_retention_days',
				'label' => __( 'Number of days to keep login log entries (1–365). Default: 30.', 'wp-ms365-graph' ),
				'min'   => 1,
				'max'   => 365,
				'step'  => 1,
			)
		);

		add_settings_section(
			'wp_ms365_wording_calendar_files',
			__( 'Calendar & Files Wording', 'wp-ms365-graph' ),
			array( $this, 'section_wording_calendar_files_intro' ),
			'wp-ms365-wording'
		);

		$wording_calendar_files_fields = array(
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

		foreach ( $wording_calendar_files_fields as $key => $label ) {
			add_settings_field(
				'wp_ms365_' . $key,
				$label,
				array( $this, 'render_text_field' ),
				'wp-ms365-wording',
				'wp_ms365_wording_calendar_files',
				array( 'key' => $key, 'label' => $label )
			);
		}

		add_settings_section(
			'wp_ms365_wording_teams',
			__( 'Teams Form Wording', 'wp-ms365-graph' ),
			array( $this, 'section_wording_teams_intro' ),
			'wp-ms365-wording'
		);

		$wording_teams_fields = array(
			'teams_form_placeholder'  => __( 'Teams Form: Placeholder', 'wp-ms365-graph' ),
			'teams_form_button_text'  => __( 'Teams Form: Button Text', 'wp-ms365-graph' ),
			'teams_form_label_name'   => __( 'Teams Form: Label Name', 'wp-ms365-graph' ),
			'teams_form_label_email'  => __( 'Teams Form: Label Email', 'wp-ms365-graph' ),
			'teams_form_label_message'=> __( 'Teams Form: Label Message', 'wp-ms365-graph' ),
			'teams_form_success'      => __( 'Teams Form: Success Message', 'wp-ms365-graph' ),
			'teams_form_error_invalid_nonce' => __( 'Teams Form: Error Invalid Nonce', 'wp-ms365-graph' ),
			'teams_form_error_missing_fields' => __( 'Teams Form: Error Missing Fields', 'wp-ms365-graph' ),
			'teams_form_error_invalid_email' => __( 'Teams Form: Error Invalid Email', 'wp-ms365-graph' ),
			'teams_form_error_invalid_form' => __( 'Teams Form: Error Invalid Form', 'wp-ms365-graph' ),
			'teams_form_error_submitted_too_fast' => __( 'Teams Form: Error Submitted Too Fast', 'wp-ms365-graph' ),
			'teams_form_error_rate_limited' => __( 'Teams Form: Error Rate Limited', 'wp-ms365-graph' ),
			'teams_form_error_invalid_endpoint' => __( 'Teams Form: Error Invalid Endpoint', 'wp-ms365-graph' ),
			'teams_form_error_post_fail' => __( 'Teams Form: Error Delivery Failed', 'wp-ms365-graph' ),
			'teams_form_error_unknown' => __( 'Teams Form: Error Unknown', 'wp-ms365-graph' ),
		);

		foreach ( $wording_teams_fields as $key => $label ) {
			add_settings_field(
				'wp_ms365_' . $key,
				$label,
				array( $this, 'render_text_field' ),
				'wp-ms365-wording',
				'wp_ms365_wording_teams',
				array( 'key' => $key, 'label' => $label )
			);
		}

		add_settings_section(
			'wp_ms365_wording_signin',
			__( 'Sign-In Wording', 'wp-ms365-graph' ),
			array( $this, 'section_wording_signin_intro' ),
			'wp-ms365-wording'
		);

		add_settings_field(
			'wp_ms365_sso_signin_button_text',
			__( 'Entra Sign-In Button Text', 'wp-ms365-graph' ),
			array( $this, 'render_text_field' ),
			'wp-ms365-wording',
			'wp_ms365_wording_signin',
			array( 'key' => 'sso_signin_button_text', 'label' => __( 'Entra Sign-In Button Text', 'wp-ms365-graph' ) )
		);

		add_settings_field(
			'wp_ms365_sso_signin_button_image',
			__( 'Entra Sign-In Button Image', 'wp-ms365-graph' ),
			array( $this, 'render_image_field' ),
			'wp-ms365-wording',
			'wp_ms365_wording_signin',
			array( 'key' => 'sso_signin_button_image', 'label' => __( 'Entra Sign-In Button Image', 'wp-ms365-graph' ) )
		);
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

		if ( isset( $input['teams_team_id'] ) ) {
			$clean['teams_team_id'] = sanitize_text_field( $input['teams_team_id'] );
		}

		if ( isset( $input['teams_channel_id'] ) ) {
			$clean['teams_channel_id'] = sanitize_text_field( $input['teams_channel_id'] );
		}

		if ( isset( $input['teams_workflow_url'] ) ) {
			$clean['teams_workflow_url'] = esc_url_raw( trim( (string) $input['teams_workflow_url'] ) );
		}

		if ( isset( $input['teams_webhook_url'] ) ) {
			$clean['teams_webhook_url'] = esc_url_raw( trim( (string) $input['teams_webhook_url'] ) );
		}

		if ( isset( $input['teams_rate_limit_max'] ) ) {
			$clean['teams_rate_limit_max'] = max( 1, min( 50, (int) $input['teams_rate_limit_max'] ) );
		}

		if ( isset( $input['teams_rate_limit_window'] ) ) {
			$clean['teams_rate_limit_window'] = max( 30, min( 86400, (int) $input['teams_rate_limit_window'] ) );
		}

		if ( isset( $input['teams_min_submit_seconds'] ) ) {
			$clean['teams_min_submit_seconds'] = max( 1, min( 120, (int) $input['teams_min_submit_seconds'] ) );
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

		if ( isset( $input['teams_form_placeholder'] ) ) {
			$clean['teams_form_placeholder'] = sanitize_text_field( $input['teams_form_placeholder'] );
		}

		if ( isset( $input['teams_form_button_text'] ) ) {
			$clean['teams_form_button_text'] = sanitize_text_field( $input['teams_form_button_text'] );
		}

		if ( isset( $input['teams_form_label_name'] ) ) {
			$clean['teams_form_label_name'] = sanitize_text_field( $input['teams_form_label_name'] );
		}

		if ( isset( $input['teams_form_label_email'] ) ) {
			$clean['teams_form_label_email'] = sanitize_text_field( $input['teams_form_label_email'] );
		}

		if ( isset( $input['teams_form_label_message'] ) ) {
			$clean['teams_form_label_message'] = sanitize_text_field( $input['teams_form_label_message'] );
		}

		if ( isset( $input['teams_form_success'] ) ) {
			$clean['teams_form_success'] = sanitize_text_field( $input['teams_form_success'] );
		}

		if ( isset( $input['teams_form_error_invalid_nonce'] ) ) {
			$clean['teams_form_error_invalid_nonce'] = sanitize_text_field( $input['teams_form_error_invalid_nonce'] );
		}

		if ( isset( $input['teams_form_error_missing_fields'] ) ) {
			$clean['teams_form_error_missing_fields'] = sanitize_text_field( $input['teams_form_error_missing_fields'] );
		}

		if ( isset( $input['teams_form_error_invalid_email'] ) ) {
			$clean['teams_form_error_invalid_email'] = sanitize_text_field( $input['teams_form_error_invalid_email'] );
		}

		if ( isset( $input['teams_form_error_invalid_form'] ) ) {
			$clean['teams_form_error_invalid_form'] = sanitize_text_field( $input['teams_form_error_invalid_form'] );
		}

		if ( isset( $input['teams_form_error_submitted_too_fast'] ) ) {
			$clean['teams_form_error_submitted_too_fast'] = sanitize_text_field( $input['teams_form_error_submitted_too_fast'] );
		}

		if ( isset( $input['teams_form_error_rate_limited'] ) ) {
			$clean['teams_form_error_rate_limited'] = sanitize_text_field( $input['teams_form_error_rate_limited'] );
		}

		if ( isset( $input['teams_form_error_invalid_endpoint'] ) ) {
			$clean['teams_form_error_invalid_endpoint'] = sanitize_text_field( $input['teams_form_error_invalid_endpoint'] );
		}

		if ( isset( $input['teams_form_error_post_fail'] ) ) {
			$clean['teams_form_error_post_fail'] = sanitize_text_field( $input['teams_form_error_post_fail'] );
		}

		if ( isset( $input['teams_form_error_unknown'] ) ) {
			$clean['teams_form_error_unknown'] = sanitize_text_field( $input['teams_form_error_unknown'] );
		}

		if ( isset( $input['sso_signin_button_text'] ) ) {
			$clean['sso_signin_button_text'] = sanitize_text_field( $input['sso_signin_button_text'] );
		}

		if ( isset( $input['sso_signin_button_image'] ) ) {
			$clean['sso_signin_button_image'] = esc_url_raw( trim( (string) $input['sso_signin_button_image'] ) );
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

		// ---- SSO / delegated sign-in settings ----
		$has_sso_payload =
			isset( $input['sso_enabled'] ) ||
			isset( $input['sso_force_redirect'] ) ||
			isset( $input['sso_auto_create'] ) ||
			isset( $input['sso_use_ms_avatar'] ) ||
			isset( $input['sso_default_role'] ) ||
			isset( $input['sso_allowed_domains'] ) ||
			isset( $input['sso_redirect_url'] ) ||
			isset( $input['sso_prompt'] ) ||
			isset( $input['sso_domain_hint'] ) ||
			isset( $input['sso_login_hint'] ) ||
			isset( $input['sso_extra_scopes'] ) ||
			isset( $input['login_log_retention_days'] );

		if ( $has_sso_payload ) {
			$clean['sso_enabled']       = ! empty( $input['sso_enabled'] ) ? 1 : 0;
			$clean['sso_force_redirect'] = ! empty( $input['sso_force_redirect'] ) ? 1 : 0;
			$clean['sso_auto_create']   = ! empty( $input['sso_auto_create'] ) ? 1 : 0;
			$clean['sso_use_ms_avatar'] = ! empty( $input['sso_use_ms_avatar'] ) ? 1 : 0;

			if ( isset( $input['sso_default_role'] ) ) {
				$allowed_roles             = array_keys( (array) wp_roles()->role_names );
				$submitted_role            = sanitize_key( (string) $input['sso_default_role'] );
				$clean['sso_default_role'] = in_array( $submitted_role, $allowed_roles, true ) ? $submitted_role : 'subscriber';
			}

			if ( isset( $input['sso_allowed_domains'] ) ) {
				// Sanitize comma-separated domain list: lower-case, strip spaces, strip protocol/path.
				$raw_domains = sanitize_text_field( (string) $input['sso_allowed_domains'] );
				$domains     = array_filter( array_map( function ( $d ) {
					$d = strtolower( trim( $d ) );
					// Strip any accidentally included scheme or path.
					$d = preg_replace( '#^https?://#i', '', $d );
					$d = rtrim( $d, '/' );
					return $d;
				}, explode( ',', $raw_domains ) ) );
				$clean['sso_allowed_domains'] = implode( ', ', $domains );
			}

			if ( isset( $input['sso_redirect_url'] ) ) {
				$clean['sso_redirect_url'] = esc_url_raw( trim( (string) $input['sso_redirect_url'] ) );
			}

			if ( isset( $input['sso_prompt'] ) ) {
				$allowed_prompts      = array( '', 'select_account', 'login', 'consent', 'none' );
				$submitted_prompt     = sanitize_key( (string) $input['sso_prompt'] );
				$clean['sso_prompt']  = in_array( $submitted_prompt, $allowed_prompts, true ) ? $submitted_prompt : '';
			}

			if ( isset( $input['sso_domain_hint'] ) ) {
				$clean['sso_domain_hint'] = strtolower( trim( sanitize_text_field( (string) $input['sso_domain_hint'] ) ) );
			}

			if ( isset( $input['sso_login_hint'] ) ) {
				$clean['sso_login_hint'] = trim( sanitize_text_field( (string) $input['sso_login_hint'] ) );
			}

			if ( isset( $input['sso_extra_scopes'] ) ) {
				$raw_scopes = trim( sanitize_text_field( (string) $input['sso_extra_scopes'] ) );
				$parts      = preg_split( '/[\s,]+/', $raw_scopes );
				$parts      = is_array( $parts ) ? $parts : array();
				$parts      = array_filter(
					array_map( 'trim', $parts ),
					function ( $scope ) {
						return (bool) preg_match( '/^[A-Za-z0-9\.\:\/\_\-]+$/', $scope );
					}
				);
				$clean['sso_extra_scopes'] = implode( ' ', array_unique( $parts ) );
			}

			if ( isset( $input['login_log_retention_days'] ) ) {
				$clean['login_log_retention_days'] = min( 365, max( 1, absint( $input['login_log_retention_days'] ) ) );
			}
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
			'dashboard'    => __( 'Dashboard', 'wp-ms365-graph' ),
			'access-stats' => __( 'Access Statistics', 'wp-ms365-graph' ),
			'settings'     => __( 'Settings', 'wp-ms365-graph' ),
			'documentation'=> __( 'Documentation', 'wp-ms365-graph' ),
			'sp-explorer'  => __( 'SP Explorer', 'wp-ms365-graph' ),
			'wording'      => __( 'Wording', 'wp-ms365-graph' ),
			'diagnostics'  => __( 'Diagnostics', 'wp-ms365-graph' ),
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
			case 'access-stats':
				$this->render_access_stats();
				break;
			case 'settings':
				$this->render_page();
				break;
			case 'documentation':
				$this->render_documentation();
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
	 * Render the unified access statistics page.
	 */
	public function render_access_stats() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		include WP_MS365_PLUGIN_DIR . 'admin/views/access-stats.php';
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
	 * Render the shortcode documentation page.
	 */
	public function render_documentation() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}
		$this->render_specific_user_required_notice();
		include WP_MS365_PLUGIN_DIR . 'admin/views/documentation.php';
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
			. esc_html__( 'Specific User is required for app-only mode. Set a UPN (user@domain.com) or object ID in MS Graph Connect settings to enable profile, calendar, and OneDrive queries.', 'wp-ms365-graph' )
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
	 * Section description for Teams settings.
	 */
	public function section_teams_intro() {
		echo '<p>'
			. esc_html__( 'Configure defaults and anti-spam controls for the Teams message form shortcode.', 'wp-ms365-graph' )
			. '</p>';
	}

	/**
	 * Section description for calendar/files wording customization.
	 */
	public function section_wording_calendar_files_intro() {
		echo '<p>'
			. esc_html__( 'Override table headings and empty-state text for calendar and file shortcodes. Leave any field blank to use translated defaults.', 'wp-ms365-graph' )
			. '</p>';
	}

	/**
	 * Section description for Teams form wording customization.
	 */
	public function section_wording_teams_intro() {
		echo '<p>'
			. esc_html__( 'Customize labels, button text, success, and error messages shown in the Teams message form shortcode.', 'wp-ms365-graph' )
			. '</p>';
	}

	/**
	 * Section description for sign-in wording customization.
	 */
	public function section_wording_signin_intro() {
		echo '<p>'
			. esc_html__( 'Customize the Microsoft Entra sign-in button wording. Leave blank to use the translated default label.', 'wp-ms365-graph' )
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
				'<textarea id="wp_ms365_%s" name="wp_ms365_settings[%s]" class="large-text code" rows="10" placeholder=".msgraph_calendar { ... }&#10;.msgraph_files { ... }">%s</textarea>',
				esc_attr( $key ),
				esc_attr( $key ),
				esc_textarea( $value )
			);
			echo '<p class="description">'
				. esc_html__( 'Optional CSS loaded on the frontend for shortcode markup. Useful selectors: .msgraph_calendar, .msgraph_calendar__item, .msgraph_files, .msgraph_files__item.', 'wp-ms365-graph' )
				. '</p>';
			return;
		}

		$type          = ( 'client_secret' === $key ) ? 'password' : 'text';
		$extra_class   = ( 'client_secret' === $key ) ? ' msgraph_password-input' : '';
		$extra_data    = '';

		if ( 'client_secret' === $key ) {
			$extra_data = sprintf(
				' data-toggle-label-show="%1$s" data-toggle-label-hide="%2$s"',
				esc_attr__( 'Show', 'wp-ms365-graph' ),
				esc_attr__( 'Hide', 'wp-ms365-graph' )
			);
		}

		printf(
			'<input type="%s" id="wp_ms365_%s" name="wp_ms365_settings[%s]" value="%s" class="regular-text%s" autocomplete="off"%s />',
			esc_attr( $type ),
			esc_attr( $key ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $extra_class ),
			$extra_data
		);

		if ( 'specific_user' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Required for app-only mode. Use a Microsoft user principal name (for example user@contoso.com) or object ID. The app registration must have Microsoft Graph application permissions User.Read.All, Calendars.Read, and Files.Read.All (grant admin consent in Azure).', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'teams_team_id' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Optional default Team ID used by [msgraph_teams_message_form] when team_id is not provided in the shortcode.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'teams_channel_id' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Optional default Channel ID used by [msgraph_teams_message_form] when channel_id is not provided in the shortcode.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'teams_workflow_url' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Workflow endpoint URL for Teams message delivery. This is the primary sender used by [msgraph_teams_message_form]. Existing Incoming Webhook URLs are still accepted for backward compatibility.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'teams_rate_limit_max' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Maximum accepted message submissions per user/IP in each rate-limit window.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'teams_rate_limit_window' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Rate-limit window in seconds for Teams message submissions.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'teams_min_submit_seconds' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Rejects form submissions faster than this threshold to reduce bot traffic.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( false !== strpos( $key, 'header_' ) || false !== strpos( $key, 'empty_text' ) ) {
			echo '<p class="description">'
				. esc_html__( 'Optional override. Leave blank to use the translated default text.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'sso_allowed_domains' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Comma-separated list of email domains allowed to sign in (e.g. contoso.com, fabrikam.org). Leave blank to allow any tenant domain.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'sso_redirect_url' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Optional. Internal URL to redirect to after successful sign-in. Overrides the per-request destination. Leave blank to redirect to the WP admin or the page that triggered login.', 'wp-ms365-graph' )
				. '</p>';
		} elseif ( 'sso_signin_button_text' === $key ) {
			echo '<p class="description">'
				. esc_html__( 'Optional override for the Entra sign-in button label used on wp-login.php and by [msgraph_login_button] when no label attribute is provided.', 'wp-ms365-graph' )
				. '</p>';
		}
	}

	/**
	 * Render a number input field.
	 *
	 * @param array $args Field arguments (key, label, min, max, step).
	 */
	public function render_number_field( $args ) {
		$settings = WP_MS365_Auth::get_settings();
		$key      = $args['key'];
		$value    = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : '';
		$min      = isset( $args['min'] ) ? (int) $args['min'] : '';
		$max      = isset( $args['max'] ) ? (int) $args['max'] : '';
		$step     = isset( $args['step'] ) ? (int) $args['step'] : 1;

		printf(
			'<input type="number" id="wp_ms365_%s" name="wp_ms365_settings[%s]" value="%s" class="small-text" min="%s" max="%s" step="%s" />',
			esc_attr( $key ),
			esc_attr( $key ),
			esc_attr( (string) $value ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max ),
			esc_attr( (string) $step )
		);

		if ( ! empty( $args['label'] ) ) {
			echo '<p class="description">' . esc_html( $args['label'] ) . '</p>';
		}
	}

	/**
	 * Render an image URL field with a WP media library picker button and live preview.
	 *
	 * @param array $args Field arguments (key, label).
	 */
	public function render_image_field( $args ) {
		$settings = WP_MS365_Auth::get_settings();
		$key      = $args['key'];
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$field_id = 'wp_ms365_' . $key;
		?>
		<div class="ms365-image-field" id="<?php echo esc_attr( $field_id . '_wrap' ); ?>">
			<input
				type="url"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="wp_ms365_settings[<?php echo esc_attr( $key ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text"
				autocomplete="off"
			/>
			<button
				type="button"
				class="button ms365-image-select"
				data-target="<?php echo esc_attr( $field_id ); ?>"
				data-preview="<?php echo esc_attr( $field_id . '_preview' ); ?>"
			><?php esc_html_e( 'Select Image', 'wp-ms365-graph' ); ?></button>
			<?php if ( ! empty( $value ) ) : ?>
			<button
				type="button"
				class="button ms365-image-remove"
				data-target="<?php echo esc_attr( $field_id ); ?>"
				data-preview="<?php echo esc_attr( $field_id . '_preview' ); ?>"
			><?php esc_html_e( 'Remove', 'wp-ms365-graph' ); ?></button>
			<?php endif; ?>
			<div class="ms365-image-preview" id="<?php echo esc_attr( $field_id . '_preview' ); ?>" style="margin-top:8px;">
				<?php if ( ! empty( $value ) ) : ?>
				<img src="<?php echo esc_url( $value ); ?>" alt="" style="max-width:200px;max-height:80px;display:block;" />
				<?php endif; ?>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Optional. Upload or select an image to replace the Microsoft logo in the sign-in button. The image is rendered as a small icon (20×20 px), so keep it square and no larger than 200×200 px. Leave blank to use the default Microsoft logo.', 'wp-ms365-graph' ); ?></p>
		<?php
	}

	/**
	 * Render a checkbox setting field.
	 *
	 * @param array $args Field arguments (key, label).
	 */
	public function render_checkbox_field( $args ) {
		$settings = WP_MS365_Auth::get_settings();
		$key      = $args['key'];
		$label    = isset( $args['label'] ) ? $args['label'] : '';
		$checked  = ! empty( $settings[ $key ] );
		printf(
			'<label><input type="checkbox" id="wp_ms365_%1$s" name="wp_ms365_settings[%1$s]" value="1"%2$s /> %3$s</label>',
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
	}

	/**
	 * Render a select/dropdown setting field.
	 *
	 * @param array $args Field arguments (key, options).
	 */
	public function render_select_field( $args ) {
		$settings = WP_MS365_Auth::get_settings();
		$key      = $args['key'];
		$options  = isset( $args['options'] ) ? $args['options'] : array();
		$current  = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

		printf( '<select id="wp_ms365_%s" name="wp_ms365_settings[%s]">', esc_attr( $key ), esc_attr( $key ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		if ( 'sso_default_role' === $key ) {
			echo '<p class="description">' . esc_html__( 'WordPress role assigned to auto-created users. Only applies when Auto-Create Users is enabled.', 'wp-ms365-graph' ) . '</p>';
		}
	}

	/**
	 * Section description for WordPress sign-in / SSO.
	 */
	public function section_sso_intro() {
		$callback_uri        = WP_MS365_Auth::get_sso_redirect_uri();
		$legacy_callback_uri = WP_MS365_Auth::get_sso_legacy_redirect_uri();
		echo '<p>'
			. esc_html__( 'Allow users to sign in to WordPress using their Microsoft tenant account via OAuth 2.0 Authorization Code + PKCE. The credentials configured in the Azure App Registration section above are reused.', 'wp-ms365-graph' )
			. '</p><p>'
			. esc_html__( 'Optional: enable forced Entra sign-in to redirect wp-login.php directly to Microsoft. For emergency local login access, append ?ms365_local_login=1 to wp-login.php.', 'wp-ms365-graph' )
			. '</p><p>'
			. '<strong>' . esc_html__( 'Required Azure app registration steps:', 'wp-ms365-graph' ) . '</strong>'
			. '</p><ol>'
			. '<li>' . esc_html__( 'Set application type to "Web".', 'wp-ms365-graph' ) . '</li>'
			. '<li>' . sprintf(
				/* translators: %s: callback redirect URI */
				esc_html__( 'Add the following Redirect URI (preferred): %s', 'wp-ms365-graph' ),
				'<code>' . esc_html( $callback_uri ) . '</code>'
			) . '</li>'
			. '<li>' . sprintf(
				/* translators: %s: legacy callback redirect URI */
				esc_html__( 'Legacy callback URL also supported: %s', 'wp-ms365-graph' ),
				'<code>' . esc_html( $legacy_callback_uri ) . '</code>'
			) . '</li>'
			. '<li>' . esc_html__( 'Under "API permissions" add the delegated permissions: openid, email, profile.', 'wp-ms365-graph' ) . '</li>'
			. '</ol>';
	}

	/**
	 * Return an array of WordPress role names keyed by role slug.
	 *
	 * @return array
	 */
	private function get_wp_roles_for_select() {
		$roles  = array();
		$global = wp_roles();
		foreach ( $global->role_names as $slug => $name ) {
			$roles[ $slug ] = translate_user_role( $name );
		}
		return $roles;
	}

	/**
	 * AJAX: Validate SSO configuration and return the computed redirect URI.
	 *
	 * @return void
	 */
	public function ajax_sso_test_config() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-ms365-graph' ) ), 403 );
		}

		check_ajax_referer( 'wp_ms365_sso_test', 'nonce' );

		$settings = WP_MS365_Auth::get_settings();
		$issues   = array();

		if ( empty( $settings['sso_enabled'] ) ) {
			$issues[] = __( 'Tenant sign-in is not enabled.', 'wp-ms365-graph' );
		}
		if ( empty( $settings['tenant_id'] ) ) {
			$issues[] = __( 'Tenant ID is missing.', 'wp-ms365-graph' );
		}
		if ( empty( $settings['client_id'] ) ) {
			$issues[] = __( 'Client ID is missing.', 'wp-ms365-graph' );
		}
		if ( empty( $settings['client_secret'] ) ) {
			$issues[] = __( 'Client Secret is missing.', 'wp-ms365-graph' );
		}

		wp_send_json_success(
			array(
				'redirect_uri' => WP_MS365_Auth::get_sso_redirect_uri(),
				'issues'       => $issues,
				'ready'        => empty( $issues ),
			)
		);
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

		// Load WP media library for image picker fields.
		wp_enqueue_media();

		wp_enqueue_script(
			'wp-ms365-admin',
			WP_MS365_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			WP_MS365_VERSION,
			true
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

		wp_safe_redirect(
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
	 * Export encrypted plugin settings bundle.
	 *
	 * @return void
	 */
	public function handle_export_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-ms365-graph' ) );
		}

		check_admin_referer( 'wp_ms365_export_settings' );

		$password         = isset( $_POST['export_password'] ) ? (string) wp_unslash( $_POST['export_password'] ) : '';
		$password_confirm = isset( $_POST['export_password_confirm'] ) ? (string) wp_unslash( $_POST['export_password_confirm'] ) : '';
		if ( '' === $password ) {
			$this->redirect_to_settings_with_import_export_notice( 'export', 'error', 'missing_password' );
		}

		if ( $password !== $password_confirm ) {
			$this->redirect_to_settings_with_import_export_notice( 'export', 'error', 'password_mismatch' );
		}

		$settings = WP_MS365_Auth::get_settings();
		$payload  = $this->encrypt_settings_blob( $settings, $password );

		if ( is_wp_error( $payload ) ) {
			$this->redirect_to_settings_with_import_export_notice( 'export', 'error', 'encryption_failed' );
		}

		$timestamp = gmdate( 'Ymd_His' );
		$filename  = self::SETTINGS_EXPORT_FILENAME_PREFIX . '_' . $timestamp . '.enc.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		echo $payload;
		exit;
	}

	/**
	 * Import encrypted plugin settings bundle.
	 *
	 * @return void
	 */
	public function handle_import_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-ms365-graph' ) );
		}

		check_admin_referer( 'wp_ms365_import_settings' );

		$password = isset( $_POST['import_password'] ) ? (string) wp_unslash( $_POST['import_password'] ) : '';
		if ( '' === $password ) {
			$this->redirect_to_settings_with_import_export_notice( 'import', 'error', 'missing_password' );
		}

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			$this->redirect_to_settings_with_import_export_notice( 'import', 'error', 'missing_file' );
		}

		if ( ! empty( $_FILES['import_file']['error'] ) && UPLOAD_ERR_OK !== (int) $_FILES['import_file']['error'] ) {
			$this->redirect_to_settings_with_import_export_notice( 'import', 'error', 'upload_failed' );
		}

		$tmp_file = (string) $_FILES['import_file']['tmp_name'];
		if ( ! is_readable( $tmp_file ) ) {
			$this->redirect_to_settings_with_import_export_notice( 'import', 'error', 'unreadable_file' );
		}

		$file_size = isset( $_FILES['import_file']['size'] ) ? (int) $_FILES['import_file']['size'] : 0;
		if ( $file_size <= 0 || $file_size > 1024 * 1024 ) {
			$this->redirect_to_settings_with_import_export_notice( 'import', 'error', 'invalid_size' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$encrypted_blob = file_get_contents( $tmp_file );
		if ( false === $encrypted_blob || '' === trim( (string) $encrypted_blob ) ) {
			$this->redirect_to_settings_with_import_export_notice( 'import', 'error', 'invalid_file' );
		}

		$decrypted_settings = $this->decrypt_settings_blob( (string) $encrypted_blob, $password );
		if ( is_wp_error( $decrypted_settings ) || ! is_array( $decrypted_settings ) ) {
			$this->redirect_to_settings_with_import_export_notice( 'import', 'error', 'decrypt_failed' );
		}

		$clean = $this->sanitize_settings( $decrypted_settings );
		update_option( 'wp_ms365_settings', $clean, false );

		$this->redirect_to_settings_with_import_export_notice( 'import', 'success' );
	}

	/**
	 * Redirect to the settings tab with an import/export status indicator.
	 *
	 * @param string $operation Operation key (import|export).
	 * @param string $status    Status key (success|error).
	 * @param string $reason    Optional error reason.
	 * @return void
	 */
	private function redirect_to_settings_with_import_export_notice( $operation, $status, $reason = '' ) {
		$args = array(
			'page'   => 'wp-ms365-graph',
			'tab'    => 'settings',
			'op'     => sanitize_key( (string) $operation ),
			'status' => sanitize_key( (string) $status ),
		);

		if ( '' !== $reason ) {
			$args['reason'] = sanitize_key( (string) $reason );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Encrypt settings array using password-derived key.
	 *
	 * @param  array  $settings Settings payload.
	 * @param  string $password Export password.
	 * @return string|WP_Error
	 */
	private function encrypt_settings_blob( array $settings, $password ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new WP_Error( 'ms365_encrypt_unavailable', __( 'OpenSSL is not available.', 'wp-ms365-graph' ) );
		}

		$plaintext = wp_json_encode(
			array(
				'format'      => self::SETTINGS_EXPORT_FORMAT,
				'version'     => self::SETTINGS_EXPORT_VERSION,
				'exported_at' => gmdate( 'c' ),
				'settings'    => $settings,
			)
		);

		if ( false === $plaintext || '' === $plaintext ) {
			return new WP_Error( 'ms365_export_encode_failed', __( 'Failed to encode settings payload.', 'wp-ms365-graph' ) );
		}

		$iterations = 120000;

		try {
			$salt = random_bytes( 16 );
			$iv   = random_bytes( 16 );
		} catch ( Exception $e ) {
			return new WP_Error( 'ms365_export_random_failed', __( 'Failed to generate encryption material.', 'wp-ms365-graph' ) );
		}

		$key        = hash_pbkdf2( 'sha256', (string) $password, $salt, $iterations, 32, true );
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return new WP_Error( 'ms365_export_encrypt_failed', __( 'Failed to encrypt settings payload.', 'wp-ms365-graph' ) );
		}

		$hmac = hash_hmac( 'sha256', $ciphertext, $key, true );

		$package = array(
			'format'     => self::SETTINGS_EXPORT_FORMAT,
			'version'    => self::SETTINGS_EXPORT_VERSION,
			'cipher'     => 'aes-256-cbc',
			'kdf'        => 'pbkdf2-sha256',
			'iterations' => $iterations,
			'salt'       => base64_encode( $salt ),
			'iv'         => base64_encode( $iv ),
			'hmac'       => base64_encode( $hmac ),
			'data'       => base64_encode( $ciphertext ),
		);

		$encoded_package = wp_json_encode( $package, JSON_PRETTY_PRINT );
		if ( false === $encoded_package || '' === $encoded_package ) {
			return new WP_Error( 'ms365_export_encode_failed', __( 'Failed to finalize encrypted settings payload.', 'wp-ms365-graph' ) );
		}

		return $encoded_package;
	}

	/**
	 * Decrypt encrypted settings payload and return settings array.
	 *
	 * @param  string $encrypted_blob Encrypted JSON package.
	 * @param  string $password       Import password.
	 * @return array|WP_Error
	 */
	private function decrypt_settings_blob( $encrypted_blob, $password ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return new WP_Error( 'ms365_decrypt_unavailable', __( 'OpenSSL is not available.', 'wp-ms365-graph' ) );
		}

		$package = json_decode( (string) $encrypted_blob, true );
		if ( ! is_array( $package ) ) {
			return new WP_Error( 'ms365_import_invalid_json', __( 'Invalid settings file format.', 'wp-ms365-graph' ) );
		}

		if ( ! isset( $package['format'] ) || self::SETTINGS_EXPORT_FORMAT !== (string) $package['format'] ) {
			return new WP_Error( 'ms365_import_invalid_format', __( 'Unsupported settings file format.', 'wp-ms365-graph' ) );
		}

		$iterations = isset( $package['iterations'] ) ? (int) $package['iterations'] : 0;
		$salt       = isset( $package['salt'] ) ? base64_decode( (string) $package['salt'], true ) : false;
		$iv         = isset( $package['iv'] ) ? base64_decode( (string) $package['iv'], true ) : false;
		$hmac       = isset( $package['hmac'] ) ? base64_decode( (string) $package['hmac'], true ) : false;
		$ciphertext = isset( $package['data'] ) ? base64_decode( (string) $package['data'], true ) : false;

		if ( $iterations < 50000 || false === $salt || false === $iv || false === $hmac || false === $ciphertext ) {
			return new WP_Error( 'ms365_import_invalid_payload', __( 'The settings file is corrupted or incomplete.', 'wp-ms365-graph' ) );
		}

		$key           = hash_pbkdf2( 'sha256', (string) $password, $salt, $iterations, 32, true );
		$computed_hmac = hash_hmac( 'sha256', $ciphertext, $key, true );

		if ( ! hash_equals( $hmac, $computed_hmac ) ) {
			return new WP_Error( 'ms365_import_invalid_password', __( 'Invalid password or tampered settings file.', 'wp-ms365-graph' ) );
		}

		$plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $plaintext || '' === $plaintext ) {
			return new WP_Error( 'ms365_import_decrypt_failed', __( 'Unable to decrypt the settings file.', 'wp-ms365-graph' ) );
		}

		$decoded = json_decode( $plaintext, true );
		if ( ! is_array( $decoded ) || empty( $decoded['settings'] ) || ! is_array( $decoded['settings'] ) ) {
			return new WP_Error( 'ms365_import_invalid_content', __( 'Decrypted settings payload is invalid.', 'wp-ms365-graph' ) );
		}

		return $decoded['settings'];
	}

	/**
	 * Handle reset request for shortcode render counters.
	 *
	 * @return void
	 */
	public function handle_reset_shortcode_counts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-ms365-graph' ) );
		}

		check_admin_referer( 'wp_ms365_reset_shortcode_counts' );
		WP_MS365_Shortcodes::reset_render_counts();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'wp-ms365-graph',
					'tab'          => 'dashboard',
					'counts_reset' => '1',
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
