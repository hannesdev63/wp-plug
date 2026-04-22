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

		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$entry     = array(
			'timestamp' => $timestamp,
			'level'     => strtoupper( $level ),
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
		error_log( "[WP-MS365-{$entry['level']}] {$message}" );
	}

	/**
	 * Get all logs.
	 *
	 * @return array
	 */
	public static function get_logs() {
		$logs = get_option( self::LOG_OPTION, array() );
		return is_array( $logs ) ? $logs : array();
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
}
