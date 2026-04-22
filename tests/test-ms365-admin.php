<?php
/**
 * Unit tests for WP_MS365_Admin::sanitize_settings().
 *
 * Run with: php tests/test-ms365-admin.php
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

$_option_store = array();

function add_action( $hook, $callback ) {
	return true;
}

function add_settings_error( $setting, $code, $message ) {
	return true;
}

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function sanitize_textarea_field( $str ) {
	$str = (string) $str;
	$str = str_replace( "\r", '', $str );
	return trim( strip_tags( $str ) );
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

function __( $text, $domain = null ) {
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function wp_die( $message, $status = 0 ) {
	throw new Exception( (string) $message );
}

require_once __DIR__ . '/../includes/class-ms365-auth.php';
require_once __DIR__ . '/../includes/class-ms365-admin.php';

$passes = 0;
$fails  = 0;

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

echo "=== Test: settings save preserves wording keys ===\n";
update_option(
	'wp_ms365_settings',
	array(
		'tenant_id'                => 'old-tenant',
		'client_id'                => 'old-client',
		'client_secret'            => 'old-secret',
		'specific_user'            => 'old@contoso.com',
		'custom_css'               => '.x{color:red;}',
		'calendar_empty_text'      => 'No appointments',
		'calendar_header_date'     => 'When',
		'calendar_header_event'    => 'What',
		'calendar_header_location' => 'Where',
		'files_empty_text'         => 'No docs',
		'files_header_file'        => 'Document',
		'files_header_size'        => 'Bytes',
		'files_header_modified'    => 'Changed',
	)
);

$admin = new WP_MS365_Admin();
$result = $admin->sanitize_settings(
	array(
		'tenant_id'     => 'new-tenant',
		'client_id'     => 'new-client',
		'client_secret' => 'new-secret',
		'specific_user' => 'new@contoso.com',
		'custom_css'    => '.y{color:blue;}',
	)
);

assert_equals( 'new-tenant', $result['tenant_id'], 'tenant_id updated from settings submission' );
assert_equals( 'new-client', $result['client_id'], 'client_id updated from settings submission' );
assert_equals( 'new-secret', $result['client_secret'], 'client_secret updated from settings submission' );
assert_equals( 'new@contoso.com', $result['specific_user'], 'specific_user updated from settings submission' );
assert_equals( '.y{color:blue;}', $result['custom_css'], 'custom_css updated from settings submission' );
assert_equals( 'No appointments', $result['calendar_empty_text'], 'calendar_empty_text preserved when not submitted' );
assert_equals( 'When', $result['calendar_header_date'], 'calendar_header_date preserved when not submitted' );
assert_equals( 'What', $result['calendar_header_event'], 'calendar_header_event preserved when not submitted' );
assert_equals( 'Where', $result['calendar_header_location'], 'calendar_header_location preserved when not submitted' );
assert_equals( 'No docs', $result['files_empty_text'], 'files_empty_text preserved when not submitted' );
assert_equals( 'Document', $result['files_header_file'], 'files_header_file preserved when not submitted' );
assert_equals( 'Bytes', $result['files_header_size'], 'files_header_size preserved when not submitted' );
assert_equals( 'Changed', $result['files_header_modified'], 'files_header_modified preserved when not submitted' );

echo "\n=== Test: wording save preserves credential keys ===\n";
update_option(
	'wp_ms365_settings',
	array(
		'tenant_id'                => 'tenant-keep',
		'client_id'                => 'client-keep',
		'client_secret'            => 'secret-keep',
		'specific_user'            => 'keep@contoso.com',
		'custom_css'               => '.keep{display:block;}',
		'calendar_empty_text'      => 'Old calendar empty',
		'calendar_header_date'     => 'Old date',
		'calendar_header_event'    => 'Old event',
		'calendar_header_location' => 'Old location',
		'files_empty_text'         => 'Old files empty',
		'files_header_file'        => 'Old file',
		'files_header_size'        => 'Old size',
		'files_header_modified'    => 'Old modified',
	)
);

$result = $admin->sanitize_settings(
	array(
		'calendar_empty_text'      => 'New calendar empty',
		'calendar_header_date'     => 'Date label',
		'calendar_header_event'    => 'Event label',
		'calendar_header_location' => 'Location label',
		'files_empty_text'         => 'New files empty',
		'files_header_file'        => 'File label',
		'files_header_size'        => 'Size label',
		'files_header_modified'    => 'Modified label',
	)
);

assert_equals( 'tenant-keep', $result['tenant_id'], 'tenant_id preserved when wording is submitted' );
assert_equals( 'client-keep', $result['client_id'], 'client_id preserved when wording is submitted' );
assert_equals( 'secret-keep', $result['client_secret'], 'client_secret preserved when wording is submitted' );
assert_equals( 'keep@contoso.com', $result['specific_user'], 'specific_user preserved when wording is submitted' );
assert_equals( '.keep{display:block;}', $result['custom_css'], 'custom_css preserved when wording is submitted' );
assert_equals( 'New calendar empty', $result['calendar_empty_text'], 'calendar_empty_text updated from wording submission' );
assert_equals( 'Date label', $result['calendar_header_date'], 'calendar_header_date updated from wording submission' );
assert_equals( 'Event label', $result['calendar_header_event'], 'calendar_header_event updated from wording submission' );
assert_equals( 'Location label', $result['calendar_header_location'], 'calendar_header_location updated from wording submission' );
assert_equals( 'New files empty', $result['files_empty_text'], 'files_empty_text updated from wording submission' );
assert_equals( 'File label', $result['files_header_file'], 'files_header_file updated from wording submission' );
assert_equals( 'Size label', $result['files_header_size'], 'files_header_size updated from wording submission' );
assert_equals( 'Modified label', $result['files_header_modified'], 'files_header_modified updated from wording submission' );

echo "\n===========================\n";
echo "Tests passed: {$passes}\n";
echo "Tests failed: {$fails}\n";
echo "===========================\n";

exit( $fails > 0 ? 1 : 0 );
