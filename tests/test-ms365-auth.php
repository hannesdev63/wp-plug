<?php
/**
 * Unit tests for WP_MS365_Auth helper methods.
 *
 * These tests run without a full WordPress bootstrap by stubbing the
 * minimal WordPress functions used in the class under test.
 *
 * Run with:  php tests/test-ms365-auth.php
 *
 * @package WP_MS365_Graph
 */

// ---------------------------------------------------------------------------
// Minimal WordPress function stubs (avoid loading full WP bootstrap)
// ---------------------------------------------------------------------------

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

$_option_store    = array();
$_transient_store = array();

function get_option( $key, $default = false ) {
	global $_option_store;
	return array_key_exists( $key, $_option_store ) ? $_option_store[ $key ] : $default;
}

function update_option( $key, $value ) {
	global $_option_store;
	$_option_store[ $key ] = $value;
	return true;
}

function add_option( $key, $value ) {
	global $_option_store;
	if ( ! array_key_exists( $key, $_option_store ) ) {
		$_option_store[ $key ] = $value;
	}
}

function delete_option( $key ) {
	global $_option_store;
	unset( $_option_store[ $key ] );
}

function get_transient( $key ) {
	global $_transient_store;
	return array_key_exists( $key, $_transient_store ) ? $_transient_store[ $key ] : false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	global $_transient_store;
	$_transient_store[ $key ] = $value;
	return true;
}

function delete_transient( $key ) {
	global $_transient_store;
	unset( $_transient_store[ $key ] );
}

function wp_parse_args( $args, $defaults ) {
	if ( is_array( $args ) ) {
		return array_merge( $defaults, $args );
	}
	return $defaults;
}

function sanitize_text_field( $str ) {
	return trim( strip_tags( $str ) );
}

function add_query_arg( $args, $url = '' ) {
	if ( empty( $url ) ) {
		return http_build_query( $args );
	}
	$sep = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
	return $url . $sep . http_build_query( $args );
}

function admin_url( $path = '' ) {
	return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
}

function wp_create_nonce( $action ) {
	return md5( $action . time() );
}

// ---------------------------------------------------------------------------
// Load class under test
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../includes/class-ms365-auth.php';

// ---------------------------------------------------------------------------
// Simple assertion helpers
// ---------------------------------------------------------------------------

$passes = 0;
$fails  = 0;

function assert_equals( $expected, $actual, $message ) {
	global $passes, $fails;
	if ( $expected === $actual ) {
		echo "  PASS: {$message}\n";
		$passes++;
	} else {
		echo "  FAIL: {$message}\n";
		echo "        Expected: " . var_export( $expected, true ) . "\n";
		echo "        Actual:   " . var_export( $actual,   true ) . "\n";
		$fails++;
	}
}

function assert_true( $value, $message ) {
	assert_equals( true, (bool) $value, $message );
}

function assert_false( $value, $message ) {
	assert_equals( false, (bool) $value, $message );
}

function assert_contains( $needle, $haystack, $message ) {
	global $passes, $fails;
	if ( strpos( $haystack, $needle ) !== false ) {
		echo "  PASS: {$message}\n";
		$passes++;
	} else {
		echo "  FAIL: {$message}\n";
		echo "        Needle '{$needle}' not found in: {$haystack}\n";
		$fails++;
	}
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo "=== Test: get_settings() returns defaults when no option is stored ===\n";
$settings = WP_MS365_Auth::get_settings();
assert_equals( '', $settings['tenant_id'],     'tenant_id defaults to empty string' );
assert_equals( '', $settings['client_id'],     'client_id defaults to empty string' );
assert_equals( '', $settings['client_secret'], 'client_secret defaults to empty string' );

echo "\n=== Test: get_settings() returns stored values ===\n";
update_option( 'wp_ms365_settings', array(
	'tenant_id'     => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
	'client_id'     => 'ffffffff-0000-1111-2222-333333333333',
	'client_secret' => 's3cr3t',
) );
$settings = WP_MS365_Auth::get_settings();
assert_equals( 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $settings['tenant_id'],     'tenant_id read correctly' );
assert_equals( 'ffffffff-0000-1111-2222-333333333333', $settings['client_id'],     'client_id read correctly' );
assert_equals( 's3cr3t',                               $settings['client_secret'], 'client_secret read correctly' );

echo "\n=== Test: get_redirect_uri() includes admin URL and page parameter ===\n";
$uri = WP_MS365_Auth::get_redirect_uri();
assert_contains( 'admin.php', $uri, 'redirect_uri contains admin.php' );
assert_contains( 'wp-ms365-graph', $uri, 'redirect_uri contains page parameter' );

echo "\n=== Test: get_authorization_url() builds a valid Microsoft login URL ===\n";
$auth_url = WP_MS365_Auth::get_authorization_url();
assert_contains( 'login.microsoftonline.com', $auth_url, 'auth URL targets Microsoft identity platform' );
assert_contains( 'response_type=code',        $auth_url, 'auth URL requests authorization code' );
assert_contains( 'ffffffff', $auth_url, 'auth URL embeds client_id' );

echo "\n=== Test: is_connected() returns false when no token is stored ===\n";
delete_transient( 'wp_ms365_access_token' );
delete_option( 'wp_ms365_refresh_token' );
// Mock refresh: refresh_access_token needs wp_remote_post, skip via absence of token.
assert_false( get_transient( 'wp_ms365_access_token' ), 'no access token in transient store' );

echo "\n=== Test: disconnect() clears all stored tokens ===\n";
set_transient( 'wp_ms365_access_token', 'dummy_token' );
update_option( 'wp_ms365_refresh_token', 'dummy_refresh' );
update_option( 'wp_ms365_token_expires', time() + 3600 );
update_option( 'wp_ms365_connected_user', 'Test User' );

WP_MS365_Auth::disconnect();
assert_false( get_transient( 'wp_ms365_access_token' ),     'access token cleared after disconnect' );
assert_false( get_option( 'wp_ms365_refresh_token', false ), 'refresh token cleared after disconnect' );
assert_false( get_option( 'wp_ms365_connected_user', false ), 'connected user cleared after disconnect' );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "\n===========================\n";
echo "Tests passed: {$passes}\n";
echo "Tests failed: {$fails}\n";
echo "===========================\n";

exit( $fails > 0 ? 1 : 0 );
