<?php
/**
 * Unit tests for WP_MS365_Shortcodes template-rendering helpers.
 *
 * Covers:
 * - render_snippet_template()  – double-brace, triple-brace, loop placeholders, XSS safety
 * - get_snippet_context_value() – bool, scalar, null, array/object normalisation
 * - maybe_render_custom_template() – toggle gate, empty template fallback, filter override
 *
 * Run with: php tests/test-ms365-shortcodes.php
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

// ---------------------------------------------------------------------------
// Minimal WordPress function stubs
// ---------------------------------------------------------------------------

$_option_store = array();

function add_action( $hook, $callback ) { return true; }
function add_shortcode( $tag, $callback ) { return true; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return true; }

$_active_filters = array();

function apply_filters( $hook, $value ) {
	global $_active_filters;
	if ( isset( $_active_filters[ $hook ] ) ) {
		$extra_args = array_slice( func_get_args(), 1 );
		return call_user_func_array( $_active_filters[ $hook ], $extra_args );
	}
	return $value;
}

function register_filter_for_test( $hook, $callback ) {
	global $_active_filters;
	$_active_filters[ $hook ] = $callback;
}

function clear_filters_for_test() {
	global $_active_filters;
	$_active_filters = array();
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

function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function wp_die( $msg ) { throw new Exception( (string) $msg ); }
function is_user_logged_in() { return false; }
function wp_login_url( $redirect = '' ) { return 'https://example.com/wp-login.php'; }
function get_permalink( $id = 0 ) { return 'https://example.com/page/'; }
function plugins_url( $path = '', $plugin = '' ) { return 'https://example.com/wp-content/plugins/' . ltrim( $path, '/' ); }
function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( $path, '/' ); }
function wp_create_nonce( $action ) { return 'testnonce'; }
function wp_localize_script( $handle, $name, $data ) { return true; }
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) { return true; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data ); }
function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
}

function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }

/**
 * Minimal wp_kses_post stub: strips <script> tags but allows basic HTML.
 * Real WP does more; this is enough to verify unsafe tags are removed.
 */
function wp_kses_post( $content ) {
	$content = (string) $content;
	$content = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $content );
	$content = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $content );
	$content = preg_replace( '/\s*on\w+\s*=\s*"[^"]*"/i', '', $content );
	$content = preg_replace( '/\s*on\w+\s*=\s*\'[^\']*\'/i', '', $content );
	return $content;
}

