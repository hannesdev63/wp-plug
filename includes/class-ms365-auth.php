<?php
/**
 * Microsoft 365 authentication handler.
 *
 * Uses OAuth 2.0 client credentials flow (app-only) so no user interaction
 * is required at runtime.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Auth {

	/** Microsoft identity platform base URL. */
	const AUTHORITY_BASE = 'https://login.microsoftonline.com';

	/** Graph scope for app-only tokens. */
	const SCOPES = 'https://graph.microsoft.com/.default';

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Obtain an app-only access token using client credentials flow.
	 *
	 * @return bool True on success.
	 */
	public static function refresh_access_token() {
		$settings      = self::get_settings();

		if ( empty( $settings['tenant_id'] ) || empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
			WP_MS365_Logger::log( 'debug', 'Token request skipped: credentials missing' );
			return false;
		}

		WP_MS365_Logger::log( 'debug', 'Requesting app-only token via client credentials' );

		$response = wp_remote_post(
			sprintf( '%s/%s/oauth2/v2.0/token', self::AUTHORITY_BASE, rawurlencode( $settings['tenant_id'] ) ),
			array(
				'timeout' => 30,
				'body'    => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'scope'         => self::SCOPES,
					'grant_type'    => 'client_credentials',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			WP_MS365_Logger::log( 'error', 'Client credentials token request failed: ' . $response->get_error_message() );
		} else {
			$status = wp_remote_retrieve_response_code( $response );
			WP_MS365_Logger::log( 'debug', 'Client credentials token response', array( 'status' => $status ) );
		}

		return self::process_token_response( $response );
	}

	/**
	 * Retrieve a valid access token, refreshing if necessary.
	 *
	 * @return string|false Access token string or false if not authenticated.
	 */
	public static function get_access_token() {
		$token = get_transient( 'wp_ms365_access_token' );
		if ( $token ) {
			return $token;
		}

		// Try to refresh.
		if ( self::refresh_access_token() ) {
			return get_transient( 'wp_ms365_access_token' );
		}

		return false;
	}

	/**
	 * Check whether the plugin has a usable token.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return (bool) self::get_access_token();
	}

	/**
	 * Revoke tokens and clear stored credentials.
	 *
	 * @return void
	 */
	public static function disconnect() {
		delete_transient( 'wp_ms365_access_token' );
		delete_option( 'wp_ms365_refresh_token' );
		delete_option( 'wp_ms365_token_expires' );
		delete_option( 'wp_ms365_connected_user' );
	}

	/**
	 * Backward-compatibility no-op from delegated OAuth implementation.
	 *
	 * @return void
	 */
	public static function maybe_handle_callback() {
		return;
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Parse a token endpoint response and persist the tokens.
	 *
	 * @param  array|WP_Error $response wp_remote_post() response.
	 * @return bool
	 */
	private static function process_token_response( $response ) {
		if ( is_wp_error( $response ) ) {
			WP_MS365_Logger::log( 'error', 'Token response error: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$error_desc = isset( $body['error_description'] ) ? $body['error_description'] : 'Unknown error';
			WP_MS365_Logger::log( 'error', 'No access token in response', array( 'error' => $error_desc ) );
			return false;
		}

		// Cache the access token until it expires (default 3600 s).
		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;
		set_transient( 'wp_ms365_access_token', $body['access_token'], $expires_in - 60 );
		update_option( 'wp_ms365_token_expires', time() + $expires_in );

		update_option( 'wp_ms365_connected_user', 'app-only' );
		WP_MS365_Logger::log_auth_event( 'App-only token acquired successfully' );

		return true;
	}

	/**
	 * Get plugin settings from the database.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'tenant_id'     => '',
			'client_id'     => '',
			'client_secret' => '',
			'specific_user' => '',
			'custom_css'    => '',
		);
		$settings = get_option( 'wp_ms365_settings', $defaults );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Return the OAuth redirect URI.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return add_query_arg(
			array( 'page' => 'wp-ms365-graph' ),
			admin_url( 'admin.php' )
		);
	}
}
