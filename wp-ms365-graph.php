<?php
/**
 * Plugin Name:       ESC Connect
 * Plugin URI:        https://github.com/hannesdev63/wp-plug
 * Description:       Integrates WordPress with the Microsoft 365 Graph API. Display calendar events, Sharepoint libraries and OneDrive files via shortcodes, with a full OAuth 2.0 authentication flow.
 * Version:           1.2.3
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            hannesdev63
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-ms365-graph
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WP_MS365_VERSION',     '1.2.3' );
define( 'WP_MS365_PLUGIN_FILE', __FILE__ );
define( 'WP_MS365_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WP_MS365_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Autoload plugin classes.
spl_autoload_register( function ( $class ) {
	$prefix = 'WP_MS365_';
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}
	$relative = strtolower( str_replace( array( $prefix, '_' ), array( '', '-' ), $class ) );
	$file     = WP_MS365_PLUGIN_DIR . 'includes/class-ms365-' . $relative . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Load logger first (used by other classes).
require_once WP_MS365_PLUGIN_DIR . 'includes/class-ms365-logger.php';

/**
 * Bootstrap the plugin after all plugins have loaded.
 */
function wp_ms365_graph_init() {
	// Load text domain.
	load_plugin_textdomain(
		'wp-ms365-graph',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Admin interface.
	if ( is_admin() ) {
		new WP_MS365_Admin();
	}

	// Front-end shortcodes (always registered so they work in widgets / REST).
	new WP_MS365_Shortcodes();

	// Tenant sign-in (login-page button + SSO callback handler).
	new WP_MS365_Login();

	// Optional replacement for wp_mail() via Microsoft Graph sendMail.
	new WP_MS365_Mail();

	// Login logging (successful and failed attempts + admin page).
	new WP_MS365_Login_Logs();

	// WordPress content access tracking for the Access Statistics page.
	new WP_MS365_WP_Access_Stats();

	// Handle OAuth callback redirect from Microsoft.
	WP_MS365_Auth::maybe_handle_callback();
}
add_action( 'plugins_loaded', 'wp_ms365_graph_init' );

/**
 * Register activation hook – create DB option defaults.
 */
function wp_ms365_graph_activate() {
	$defaults = array(
		'tenant_id'     => '',
		'client_id'     => '',
		'client_secret' => '',
		'specific_user' => '',
		'mail_enabled'  => 0,
		'mail_sender_user' => '',
		'mail_save_to_sent_items' => 1,
		'teams_team_id' => '',
		'teams_channel_id' => '',
		'teams_workflow_url' => '',
		'teams_webhook_url' => '',
		'teams_rate_limit_max' => 3,
		'teams_rate_limit_window' => 300,
		'teams_min_submit_seconds' => 3,
		'custom_css'    => '',
		'calendar_empty_text'      => '',
		'calendar_header_date'     => '',
		'calendar_header_event'    => '',
		'calendar_header_location' => '',
		'files_empty_text'         => '',
		'files_header_file'        => '',
		'files_header_size'        => '',
		'files_header_modified'    => '',
		'teams_form_placeholder'   => '',
		'teams_form_button_text'   => '',
		'teams_form_label_name'    => '',
		'teams_form_label_email'   => '',
		'teams_form_label_message' => '',
		'teams_form_success'       => '',
		'teams_form_error_invalid_nonce' => '',
		'teams_form_error_missing_fields' => '',
		'teams_form_error_invalid_email' => '',
		'teams_form_error_invalid_form' => '',
		'teams_form_error_submitted_too_fast' => '',
		'teams_form_error_rate_limited' => '',
		'teams_form_error_invalid_endpoint' => '',
		'teams_form_error_post_fail' => '',
		'teams_form_error_unknown'  => '',
		'sso_signin_button_text'  => '',
		'sso_signin_button_image' => '',
		'redirect_uri'  => admin_url( 'admin.php?page=wp-ms365-graph' ),
		// WordPress tenant sign-in (delegated Auth Code + PKCE).
		'sso_enabled'         => 0,
		'sso_force_redirect'  => 0,
		'sso_auto_create'     => 0,
		'sso_use_ms_avatar'   => 0,
		'sso_default_role'    => 'subscriber',
		'sso_allowed_domains' => '',
		'sso_redirect_url'    => '',
		'sso_prompt'          => '',
		'sso_domain_hint'     => '',
		'sso_login_hint'      => '',
		'sso_extra_scopes'    => '',
		'login_log_retention_days' => 30,
	);
	add_option( 'wp_ms365_settings', $defaults );
	WP_MS365_Login_Logs::create_table();
	WP_MS365_Login_Logs::schedule_cleanup();
	WP_MS365_WP_Access_Stats::create_table();
	WP_MS365_WP_Access_Stats::schedule_cleanup();
}
register_activation_hook( __FILE__, 'wp_ms365_graph_activate' );

/**
 * Register deactivation hook – clean up transients.
 */
function wp_ms365_graph_deactivate() {
	delete_transient( 'wp_ms365_access_token' );
	WP_MS365_Login_Logs::unschedule_cleanup();
	WP_MS365_WP_Access_Stats::unschedule_cleanup();
}
register_deactivation_hook( __FILE__, 'wp_ms365_graph_deactivate' );
