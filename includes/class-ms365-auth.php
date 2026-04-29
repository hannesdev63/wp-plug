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

	/** Query parameter used to identify the delegated SSO callback request. */
	const SSO_CALLBACK_PARAM = 'ms365_sso_callback';

	/** Pretty permalink callback path for delegated SSO callback requests. */
	const SSO_CALLBACK_PATH = 'ms365-sso-callback';

	/** OpenID Connect scopes requested for delegated sign-in. */
	const SSO_SCOPES = 'openid email profile';

	/** User meta flag indicating the account is linked to Entra SSO. */
	const USER_META_SSO_LINKED = 'wp_ms365_sso_linked';

	/** User meta value for linked identity provider. */
	const USER_META_SSO_PROVIDER = 'wp_ms365_sso_provider';

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
	 * Return the redirect URI for the delegated SSO callback.
	 *
	 * @return string
	 */
	public static function get_sso_redirect_uri() {
		return home_url( '/' . trim( self::SSO_CALLBACK_PATH, '/' ) . '/' );
	}

	/**
	 * Return the legacy query-string redirect URI for delegated SSO callback.
	 *
	 * @return string
	 */
	public static function get_sso_legacy_redirect_uri() {
		return add_query_arg( self::SSO_CALLBACK_PARAM, '1', home_url( '/' ) );
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
			'calendar_header_duration' => '',
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
			// WordPress tenant sign-in (delegated Auth Code + PKCE).
			'sso_enabled'         => 0,
			'sso_force_redirect'  => 0,
			'sso_auto_create'     => 0,
			'sso_default_role'    => 'subscriber',
			'sso_allowed_domains' => '',
			'sso_redirect_url'    => '',
			'sso_prompt'          => '',
			'sso_domain_hint'     => '',
			'sso_login_hint'      => '',
			'sso_extra_scopes'    => '',
			'login_log_retention_days' => 30,
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

	// ------------------------------------------------------------------
	// Delegated Sign-In: Authorization Code + PKCE
	// ------------------------------------------------------------------

	/**
	 * Build a Microsoft authorization URL for tenant-based user sign-in.
	 *
	 * Stores a one-time transient keyed by the state value containing the
	 * code_verifier, nonce, and optional post-login redirect target so they
	 * can be validated and consumed in the callback.
	 *
	 * @param  string $redirect_after Internal URL to redirect to after login.
	 * @return string|false           Authorization URL or false on configuration error.
	 */
	public static function get_sso_login_url( $redirect_after = '' ) {
		$settings = self::get_settings();
		if ( empty( $settings['sso_enabled'] ) ) {
			return false;
		}

		if ( empty( $settings['tenant_id'] ) || empty( $settings['client_id'] ) ) {
			return false;
		}

		$verifier  = self::generate_pkce_verifier();
		$challenge = self::compute_pkce_challenge( $verifier );
		$state     = bin2hex( self::random_bytes( 32 ) );
		$nonce     = bin2hex( self::random_bytes( 32 ) );

		// Store artifacts in a transient; expire after 10 minutes.
		set_transient(
			'wp_ms365_sso_state_' . $state,
			array(
				'nonce'          => $nonce,
				'code_verifier'  => $verifier,
				'redirect_after' => self::sanitize_redirect( $redirect_after ),
				'created_at'     => time(),
			),
			10 * MINUTE_IN_SECONDS
		);

		$params = array(
			'client_id'             => $settings['client_id'],
			'response_type'         => 'code',
			'redirect_uri'          => self::get_sso_redirect_uri(),
			'scope'                 => self::get_sso_scope_string( $settings ),
			'state'                 => $state,
			'nonce'                 => $nonce,
			'code_challenge'        => $challenge,
			'code_challenge_method' => 'S256',
			'response_mode'         => 'query',
		);

		if ( ! empty( $settings['sso_prompt'] ) ) {
			$params['prompt'] = (string) $settings['sso_prompt'];
		}

		if ( ! empty( $settings['sso_domain_hint'] ) ) {
			$params['domain_hint'] = (string) $settings['sso_domain_hint'];
		}

		if ( ! empty( $settings['sso_login_hint'] ) ) {
			$params['login_hint'] = (string) $settings['sso_login_hint'];
		}

		return sprintf(
			'%s/%s/oauth2/v2.0/authorize?%s',
			self::AUTHORITY_BASE,
			rawurlencode( $settings['tenant_id'] ),
			http_build_query( $params, '', '&', PHP_QUERY_RFC3986 )
		);
	}

	/**
	 * Handle the OAuth callback from Microsoft and sign the user into WordPress.
	 *
	 * Hooked on `init`. Exits early if the callback query parameter is absent.
	 *
	 * @return void
	 */
	public static function maybe_handle_sso_callback() {
		if ( ! self::is_sso_callback_request() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';

		if ( '' !== $error ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error_desc = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '';
			WP_MS365_Logger::log( 'error', 'SSO callback: Microsoft returned error', array( 'error' => $error, 'description' => $error_desc ) );
			self::sso_die( __( 'Microsoft sign-in was denied or cancelled.', 'wp-ms365-graph' ) );
		}

		if ( '' === $code || '' === $state ) {
			WP_MS365_Logger::log( 'error', 'SSO callback: missing code or state' );
			self::sso_die( __( 'Invalid sign-in response.', 'wp-ms365-graph' ) );
		}

		// Validate and consume state transient (one-time use, prevents replay).
		$transient_key = 'wp_ms365_sso_state_' . $state;
		$state_data    = get_transient( $transient_key );
		delete_transient( $transient_key );

		if ( ! is_array( $state_data ) || empty( $state_data['nonce'] ) || empty( $state_data['code_verifier'] ) ) {
			WP_MS365_Logger::log( 'error', 'SSO callback: state not found or expired' );
			self::sso_die( __( 'Sign-in session expired or invalid. Please try again.', 'wp-ms365-graph' ) );
		}

		$settings = self::get_settings();
		if ( empty( $settings['sso_enabled'] ) ) {
			self::sso_die( __( 'Microsoft sign-in is not enabled.', 'wp-ms365-graph' ) );
		}

		// Exchange authorization code for tokens.
		$token_response = wp_remote_post(
			sprintf( '%s/%s/oauth2/v2.0/token', self::AUTHORITY_BASE, rawurlencode( $settings['tenant_id'] ) ),
			array(
				'timeout' => 30,
				'body'    => array(
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => self::get_sso_redirect_uri(),
					'code_verifier' => $state_data['code_verifier'],
					'scope'         => self::get_sso_scope_string( $settings ),
				),
			)
		);

		if ( is_wp_error( $token_response ) ) {
			WP_MS365_Logger::log( 'error', 'SSO callback: token exchange failed', array( 'err' => $token_response->get_error_message() ) );
			self::sso_die( __( 'Failed to obtain sign-in token. Please try again.', 'wp-ms365-graph' ) );
		}

		$token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
		if ( empty( $token_body['id_token'] ) ) {
			$err = isset( $token_body['error_description'] ) ? $token_body['error_description'] : 'no id_token';
			WP_MS365_Logger::log( 'error', 'SSO callback: no id_token in response', array( 'err' => $err ) );
			self::sso_die( __( 'Sign-in failed: no identity token received.', 'wp-ms365-graph' ) );
		}

		// Parse and validate ID token claims.
		$claims = self::parse_id_token( $token_body['id_token'], $settings, $state_data['nonce'] );
		if ( is_wp_error( $claims ) ) {
			WP_MS365_Logger::log( 'error', 'SSO callback: ID token validation failed', array( 'err' => $claims->get_error_message() ) );
			self::sso_die( $claims->get_error_message() );
		}

		// Resolve WordPress user from token identity.
		$user = self::resolve_wp_user( $claims, $settings );
		if ( is_wp_error( $user ) ) {
			WP_MS365_Logger::log( 'error', 'SSO callback: user resolution failed', array( 'err' => $user->get_error_message() ) );
			self::sso_die( $user->get_error_message() );
		}

		// Complete the WordPress login session.
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		WP_MS365_Logger::log_auth_event( sprintf( 'SSO login: user %s (%d)', $user->user_email, $user->ID ) );

		// Mark account as Entra-linked so local password login can be restricted.
		update_user_meta( $user->ID, self::USER_META_SSO_LINKED, 1 );
		update_user_meta( $user->ID, self::USER_META_SSO_PROVIDER, 'entra' );

		// Redirect to post-login destination.
		$redirect = ! empty( $state_data['redirect_after'] ) ? $state_data['redirect_after'] : home_url( '/' );
		if ( ! empty( $settings['sso_redirect_url'] ) ) {
			$redirect = self::sanitize_redirect( $settings['sso_redirect_url'] );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Determine whether current request targets the SSO callback endpoint.
	 *
	 * Supports both pretty-path and legacy query-string callback URLs.
	 *
	 * @return bool
	 */
	private static function is_sso_callback_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::SSO_CALLBACK_PARAM ] ) ) {
			return true;
		}

		$raw_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $raw_request_uri ) {
			return false;
		}

		$request_path  = wp_parse_url( $raw_request_uri, PHP_URL_PATH );
		$expected_path = wp_parse_url( self::get_sso_redirect_uri(), PHP_URL_PATH );

		if ( ! is_string( $request_path ) || ! is_string( $expected_path ) || '' === $request_path || '' === $expected_path ) {
			return false;
		}

		return untrailingslashit( $request_path ) === untrailingslashit( $expected_path );
	}

	// ------------------------------------------------------------------
	// Private PKCE / SSO helpers
	// ------------------------------------------------------------------

	/**
	 * Generate a cryptographically random PKCE code verifier.
	 *
	 * @return string base64url-encoded 64-byte random value.
	 */
	private static function generate_pkce_verifier() {
		return rtrim( strtr( base64_encode( self::random_bytes( 64 ) ), '+/', '-_' ), '=' );
	}

	/**
	 * Compute the PKCE code challenge from a verifier.
	 *
	 * @param  string $verifier PKCE code verifier.
	 * @return string           base64url-encoded SHA-256 hash.
	 */
	private static function compute_pkce_challenge( $verifier ) {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	/**
	 * Generate cryptographically secure random bytes.
	 *
	 * PHP >= 7.0 always provides random_bytes(); the openssl fallback has been
	 * removed because openssl_random_pseudo_bytes() silently ignores its
	 * $crypto_strong output parameter, making it unsafe as a fallback.
	 *
	 * @param  int    $length Number of bytes.
	 * @return string
	 */
	private static function random_bytes( $length ) {
		return random_bytes( $length );
	}

	/**
	 * Build delegated OAuth scope string for Entra authorization code flow.
	 *
	 * Includes required OIDC scopes and optional admin-configured scopes.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private static function get_sso_scope_string( array $settings ) {
		$scopes = preg_split( '/\s+/', trim( self::SSO_SCOPES ) );
		$scopes = is_array( $scopes ) ? $scopes : array();

		if ( ! empty( $settings['sso_extra_scopes'] ) ) {
			$extra = preg_split( '/\s+/', trim( (string) $settings['sso_extra_scopes'] ) );
			$extra = is_array( $extra ) ? $extra : array();
			$scopes = array_merge( $scopes, $extra );
		}

		$scopes = array_filter( array_map( 'trim', $scopes ) );
		$scopes = array_values( array_unique( $scopes ) );

		return implode( ' ', $scopes );
	}

	// ------------------------------------------------------------------
	// JWKS signature verification helpers
	// ------------------------------------------------------------------

	/**
	 * Verify the RS256 signature of a Microsoft JWT ID token.
	 *
	 * Tries the cached JWKS first; on key-not-found forces one refresh to
	 * handle key rotation without blocking every login.
	 *
	 * @param  string $id_token Raw JWT string (header.payload.signature).
	 * @param  array  $settings Plugin settings (tenant_id required).
	 * @return bool
	 */
	private static function verify_id_token_signature( $id_token, array $settings ) {
		$parts = explode( '.', (string) $id_token );
		if ( 3 !== count( $parts ) ) {
			return false;
		}

		// Decode JWT header to get kid and alg.
		$header_b64  = $parts[0];
		$padding     = str_repeat( '=', ( 4 - strlen( $header_b64 ) % 4 ) % 4 );
		$header_json = base64_decode( strtr( $header_b64, '-_', '+/' ) . $padding );
		if ( false === $header_json ) {
			return false;
		}

		$header = json_decode( $header_json, true );
		if ( ! is_array( $header ) || empty( $header['kid'] ) ) {
			return false;
		}

		// Microsoft ID tokens use RS256; reject anything else.
		if ( isset( $header['alg'] ) && 'RS256' !== $header['alg'] ) {
			return false;
		}

		$kid = (string) $header['kid'];

		// The signed data is the ASCII bytes of "header.payload".
		$signed_data = $parts[0] . '.' . $parts[1];

		// Decode the base64url signature.
		$sig_b64   = $parts[2];
		$padding   = str_repeat( '=', ( 4 - strlen( $sig_b64 ) % 4 ) % 4 );
		$signature = base64_decode( strtr( $sig_b64, '-_', '+/' ) . $padding );
		if ( false === $signature ) {
			return false;
		}

		// Try cached JWKS first; force one refresh on key-not-found (rotation).
		foreach ( array( false, true ) as $force_refresh ) {
			$jwks = self::get_jwks( $settings['tenant_id'], $force_refresh );
			if ( is_wp_error( $jwks ) ) {
				return false;
			}

			$public_key = self::find_jwks_key( $jwks, $kid );
			if ( false === $public_key ) {
				continue; // Key not in this set — try the refreshed set.
			}

			$result = openssl_verify( $signed_data, $signature, $public_key, OPENSSL_ALGO_SHA256 );

			// On PHP ≤ 7.4 the key is an OpenSSL resource; free it explicitly.
			if ( is_resource( $public_key ) ) {
				openssl_free_key( $public_key );
			}

			return 1 === $result;
		}

		return false;
	}

	/**
	 * Fetch (and cache) the JWKS key set for a tenant.
	 *
	 * Keys are cached for one hour. Call with $force_refresh = true to
	 * invalidate the cache (e.g., after a key-not-found during verification).
	 *
	 * @param  string $tenant_id     Azure tenant GUID or domain.
	 * @param  bool   $force_refresh Delete cached value and re-fetch.
	 * @return array|WP_Error        Array of JWK objects, or WP_Error.
	 */
	private static function get_jwks( $tenant_id, $force_refresh = false ) {
		$cache_key = 'wp_ms365_jwks_' . md5( $tenant_id );

		if ( $force_refresh ) {
			delete_transient( $cache_key );
		} else {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$url      = sprintf( '%s/%s/discovery/v2.0/keys', self::AUTHORITY_BASE, rawurlencode( $tenant_id ) );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ms365_jwks_fetch_failed', $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['keys'] ) || ! is_array( $body['keys'] ) ) {
			return new WP_Error( 'ms365_jwks_invalid', __( 'Invalid JWKS response from Microsoft.', 'wp-ms365-graph' ) );
		}

		set_transient( $cache_key, $body['keys'], HOUR_IN_SECONDS );

		return $body['keys'];
	}

	/**
	 * Find a JWK by kid and return an OpenSSL public key.
	 *
	 * @param  array  $jwks JWKS keys array.
	 * @param  string $kid  Key ID to locate.
	 * @return resource|OpenSSLAsymmetricKey|false  OpenSSL key or false.
	 */
	private static function find_jwks_key( array $jwks, $kid ) {
		foreach ( $jwks as $key ) {
			if ( ! is_array( $key ) ) {
				continue;
			}
			if ( ! isset( $key['kid'] ) || $key['kid'] !== $kid ) {
				continue;
			}
			if ( ! isset( $key['kty'] ) || 'RSA' !== $key['kty'] ) {
				continue;
			}
			if ( empty( $key['n'] ) || empty( $key['e'] ) ) {
				continue;
			}

			$pem = self::jwk_rsa_to_pem( $key['n'], $key['e'] );
			if ( false === $pem ) {
				return false;
			}

			$public_key = openssl_pkey_get_public( $pem );
			return ( false !== $public_key ) ? $public_key : false;
		}

		return false;
	}

	/**
	 * Convert an RSA JWK (n, e as base64url) into a PEM-encoded public key.
	 *
	 * Builds a SubjectPublicKeyInfo DER structure manually:
	 *   SEQUENCE {
	 *     SEQUENCE { OID rsaEncryption, NULL }
	 *     BIT STRING { SEQUENCE { INTEGER n, INTEGER e } }
	 *   }
	 *
	 * @param  string $n_b64url Base64url-encoded RSA modulus.
	 * @param  string $e_b64url Base64url-encoded RSA public exponent.
	 * @return string|false     PEM string, or false on decode failure.
	 */
	private static function jwk_rsa_to_pem( $n_b64url, $e_b64url ) {
		$pad     = str_repeat( '=', ( 4 - strlen( $n_b64url ) % 4 ) % 4 );
		$n_bytes = base64_decode( strtr( $n_b64url, '-_', '+/' ) . $pad );

		$pad     = str_repeat( '=', ( 4 - strlen( $e_b64url ) % 4 ) % 4 );
		$e_bytes = base64_decode( strtr( $e_b64url, '-_', '+/' ) . $pad );

		if ( false === $n_bytes || false === $e_bytes || '' === $n_bytes || '' === $e_bytes ) {
			return false;
		}

		// RSAPublicKey ::= SEQUENCE { modulus INTEGER, publicExponent INTEGER }
		$rsa_key = self::der_sequence(
			self::der_integer( $n_bytes ) . self::der_integer( $e_bytes )
		);

		// AlgorithmIdentifier: OID 1.2.840.113549.1.1.1 (rsaEncryption) + NULL params.
		$alg_id = self::der_sequence( "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00" );

		// BIT STRING: 0x03 + length + 0x00 (zero unused bits) + RSAPublicKey.
		$bit_string = "\x03" . self::der_length( strlen( $rsa_key ) + 1 ) . "\x00" . $rsa_key;

		// SubjectPublicKeyInfo ::= SEQUENCE { AlgorithmIdentifier, BIT STRING }
		$spki = self::der_sequence( $alg_id . $bit_string );

		return "-----BEGIN PUBLIC KEY-----\n"
			. chunk_split( base64_encode( $spki ), 64, "\n" )
			. "-----END PUBLIC KEY-----\n";
	}

	/**
	 * Encode an ASN.1 DER SEQUENCE (tag 0x30).
	 *
	 * @param  string $contents DER-encoded inner content.
	 * @return string
	 */
	private static function der_sequence( $contents ) {
		return "\x30" . self::der_length( strlen( $contents ) ) . $contents;
	}

	/**
	 * Encode an ASN.1 DER INTEGER (tag 0x02).
	 *
	 * Strips leading zero bytes but keeps at least one byte. Prepends 0x00
	 * when the high bit is set to prevent misinterpretation as a negative value.
	 *
	 * @param  string $bytes Raw unsigned big-endian integer bytes.
	 * @return string
	 */
	private static function der_integer( $bytes ) {
		$bytes = ltrim( $bytes, "\x00" );
		if ( '' === $bytes ) {
			$bytes = "\x00";
		}
		if ( ord( $bytes[0] ) >= 0x80 ) {
			$bytes = "\x00" . $bytes; // Prevent sign-bit misinterpretation.
		}
		return "\x02" . self::der_length( strlen( $bytes ) ) . $bytes;
	}

	/**
	 * Encode a DER length field (BER definite short or long form).
	 *
	 * @param  int    $len Content length in bytes.
	 * @return string
	 */
	private static function der_length( $len ) {
		if ( $len < 0x80 ) {
			return chr( $len );
		}
		$encoded = '';
		$tmp     = $len;
		while ( $tmp > 0 ) {
			$encoded = chr( $tmp & 0xff ) . $encoded;
			$tmp     = $tmp >> 8;
		}
		return chr( 0x80 | strlen( $encoded ) ) . $encoded;
	}

	/**
	 * Decode and validate the Microsoft ID token payload.
	 *
	 * Verifies the RS256 signature against Microsoft's JWKS endpoint, then
	 * validates audience, nonce, expiry, and issuer claims.
	 *
	 * @param  string $id_token Raw JWT string.
	 * @param  array  $settings Plugin settings.
	 * @param  string $nonce    Expected nonce value.
	 * @return array|WP_Error   Decoded claims array or WP_Error.
	 */
	private static function parse_id_token( $id_token, array $settings, $nonce ) {
		$parts = explode( '.', (string) $id_token );
		if ( 3 !== count( $parts ) ) {
			return new WP_Error( 'ms365_sso_invalid_token', __( 'Malformed identity token.', 'wp-ms365-graph' ) );
		}

		// Verify RS256 signature against Microsoft's JWKS before trusting any claims.
		if ( ! self::verify_id_token_signature( $id_token, $settings ) ) {
			return new WP_Error( 'ms365_sso_invalid_signature', __( 'Identity token signature is invalid.', 'wp-ms365-graph' ) );
		}

		$payload_b64  = $parts[1];
		$padding      = str_repeat( '=', ( 4 - strlen( $payload_b64 ) % 4 ) % 4 );
		$payload_json = base64_decode( strtr( $payload_b64, '-_', '+/' ) . $padding );
		if ( false === $payload_json ) {
			return new WP_Error( 'ms365_sso_invalid_token', __( 'Identity token payload could not be decoded.', 'wp-ms365-graph' ) );
		}

		$claims = json_decode( $payload_json, true );
		if ( ! is_array( $claims ) ) {
			return new WP_Error( 'ms365_sso_invalid_token', __( 'Identity token contains invalid payload.', 'wp-ms365-graph' ) );
		}

		// Audience must match our client ID.
		if ( empty( $claims['aud'] ) || (string) $claims['aud'] !== $settings['client_id'] ) {
			return new WP_Error( 'ms365_sso_aud_mismatch', __( 'Identity token audience mismatch.', 'wp-ms365-graph' ) );
		}

		// Token must not be expired (allow 60 s clock skew).
		if ( empty( $claims['exp'] ) || ( time() - 60 ) > (int) $claims['exp'] ) {
			return new WP_Error( 'ms365_sso_token_expired', __( 'Identity token has expired.', 'wp-ms365-graph' ) );
		}

		// Nonce must match what we sent (hash_equals prevents timing attacks).
		if ( empty( $claims['nonce'] ) || ! hash_equals( $nonce, (string) $claims['nonce'] ) ) {
			return new WP_Error( 'ms365_sso_nonce_mismatch', __( 'Identity token nonce mismatch.', 'wp-ms365-graph' ) );
		}

		// Issuer must be from our tenant.
		$expected_iss = sprintf( '%s/%s/v2.0', self::AUTHORITY_BASE, $settings['tenant_id'] );
		if ( empty( $claims['iss'] ) || (string) $claims['iss'] !== $expected_iss ) {
			return new WP_Error( 'ms365_sso_iss_mismatch', __( 'Identity token issuer mismatch.', 'wp-ms365-graph' ) );
		}

		return $claims;
	}

	/**
	 * Resolve (or optionally create) a WordPress user from ID token claims.
	 *
	 * Priority: preferred_username → email → upn. Checks allowed domain list
	 * if configured.
	 *
	 * @param  array $claims   Decoded ID token payload.
	 * @param  array $settings Plugin settings.
	 * @return WP_User|WP_Error
	 */
	private static function resolve_wp_user( array $claims, array $settings ) {
		// Extract the best available email/UPN identity.
		$email = '';
		foreach ( array( 'preferred_username', 'email', 'upn' ) as $key ) {
			if ( ! empty( $claims[ $key ] ) && false !== strpos( $claims[ $key ], '@' ) ) {
				$email = strtolower( sanitize_email( $claims[ $key ] ) );
				break;
			}
		}

		if ( '' === $email ) {
			return new WP_Error( 'ms365_sso_no_email', __( 'No email address found in Microsoft identity token.', 'wp-ms365-graph' ) );
		}

		// Enforce allowed-domain constraint if configured.
		if ( ! empty( $settings['sso_allowed_domains'] ) ) {
			$allowed = array_map( 'trim', explode( ',', strtolower( (string) $settings['sso_allowed_domains'] ) ) );
			$domain  = substr( $email, strpos( $email, '@' ) + 1 );
			if ( ! in_array( $domain, $allowed, true ) ) {
				return new WP_Error(
					'ms365_sso_domain_not_allowed',
					__( 'Your Microsoft account domain is not permitted to sign in to this site.', 'wp-ms365-graph' )
				);
			}
		}

		// Try to match an existing WordPress user by email.
		$user = get_user_by( 'email', $email );
		if ( $user instanceof WP_User ) {
			return $user;
		}

		// Auto-create if enabled.
		if ( ! empty( $settings['sso_auto_create'] ) ) {
			$display_name = ! empty( $claims['name'] ) ? sanitize_text_field( $claims['name'] ) : $email;
			$role         = ! empty( $settings['sso_default_role'] ) ? $settings['sso_default_role'] : 'subscriber';

			$user_id = wp_insert_user(
				array(
					'user_login'   => $email,
					'user_email'   => $email,
					'display_name' => $display_name,
					'user_pass'    => wp_generate_password( 32, true, true ),
					'role'         => $role,
				)
			);

			if ( is_wp_error( $user_id ) ) {
				WP_MS365_Logger::log( 'error', 'SSO auto-create failed', array( 'email' => $email, 'err' => $user_id->get_error_message() ) );
				return $user_id;
			}

			WP_MS365_Logger::log_auth_event( sprintf( 'SSO auto-created user: %s (role: %s)', $email, $role ) );
			return get_user_by( 'id', $user_id );
		}

		return new WP_Error(
			'ms365_sso_user_not_found',
			__( 'No WordPress account is associated with your Microsoft identity. Please contact the site administrator.', 'wp-ms365-graph' )
		);
	}

	/**
	 * Ensure a redirect target is an internal site URL.
	 *
	 * @param  string $url Candidate redirect URL.
	 * @return string      Safe internal URL, or home URL if the candidate is external.
	 */
	private static function sanitize_redirect( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return home_url( '/' );
		}
		// wp_validate_redirect returns empty string for external URLs.
		if ( ! wp_validate_redirect( $url, '' ) ) {
			return home_url( '/' );
		}
		return $url;
	}

	/**
	 * Output a user-facing SSO error page and exit.
	 *
	 * @param  string $message Plain-text message.
	 * @return void
	 */
	private static function sso_die( $message ) {
		wp_die(
			esc_html( $message ),
			esc_html__( 'Sign-in Error', 'wp-ms365-graph' ),
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}
}
