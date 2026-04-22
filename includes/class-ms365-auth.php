<?php
/**
 * Microsoft 365 OAuth 2.0 Authentication handler.
 *
 * Implements the Authorization Code flow using WordPress HTTP API and
 * stores tokens securely as WordPress transients / options.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Auth {

	/** Microsoft identity platform base URL. */
	const AUTHORITY_BASE = 'https://login.microsoftonline.com';

	/** Graph API scopes requested. */
	const SCOPES = 'offline_access User.Read User.ReadBasic.All Calendars.Read Calendars.Read.Shared Files.Read Files.Read.All';

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Build the authorization URL that the user must visit to grant consent.
	 *
	 * @return string
	 */
	public static function get_authorization_url() {
		$settings = self::get_settings();
		$state    = wp_create_nonce( 'wp_ms365_oauth_state' );
		update_option( 'wp_ms365_oauth_state', $state );

		$params = array(
			'client_id'     => $settings['client_id'],
			'response_type' => 'code',
			'redirect_uri'  => self::get_redirect_uri(),
			'response_mode' => 'query',
			'scope'         => self::SCOPES,
			'state'         => $state,
		);

		return sprintf(
			'%s/%s/oauth2/v2.0/authorize?%s',
			self::AUTHORITY_BASE,
			rawurlencode( $settings['tenant_id'] ),
			http_build_query( $params )
		);
	}

	/**
	 * Exchange an authorization code for access + refresh tokens.
	 *
	 * @param  string $code Authorization code from Microsoft.
	 * @return bool         True on success, false on failure.
	 */
	public static function exchange_code_for_token( $code ) {
		$settings = self::get_settings();

		WP_MS365_Logger::log_auth_event( 'Code exchange requested', array( 'code' => substr( $code, 0, 10 ) . '...' ) );

		$response = wp_remote_post(
			sprintf( '%s/%s/oauth2/v2.0/token', self::AUTHORITY_BASE, rawurlencode( $settings['tenant_id'] ) ),
			array(
				'timeout' => 30,
				'body'    => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'scope'         => self::SCOPES,
					'code'          => $code,
					'redirect_uri'  => self::get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			WP_MS365_Logger::log( 'error', 'Token exchange failed: ' . $response->get_error_message() );
		} else {
			$status = wp_remote_retrieve_response_code( $response );
			WP_MS365_Logger::log( 'info', 'Token exchange response', array( 'status' => $status ) );
		}

		return self::process_token_response( $response );
	}

	/**
	 * Use the stored refresh token to obtain a new access token.
	 *
	 * @return bool True on success.
	 */
	public static function refresh_access_token() {
		$settings      = self::get_settings();
		$refresh_token = get_option( 'wp_ms365_refresh_token', '' );

		if ( empty( $refresh_token ) ) {
			return false;
		}

		WP_MS365_Logger::log( 'debug', 'Attempting token refresh' );

		$response = wp_remote_post(
			sprintf( '%s/%s/oauth2/v2.0/token', self::AUTHORITY_BASE, rawurlencode( $settings['tenant_id'] ) ),
			array(
				'timeout' => 30,
				'body'    => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'scope'         => self::SCOPES,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			WP_MS365_Logger::log( 'error', 'Token refresh failed: ' . $response->get_error_message() );
		} else {
			$status = wp_remote_retrieve_response_code( $response );
			WP_MS365_Logger::log( 'debug', 'Token refresh response', array( 'status' => $status ) );
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
	 * Hook called during `plugins_loaded` to detect the OAuth callback
	 * redirect from Microsoft and exchange the code for a token.
	 *
	 * @return void
	 */
	public static function maybe_handle_callback() {
		// Only act on the admin redirect URI page.
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page  = isset( $_GET['page'] )  ? sanitize_key( $_GET['page'] )  : '';
		$code  = isset( $_GET['code'] )  ? sanitize_text_field( wp_unslash( $_GET['code'] ) )  : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:enable

		if ( 'wp-ms365-graph' !== $page || empty( $code ) ) {
			return;
		}

		// Validate state (CSRF protection).
		$saved_state = get_option( 'wp_ms365_oauth_state', '' );
		if ( empty( $saved_state ) || ! hash_equals( $saved_state, $state ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>'
					. esc_html__( 'Microsoft 365: Invalid OAuth state – possible CSRF attack.', 'wp-ms365-graph' )
					. '</p></div>';
			} );
			return;
		}

		delete_option( 'wp_ms365_oauth_state' );

		if ( self::exchange_code_for_token( $code ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success"><p>'
					. esc_html__( 'Microsoft 365: Successfully connected!', 'wp-ms365-graph' )
					. '</p></div>';
			} );
		} else {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>'
					. esc_html__( 'Microsoft 365: Token exchange failed. Check your credentials.', 'wp-ms365-graph' )
					. '</p></div>';
			} );
		}
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

		// Persist the refresh token for future use.
		if ( ! empty( $body['refresh_token'] ) ) {
			update_option( 'wp_ms365_refresh_token', $body['refresh_token'] );
		}

		// Store basic user info if present (id_token).
		if ( ! empty( $body['id_token'] ) ) {
			$parts   = explode( '.', $body['id_token'] );
			$payload = isset( $parts[1] ) ? json_decode( self::base64_url_decode( $parts[1] ), true ) : array();
			if ( ! empty( $payload['name'] ) ) {
				update_option( 'wp_ms365_connected_user', sanitize_text_field( $payload['name'] ) );
				WP_MS365_Logger::log_auth_event( 'Connected successfully', array( 'user' => sanitize_text_field( $payload['name'] ) ) );
			}
		}

		return true;
	}

	/**
	 * Base64-URL decode (for JWT).
	 *
	 * @param  string $data Base64-URL encoded string.
	 * @return string
	 */
	private static function base64_url_decode( $data ) {
		$remainder = strlen( $data ) % 4;
		if ( $remainder ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		$decoded = base64_decode( strtr( $data, '-_', '+/' ), true );
		return ( false === $decoded ) ? '' : $decoded;
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