/**
 * esc_html stub: mirrors real WP behaviour.
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function is_wp_error( $thing ) { return false; }
function get_transient( $key ) { return false; }
function set_transient( $key, $value, $expiration = 0 ) { return true; }
function delete_transient( $key ) { return true; }

require_once __DIR__ . '/../includes/class-ms365-auth.php';
require_once __DIR__ . '/../includes/class-ms365-shortcodes.php';

// ---------------------------------------------------------------------------
// Test harness
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
		echo '        Expected: ' . var_export( $expected, true ) . "\n";
		echo '        Actual:   ' . var_export( $actual, true ) . "\n";
		$fails++;
	}
}

function assert_contains( $needle, $haystack, $message ) {
	global $passes, $fails;
	if ( false !== strpos( $haystack, $needle ) ) {
		echo "  PASS: {$message}\n";
		$passes++;
	} else {
		echo "  FAIL: {$message}\n";
		echo '        Needle:   ' . var_export( $needle, true ) . "\n";
		echo '        Haystack: ' . var_export( $haystack, true ) . "\n";
		$fails++;
	}
}

function assert_not_contains( $needle, $haystack, $message ) {
	global $passes, $fails;
	if ( false === strpos( $haystack, $needle ) ) {
		echo "  PASS: {$message}\n";
		$passes++;
	} else {
		echo "  FAIL: {$message}\n";
		echo '        Needle (not expected): ' . var_export( $needle, true ) . "\n";
		echo '        Haystack:              ' . var_export( $haystack, true ) . "\n";
		$fails++;
	}
}

// ---------------------------------------------------------------------------
// Expose private helpers via reflection
// ---------------------------------------------------------------------------

$shortcodes = new WP_MS365_Shortcodes();
$ref_class  = new ReflectionClass( $shortcodes );

function call_private( $shortcodes, $ref_class, $method_name, array $args ) {
	$method = $ref_class->getMethod( $method_name );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}
	return $method->invokeArgs( $shortcodes, $args );
}

// ---------------------------------------------------------------------------
// Helper: set settings option
// ---------------------------------------------------------------------------

function set_ms365_settings( array $overrides ) {
	global $_option_store;
	$defaults = array(
		'client_id'     => '',
		'client_secret' => '',
		'tenant_id'     => '',
	);
	$_option_store['wp_ms365_settings'] = array_merge( $defaults, $overrides );
}

// ===========================================================================
// Section 1: get_snippet_context_value
// ===========================================================================

echo "\n--- get_snippet_context_value ---\n";

// Missing key
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array(), 'missing' ) );
assert_equals( '', $result, 'Missing key returns empty string' );

// Boolean true
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array( 'flag' => true ), 'flag' ) );
assert_equals( 'true', $result, 'Boolean true returns "true"' );

// Boolean false
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array( 'flag' => false ), 'flag' ) );
assert_equals( 'false', $result, 'Boolean false returns "false"' );

// Integer scalar
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array( 'count' => 42 ), 'count' ) );
assert_equals( '42', $result, 'Integer scalar cast to string' );

// Float scalar
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array( 'ratio' => 3.14 ), 'ratio' ) );
assert_equals( '3.14', $result, 'Float scalar cast to string' );

// Null
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array( 'val' => null ), 'val' ) );
assert_equals( '', $result, 'Null cast to empty string' );

// String
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array( 'name' => 'Hello' ), 'name' ) );
assert_equals( 'Hello', $result, 'String value returned as-is' );

// Array encoded as JSON
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array( 'items' => array( 1, 2, 3 ) ), 'items' ) );
assert_equals( '[1,2,3]', $result, 'Array encoded via wp_json_encode' );

// Object encoded as JSON
$obj        = new stdClass();
$obj->a     = 'b';
$result = call_private( $shortcodes, $ref_class, 'get_snippet_context_value', array( array( 'obj' => $obj ), 'obj' ) );
assert_equals( '{"a":"b"}', $result, 'Object encoded via wp_json_encode' );

// ===========================================================================
// Section 2: render_snippet_template – double-brace {{key}}
// ===========================================================================

echo "\n--- render_snippet_template: double-brace {{key}} ---\n";

// Basic substitution
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'Hello {{name}}!',
	array( 'name' => 'World' ),
) );
assert_equals( 'Hello World!', $result, 'Basic double-brace substitution' );

// HTML in context value is escaped for {{}}
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'Title: {{title}}',
	array( 'title' => '<b>Bold</b>' ),
) );
assert_contains( '&lt;b&gt;', $result, 'HTML in context value is esc_html escaped for {{}}' );
assert_not_contains( '<b>', $result, 'Raw <b> not present after {{}} escaping' );

// Script payload in context value is escaped
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{xss}}',
	array( 'xss' => '<script>alert(1)</script>' ),
) );
assert_not_contains( '<script>', $result, 'Script tag in context value escaped by {{}}' );

// Missing key placeholder removed (empty string)
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'Before {{missing}} After',
	array(),
) );
assert_equals( 'Before  After', $result, 'Missing key replaced with empty string' );

// Multiple placeholders
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{a}} + {{b}} = {{c}}',
	array( 'a' => '1', 'b' => '2', 'c' => '3' ),
) );
assert_equals( '1 + 2 = 3', $result, 'Multiple double-brace placeholders' );

// Whitespace inside braces
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{ name }}',
	array( 'name' => 'WP' ),
) );
assert_equals( 'WP', $result, 'Whitespace inside double-braces is trimmed' );

// ===========================================================================
// Section 3: render_snippet_template – triple-brace {{{key}}}
// ===========================================================================

echo "\n--- render_snippet_template: triple-brace {{{key}}} ---\n";

// Markup is passed through wp_kses_post (safe tags allowed)
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{{content}}}',
	array( 'content' => '<p class="test">Hello</p>' ),
) );
assert_contains( '<p', $result, 'Triple-brace passes safe HTML through wp_kses_post' );

// Script tag is stripped by wp_kses_post for {{{ }}}
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{{content}}}',
	array( 'content' => '<script>evil()</script><p>safe</p>' ),
) );
assert_not_contains( '<script>', $result, 'Script tag stripped by wp_kses_post in {{{  }}}' );
assert_contains( '<p>safe</p>', $result, 'Safe HTML remains after script strip in {{{  }}}' );

// Triple-brace takes precedence over double-brace (no partial match)
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{{key}}}',
	array( 'key' => '<em>em</em>' ),
) );
assert_contains( '<em>', $result, 'Triple-brace allows <em> tag through wp_kses_post' );

// Missing key in triple-brace returns empty
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'A{{{missing}}}B',
	array(),
) );
assert_equals( 'AB', $result, 'Missing key in triple-brace replaced with empty' );

// Mixed: one triple, one double in same template
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'<div>{{{inner}}}</div><span>{{label}}</span>',
	array( 'inner' => '<b>bold</b>', 'label' => '<i>italic</i>' ),
) );
assert_contains( '<b>bold</b>', $result, 'Mixed: triple-brace allows markup' );
assert_not_contains( '<i>italic</i>', $result, 'Mixed: double-brace escapes markup' );
assert_contains( '&lt;i&gt;italic&lt;/i&gt;', $result, 'Mixed: double-brace output is HTML-encoded' );

// ===========================================================================
// Section 4: render_snippet_template – loop blocks {{#items}}...{{/items}}
// ===========================================================================

echo "\n--- render_snippet_template: loop blocks {{#items}}...{{/items}} ---\n";

// Basic loop iteration renders each item in order.
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{#items}}<li>{{name}}</li>{{/items}}',
	array(
		'items' => array(
			array( 'name' => 'First' ),
			array( 'name' => 'Second' ),
		),
	),
) );
assert_equals( '<li>First</li><li>Second</li>', $result, 'Loop block renders each item in order' );

// Empty arrays produce no output.
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'Before{{#items}}<li>{{name}}</li>{{/items}}After',
	array( 'items' => array() ),
) );
assert_equals( 'BeforeAfter', $result, 'Empty loop array renders empty string' );

// Missing or non-array loop values are ignored.
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'X{{#items}}<li>{{name}}</li>{{/items}}Y',
	array(),
) );
assert_equals( 'XY', $result, 'Missing loop key renders empty string' );

$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'X{{#items}}<li>{{name}}</li>{{/items}}Y',
	array( 'items' => 'not-an-array' ),
) );
assert_equals( 'XY', $result, 'Non-array loop key renders empty string' );

// Item context supports both escaped and triple-brace placeholders.
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{#items}}<article>{{title}} {{{body}}}</article>{{/items}}',
	array(
		'items' => array(
			array(
				'title' => '<b>Unsafe</b>',
				'body'  => '<p>Safe</p><script>bad()</script>',
			),
		),
	),
) );
assert_contains( '&lt;b&gt;Unsafe&lt;/b&gt;', $result, 'Loop item double-brace escapes item fields' );
assert_contains( '<p>Safe</p>', $result, 'Loop item triple-brace keeps safe HTML' );
assert_not_contains( '<script>', $result, 'Loop item triple-brace strips script tags' );

// Non-array entries are skipped instead of causing malformed output.
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'{{#items}}<li>{{name}}</li>{{/items}}',
	array(
		'items' => array(
			array( 'name' => 'Valid' ),
			'oops',
		),
	),
) );
assert_equals( '<li>Valid</li>', $result, 'Loop skips non-array entries' );

// ===========================================================================
// Section 5: maybe_render_custom_template – fallback when disabled / empty
// ===========================================================================

echo "\n--- maybe_render_custom_template: fallback logic ---\n";

// Toggle disabled → returns default_html
set_ms365_settings( array(
	'shortcode_render_calendar_enabled'  => 0,
	'shortcode_render_calendar_template' => '<section>{{title}}</section>',
) );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'calendar',
	'<p>default</p>',
	array( 'title' => 'Events' ),
) );
assert_equals( '<p>default</p>', $result, 'Disabled toggle: falls back to default_html' );

// Toggle enabled, empty template → returns default_html
set_ms365_settings( array(
	'shortcode_render_calendar_enabled'  => 1,
	'shortcode_render_calendar_template' => '   ',
) );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'calendar',
	'<p>default</p>',
	array( 'title' => 'Events' ),
) );
assert_equals( '<p>default</p>', $result, 'Enabled but empty template: falls back to default_html' );

// Toggle enabled, whitespace-only result → returns default_html
set_ms365_settings( array(
	'shortcode_render_calendar_enabled'  => 1,
	'shortcode_render_calendar_template' => '   ',
) );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'calendar',
	'<p>default</p>',
	array(),
) );
assert_equals( '<p>default</p>', $result, 'Whitespace-only template renders default' );

// Toggle enabled, valid template → renders template
set_ms365_settings( array(
	'shortcode_render_calendar_enabled'  => 1,
	'shortcode_render_calendar_template' => '<div class="cal">{{title}}</div>',
) );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'calendar',
	'<p>default</p>',
	array( 'title' => 'My Events' ),
) );
assert_equals( '<div class="cal">My Events</div>', $result, 'Enabled valid template: template output returned' );

// `content` placeholder is automatically injected
set_ms365_settings( array(
	'shortcode_render_calendar_enabled'  => 1,
	'shortcode_render_calendar_template' => '<wrapper>{{{content}}}</wrapper>',
) );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'calendar',
	'<p>default content</p>',
	array(),
) );
assert_contains( '<p>default content</p>', $result, '{{{content}}} auto-injects default_html' );

// `shortcode` placeholder is automatically injected
set_ms365_settings( array(
	'shortcode_render_calendar_enabled'  => 1,
	'shortcode_render_calendar_template' => '{{shortcode}}',
) );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'calendar',
	'<p>default</p>',
	array(),
) );
assert_equals( 'calendar', $result, '{{shortcode}} auto-injected as scope key' );

// Unknown scope → returns default_html
set_ms365_settings( array() );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'unknown_scope',
	'<p>fallback</p>',
	array(),
) );
assert_equals( '<p>fallback</p>', $result, 'Unknown scope: returns default_html' );

// ===========================================================================
// Section 5: maybe_render_custom_template – filter override
// ===========================================================================

echo "\n--- maybe_render_custom_template: filter override ---\n";

// Filter that replaces output entirely
register_filter_for_test( 'wp_ms365_shortcode_custom_render', function( $output, $scope, $context, $default_html ) {
	return '<span>filtered</span>';
} );

set_ms365_settings( array(
	'shortcode_render_files_enabled'  => 0,
	'shortcode_render_files_template' => '',
) );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'files',
	'<p>default</p>',
	array(),
) );
assert_equals( '<span>filtered</span>', $result, 'Filter override replaces output' );
clear_filters_for_test();

// Filter returning non-string falls back to $output
register_filter_for_test( 'wp_ms365_shortcode_custom_render', function( $output, $scope, $context, $default_html ) {
	return null;
} );
set_ms365_settings( array(
	'shortcode_render_files_enabled'  => 0,
	'shortcode_render_files_template' => '',
) );
$result = call_private( $shortcodes, $ref_class, 'maybe_render_custom_template', array(
	'files',
	'<p>default</p>',
	array(),
) );
assert_equals( '<p>default</p>', $result, 'Filter returning null falls back to $output' );
clear_filters_for_test();

// ===========================================================================
// Section 6: XSS safety in template source
// ===========================================================================

echo "\n--- XSS safety in template source ---\n";

// Script tags in the TEMPLATE itself are sanitized by wp_kses_post at save time;
// render_snippet_template receives already-sanitized input. Verify it does not
// re-introduce script injection via triple-brace with a context value:
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'<p>{{{evil}}}</p>',
	array( 'evil' => '<script>document.cookie</script>' ),
) );
assert_not_contains( '<script>', $result, 'Triple-brace: script in context stripped by wp_kses_post' );

// onerror attribute – context value is esc_html encoded so the raw attribute cannot execute
$result = call_private( $shortcodes, $ref_class, 'render_snippet_template', array(
	'<img src="{{src}}">',
	array( 'src' => '" onerror="alert(1)"' ),
) );
// esc_html encodes quotes so onerror cannot form a real HTML attribute
assert_not_contains( '" onerror="', $result, 'Inline event handler in context value encoded, cannot form real attribute' );

// ===========================================================================
// Summary
// ===========================================================================

echo "\n";
echo '=======================================================', "\n";
echo "Results: {$passes} passed, {$fails} failed.\n";
echo '=======================================================', "\n";

exit( $fails > 0 ? 1 : 0 );
