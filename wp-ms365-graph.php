<?php
/**
 * Plugin Name:       Graph Connect for WordPress
 * Plugin URI:        https://github.com/hannesdev63/wp-plug
 * Description:       Integrates WordPress with the Microsoft 365 Graph API. Display calendar events, Sharepoint libraries and OneDrive files via shortcodes, with a full OAuth 2.0 authentication flow.
 * Version:           1.0.4
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            hannesdev63
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       graph-connect-for-wordpress
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WP_MS365_VERSION',     '1.0.4' );
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
		'custom_css'    => '',
		'calendar_empty_text'      => '',
		'calendar_header_date'     => '',
		'calendar_header_event'    => '',
		'calendar_header_location' => '',
		'files_empty_text'         => '',
		'files_header_file'        => '',
		'files_header_size'        => '',
		'files_header_modified'    => '',
		'redirect_uri'  => admin_url( 'admin.php?page=wp-ms365-graph' ),
	);
	add_option( 'wp_ms365_settings', $defaults );
}
register_activation_hook( __FILE__, 'wp_ms365_graph_activate' );

/**
 * Register deactivation hook – clean up transients.
 */
function wp_ms365_graph_deactivate() {
	delete_transient( 'wp_ms365_access_token' );
}
register_deactivation_hook( __FILE__, 'wp_ms365_graph_deactivate' );
