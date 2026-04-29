<?php
/**
 * Logging and diagnostics for WP Microsoft 365 Graph.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Logger {

	/** Log option key. */
	const LOG_OPTION = 'wp_ms365_debug_log';

	/** Maximum log entries to keep. */
	const MAX_LOG_ENTRIES = 500;

	/**
	 * Log a message.
	 *
	 * @param string $level   Log level (debug, info, warning, error).
	 * @param string $message Message to log.
	 * @param array  $context Optional context data.
	 */
	public static function log( $level, $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$level   = strtoupper( (string) $level );
		$message = self::redact_string( (string) $message );
		$context = self::redact_context( $context );

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$entry     = array(
			'timestamp' => $timestamp,
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
		);

		// Get existing logs.
		$logs = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Add new entry.
		$logs[] = $entry;

		// Trim to max entries.
		if ( count( $logs ) > self::MAX_LOG_ENTRIES ) {
			$logs = array_slice( $logs, -self::MAX_LOG_ENTRIES );
		}

		update_option( self::LOG_OPTION, $logs );

		// Also log to PHP error log if available.
		error_log( "[WP-MS365-{$entry['level']}] {$entry['message']}" );
	}

	/**
	 * Get all logs.
	 *
	 * @return array
	 */
	public static function get_logs() {
		$logs = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $logs ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $logs as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$sanitized[] = array(
				'timestamp' => isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '',
				'level'     => isset( $entry['level'] ) ? (string) $entry['level'] : 'INFO',
				'message'   => self::redact_string( isset( $entry['message'] ) ? (string) $entry['message'] : '' ),
				'context'   => self::redact_context( isset( $entry['context'] ) ? $entry['context'] : array() ),
			);
		}

		return $sanitized;
	}

	/**
	 * Clear all logs.
	 *
	 * @return bool
	 */
	public static function clear_logs() {
		return delete_option( self::LOG_OPTION );
	}

	/**
	 * Log authentication event.
	 *
	 * @param string $event Event name.
	 * @param array  $data  Event data.
	 */
	public static function log_auth_event( $event, $data = array() ) {
		self::log( 'info', "Auth: {$event}", $data );
	}

	/**
	 * Log Graph API call.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Endpoint path.
	 * @param int    $status   HTTP status code.
	 * @param array  $error    Optional error details.
	 */
	public static function log_graph_call( $method, $endpoint, $status, $error = null ) {
		$message = "{$method} {$endpoint} => {$status}";
		$level   = ( $status >= 400 ) ? 'error' : 'debug';
		$context = array( 'status' => $status );

		if ( $error ) {
			$context['error'] = $error;
		}

		self::log( $level, $message, $context );
	}

	/**
	 * Format logs for display.
	 *
	 * @return string HTML table of logs.
	 */
	public static function render_logs_table() {
		$logs = self::get_logs();

		if ( empty( $logs ) ) {
			return '<p>' . esc_html__( 'No logs recorded. Enable WP_DEBUG in wp-config.php to see debug logs.', 'wp-ms365-graph' ) . '</p>';
		}

		$html = '<table class="widefat striped">';
		$html .= '<thead><tr><th>Time</th><th>Level</th><th>Message</th><th>Context</th></tr></thead>';
		$html .= '<tbody>';

		// Show most recent first.
		foreach ( array_reverse( $logs ) as $entry ) {
			$level_class = strtolower( $entry['level'] );
			$context_str = ! empty( $entry['context'] ) ? wp_json_encode( $entry['context'] ) : '—';
			$html .= sprintf(
				'<tr class="log-level-%s"><td>%s</td><td><code>%s</code></td><td>%s</td><td><code>%s</code></td></tr>',
				esc_attr( $level_class ),
				esc_html( $entry['timestamp'] ),
				esc_html( $entry['level'] ),
				esc_html( $entry['message'] ),
				esc_html( $context_str )
			);
		}

		$html .= '</tbody>';
		$html .= '</table>';

		return $html;
	}

	/**
	 * Recursively redact sensitive values in log context.
	 *
	 * @param mixed  $value Context value.
	 * @param string $key   Optional context key.
	 * @return mixed
	 */
	private static function redact_context( $value, $key = '' ) {
		$key_lc = strtolower( (string) $key );

		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $child_key => $child_value ) {
				$sanitized[ $child_key ] = self::redact_context( $child_value, (string) $child_key );
			}
			return $sanitized;
		}

		if ( is_object( $value ) ) {
			return self::redact_context( (array) $value, $key );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		$scalar = (string) $value;

		if ( preg_match( '/(email|upn|userprincipalname|token|secret|password|authorization|ip|code)/i', $key_lc ) ) {
			return '[redacted]';
		}

		return self::redact_string( $scalar );
	}

	/**
	 * Redact common PII/secrets from a free-text string.
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	private static function redact_string( $text ) {
		if ( '' === $text ) {
			return $text;
		}

		$patterns = array(
			// Email/UPN addresses.
			'/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i'                         => '[redacted-email]',
			// IPv4 addresses.
			'/\b(?:\d{1,3}\.){3}\d{1,3}\b/'                                            => '[redacted-ip]',
			// GUID/Object IDs.
			'/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i' => '[redacted-guid]',
			// OAuth/query secrets.
			'/\b(access_token|refresh_token|id_token|client_secret|password|code)=([^&\s]+)/i' => '$1=[redacted-secret]',
			// Authorization bearer token values.
			'/(authorization\s*:\s*bearer\s+)[A-Za-z0-9\-._~+\/=]+/i'                  => '$1[redacted-token]',
			// JWT-like strings.
			'/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/'               => '[redacted-jwt]',
		);

		foreach ( $patterns as $pattern => $replacement ) {
			$text = preg_replace( $pattern, $replacement, $text );
		}

		return (string) $text;
	}
}
