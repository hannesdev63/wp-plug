<?php
/**
 * Unit tests for WP_MS365_Login local bypass and force-redirect behavior.
 *
 * Run with: php tests/test-ms365-login.php
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

$_option_store      = array();
$_wp_redirect_calls = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function wp_unslash( $value ) {
	return $value;
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function is_user_logged_in() {
	return false;
}

function get_option( $key, $default = false ) {
	global $_option_store;
	return array_key_exists( $key, $_option_store ) ? $_option_store[ $key ] : $default;
}

function update_option( $key, $value ) {
	global $_option_store;
	$_option_store[ $key ] = $value;
	return true;
}

function wp_parse_args( $args, $defaults ) {
	if ( is_array( $args ) ) {
		return array_merge( $defaults, $args );
	}
	return $defaults;
}

function wp_redirect( $location, $status = 302 ) {
	global $_wp_redirect_calls;
	$_wp_redirect_calls[] = array(
		'location' => $location,
		'status'   => $status,
	);
	throw new Exception( 'redirect-called' );
}

require_once __DIR__ . '/../includes/class-ms365-auth.php';
require_once __DIR__ . '/../includes/class-ms365-login.php';

$passes = 0;
$fails  = 0;

function assert_true( $condition, $message ) {
	global $passes, $fails;
	if ( $condition ) {
		echo "  PASS: {$message}\n";
		$passes++;
	} else {
		echo "  FAIL: {$message}\n";
		$fails++;
	}
}

function assert_equals( $expected, $actual, $message ) {
	global $passes, $fails;
	if ( $expected === $actual ) {
		echo "  PASS: {$message}\n";
		$passes++;
	} else {
		echo "  FAIL: {$message}\n";
		echo '        Expected: ' . var_export( $expected, true ) . "\n";
		echo '        Actual:   ' . var_export( $actual, true ) . "\n";
		$fails++;
	}
}

function reset_request_state() {
	$_GET    = array();
	$_POST   = array();
	$_REQUEST = array();
	$_SERVER = array();
}

echo "=== Test: local login bypass via query string skips force redirect ===\n";
reset_request_state();
update_option(
	'wp_ms365_settings',
	array(
		'sso_enabled'        => 1,
		'sso_force_redirect' => 1,
	)
);
$_GET['ms365_local_login']     = '1';
$_REQUEST['ms365_local_login'] = '1';

$login = new WP_MS365_Login();
$_wp_redirect_calls = array();

try {
	$login->maybe_force_entra_login();
} catch ( Exception $e ) {
	// Redirects are thrown by our stub; this should not happen in bypass mode.
}

assert_equals( 0, count( $_wp_redirect_calls ), 'No redirect when ms365_local_login bypass is active' );

echo "\n=== Test: POST credential submission skips force redirect ===\n";
reset_request_state();
update_option(
	'wp_ms365_settings',
	array(
		'sso_enabled'        => 1,
		'sso_force_redirect' => 1,
	)
);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_REQUEST['action']        = 'login';
$_REQUEST['log']           = 'admin';
$_REQUEST['pwd']           = 'secret';

$_wp_redirect_calls = array();

try {
	$login->maybe_force_entra_login();
} catch ( Exception $e ) {
	// Redirects are thrown by our stub; this should not happen for login POST.
}

assert_equals( 0, count( $_wp_redirect_calls ), 'No redirect during local wp-login POST processing' );

echo "\n=== Test: bypass notice also works from POST/request context ===\n";
reset_request_state();
update_option(
	'wp_ms365_settings',
	array(
		'sso_enabled'        => 1,
		'sso_force_redirect' => 1,
	)
);
$_POST['ms365_local_login']    = '1';
$_REQUEST['ms365_local_login'] = '1';

$message = $login->render_local_login_notice( 'ORIGINAL' );

assert_true(
	false !== strpos( $message, 'Local login bypass is active.' ),
	'Bypass notice is rendered when bypass flag is present in request payload'
);
assert_true(
	false !== strpos( $message, 'ORIGINAL' ),
	'Original message is preserved when bypass notice is prepended'
);

echo "\n===========================\n";
echo "Tests passed: {$passes}\n";
echo "Tests failed: {$fails}\n";
echo "===========================\n";

exit( $fails > 0 ? 1 : 0 );
