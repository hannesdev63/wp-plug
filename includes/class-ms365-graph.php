<?php
/**
 * Microsoft Graph API HTTP client.
 *
 * Thin wrapper around wp_remote_* functions that injects the Bearer token,
 * handles rate-limit retries, and decodes JSON responses.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Graph {

	/** Graph API base URL. */
	const API_BASE = 'https://graph.microsoft.com/v1.0';

	// ------------------------------------------------------------------
	// Public helpers
	// ------------------------------------------------------------------

	/**
	 * GET a Graph API endpoint.
	 *
	 * @param  string $endpoint  Relative endpoint, e.g. "/me/calendars".
	 * @param  array  $query     Optional query-string parameters.
	 * @return array|WP_Error    Decoded JSON body as an associative array, or WP_Error.
	 */
	public static function get( $endpoint, array $query = array() ) {
		$url = self::build_url( $endpoint, $query );
		return self::request( 'GET', $url );
	}

	/**
	 * POST to a Graph API endpoint.
	 *
	 * @param  string $endpoint  Relative endpoint.
	 * @param  array  $body      Request body (will be JSON-encoded).
	 * @return array|WP_Error
	 */
	public static function post( $endpoint, array $body = array() ) {
		$url = self::build_url( $endpoint );
		return self::request( 'POST', $url, $body );
	}

	// ------------------------------------------------------------------
	// High-level Graph resource methods
	// ------------------------------------------------------------------

	/**
	 * Retrieve the signed-in user's profile.
	 *
	 * @return array|WP_Error
	 */
	public static function get_me() {
		return self::get( '/me' );
	}

	/**
	 * Retrieve the configured target user (UPN or object ID), if any.
	 *
	 * @return string Empty string means "use signed-in user".
	 */
	public static function get_configured_user() {
		$settings = WP_MS365_Auth::get_settings();
		return isset( $settings['specific_user'] ) ? trim( (string) $settings['specific_user'] ) : '';
	}

	/**
	 * Retrieve the effective target user's profile.
	 *
	 * @param  string $user Optional explicit user identifier.
	 * @return array|WP_Error
	 */
	public static function get_target_user_profile( $user = '' ) {
		$effective_user = self::get_effective_user( $user );
		if ( '' === $effective_user ) {
			return self::get_me();
		}

		return self::get( '/users/' . rawurlencode( $effective_user ) );
	}

	/**
	 * Retrieve upcoming calendar events.
	 *
	 * @param  int    $limit    Maximum number of events to return.
	 * @param  string $timezone IANA timezone string (default UTC).
	 * @return array|WP_Error   Array with 'value' key containing events.
	 */
	public static function get_calendar_events( $limit = 10, $timezone = 'UTC', $user = '' ) {
		$start = gmdate( 'Y-m-d\TH:i:s\Z' );
		$end   = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+30 days' ) );
		$user_prefix = self::get_user_endpoint_prefix( $user );

		return self::get(
			$user_prefix . '/calendarView',
			array(
				'startDateTime'   => $start,
				'endDateTime'     => $end,
				'$top'            => $limit,
				'$orderby'        => 'start/dateTime',
				'$select'         => 'subject,start,end,location,webLink,organizer,isAllDay',
				'Prefer'          => 'outlook.timezone="' . $timezone . '"',
			)
		);
	}

	/**
	 * Retrieve items from the user's OneDrive root.
	 *
	 * @param  string $folder Relative path inside the drive (default: root).
	 * @param  int    $limit  Maximum number of items to return.
	 * @return array|WP_Error
	 */
	public static function get_drive_items( $folder = '', $limit = 20, $user = '' ) {
		$user_prefix = self::get_user_endpoint_prefix( $user );

		if ( $folder ) {
			$endpoint = $user_prefix . '/drive/root:/' . ltrim( $folder, '/' ) . ':/children';
		} else {
			$endpoint = $user_prefix . '/drive/root/children';
		}

		return self::get(
			$endpoint,
			array(
				'$top'    => $limit,
				'$select' => 'name,size,lastModifiedDateTime,webUrl,file,folder',
				'$orderby' => 'name',
			)
		);
	}

	/**
	 * Retrieve the user's mail messages from the Inbox.
	 *
	 * @param  int $limit Maximum number of messages to return.
	 * @return array|WP_Error
	 */
	public static function get_mail_messages( $limit = 10, $user = '' ) {
		$user_prefix = self::get_user_endpoint_prefix( $user );

		return self::get(
			$user_prefix . '/mailFolders/Inbox/messages',
			array(
				'$top'     => $limit,
				'$select'  => 'subject,from,receivedDateTime,isRead,webLink',
				'$orderby' => 'receivedDateTime DESC',
			)
		);
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * Build a full API URL with optional query parameters.
	 *
	 * @param  string $endpoint Relative endpoint.
	 * @param  array  $query    Query parameters.
	 * @return string
	 */
	private static function build_url( $endpoint, array $query = array() ) {
		$url = self::API_BASE . $endpoint;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}
		return $url;
	}

	/**
	 * Execute an authenticated HTTP request, with one retry on 401.
	 *
	 * @param  string $method HTTP method.
	 * @param  string $url    Full URL.
	 * @param  array  $body   Optional request body for POST/PATCH.
	 * @return array|WP_Error
	 */
	private static function request( $method, $url, array $body = array() ) {
		$token = WP_MS365_Auth::get_access_token();
		if ( ! $token ) {
			return new WP_Error(
				'ms365_not_authenticated',
				__( 'Not connected to Microsoft 365. Please authenticate first.', 'wp-ms365-graph' )
			);
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		// On transient 401 (e.g. token just expired), refresh once and retry.
		if ( ! is_wp_error( $response ) && 401 === wp_remote_retrieve_response_code( $response ) ) {
			if ( WP_MS365_Auth::refresh_access_token() ) {
				$new_token = get_transient( 'wp_ms365_access_token' );
				if ( $new_token ) {
					$args['headers']['Authorization'] = 'Bearer ' . $new_token;
					$response = wp_remote_request( $url, $args );
				}
			}
		}

		return self::parse_response( $response );
	}

	/**
	 * Parse the HTTP response into an array or WP_Error.
	 *
	 * @param  array|WP_Error $response wp_remote_* response.
	 * @return array|WP_Error
	 */
	private static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $data ) ? $data : array();
		}

		$error_message = isset( $data['error']['message'] )
			? $data['error']['message']
			: sprintf(
				/* translators: %d = HTTP status code */
				__( 'Graph API error (HTTP %d).', 'wp-ms365-graph' ),
				$code
			);

		return new WP_Error(
			'ms365_graph_error',
			$error_message,
			array( 'status' => $code, 'body' => $body )
		);
	}

	/**
	 * Resolve which user should be targeted for user-scoped endpoints.
	 *
	 * @param  string $user Optional explicit user identifier.
	 * @return string
	 */
	private static function get_effective_user( $user = '' ) {
		$explicit_user = trim( (string) $user );
		if ( '' !== $explicit_user ) {
			return $explicit_user;
		}

		return self::get_configured_user();
	}

	/**
	 * Get endpoint prefix for either configured user or signed-in user.
	 *
	 * @param  string $user Optional explicit user identifier.
	 * @return string
	 */
	private static function get_user_endpoint_prefix( $user = '' ) {
		$effective_user = self::get_effective_user( $user );
		if ( '' === $effective_user ) {
			return '/me';
		}

		return '/users/' . rawurlencode( $effective_user );
	}
}
