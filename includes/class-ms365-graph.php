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
			WP_MS365_Logger::log( 'debug', 'Token expired (401), attempting refresh' );
			if ( WP_MS365_Auth::refresh_access_token() ) {
				$new_token = get_transient( 'wp_ms365_access_token' );
				if ( $new_token ) {
					$args['headers']['Authorization'] = 'Bearer ' . $new_token;
					$response = wp_remote_request( $url, $args );
				}
			}
		}

		// Log the API call result.
		if ( ! is_wp_error( $response ) ) {
			$status = wp_remote_retrieve_response_code( $response );
			WP_MS365_Logger::log_graph_call( $method, $url, $status );
		} else {
			WP_MS365_Logger::log( 'error', "Request failed to {$url}: " . $response->get_error_message() );
		}

		return self::parse_response( $response, $url );
	}

	/**
	 * Parse the HTTP response into an array or WP_Error.
	 *
	 * @param  array|WP_Error $response wp_remote_* response.
	 * @return array|WP_Error
	 */
	private static function parse_response( $response, $url = '' ) {
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
			? (string) $data['error']['message']
			: sprintf(
				/* translators: %d = HTTP status code */
				__( 'Graph API error (HTTP %d).', 'wp-ms365-graph' ),
				$code
			);

		$raw_message = $error_message;
		$error_code  = isset( $data['error']['code'] ) ? (string) $data['error']['code'] : '';
		$raw_message_lc = strtolower( $raw_message );
		$error_code_lc  = strtolower( $error_code );

		$is_drive_endpoint = ( strpos( $url, '/drive/' ) !== false );
		$is_calendar_endpoint = ( strpos( $url, '/calendar' ) !== false );
		$is_users_endpoint = ( strpos( $url, '/users/' ) !== false );

		$drive_not_provisioned =
			( strpos( $raw_message_lc, 'mysite' ) !== false )
			|| ( strpos( $raw_message_lc, 'unable to retrieve user\'s mysite url' ) !== false )
			|| ( strpos( $raw_message_lc, 'unable to retrieve mysite url' ) !== false )
			|| ( strpos( $raw_message_lc, 'site not found' ) !== false && $is_drive_endpoint );

		$calendar_not_provisioned =
			( strpos( $raw_message_lc, 'mailbox' ) !== false && strpos( $raw_message_lc, 'not enabled' ) !== false )
			|| ( strpos( $error_code_lc, 'mailboxnotenabledforrestapi' ) !== false )
			|| ( strpos( $raw_message_lc, 'inactive, soft-deleted, or is hosted on-premise' ) !== false );

		if ( $is_drive_endpoint && $drive_not_provisioned ) {
			$error_message = __( 'OneDrive for the selected user is not provisioned yet. Assign a license that includes OneDrive/SharePoint and let the user sign in to OneDrive once to complete provisioning.', 'wp-ms365-graph' );
		}

		if ( $is_calendar_endpoint && $calendar_not_provisioned ) {
			$error_message = __( 'Mailbox/calendar for the selected user is not provisioned yet. Assign an Exchange Online license and let the user open Outlook on the web once to finish setup.', 'wp-ms365-graph' );
		}

		$insufficient_privileges =
			( 403 === $code )
			&& (
				( strpos( $raw_message_lc, 'insufficient privileges' ) !== false )
				|| ( strpos( $raw_message_lc, 'access is denied' ) !== false )
				|| ( strpos( $error_code_lc, 'authorization_requestdenied' ) !== false )
			);

		if ( $is_users_endpoint && $insufficient_privileges ) {
			$error_message = __( 'Insufficient privileges to read the selected user profile. Add Microsoft Graph delegated permission User.ReadBasic.All and grant admin consent, then disconnect/reconnect the plugin.', 'wp-ms365-graph' );
		}

		if ( ! $drive_not_provisioned && ! $calendar_not_provisioned && ( $code === 404 || stripos( $raw_message, 'object was not found in the store' ) !== false || stripos( $error_code, 'itemnotfound' ) !== false ) ) {
			if ( $is_drive_endpoint ) {
				$error_message = __( 'OneDrive for the selected user could not be found. Verify the Specific User value (UPN or ID) and ensure the user has OneDrive provisioned.', 'wp-ms365-graph' );
			} elseif ( $is_calendar_endpoint ) {
				$error_message = __( 'Calendar for the selected user could not be found. Verify the Specific User value (UPN or ID) and ensure the user has a mailbox/calendar in Microsoft 365.', 'wp-ms365-graph' );
			} elseif ( $is_users_endpoint ) {
				$error_message = __( 'The configured Specific User could not be found. Use a valid user principal name (user@domain.com) or Entra object ID.', 'wp-ms365-graph' );
			}
		}

		if ( $code >= 400 ) {
			WP_MS365_Logger::log_graph_call( 'RESPONSE', 'parse_response', $code, $error_message );
			if ( $raw_message !== $error_message ) {
				WP_MS365_Logger::log( 'debug', 'Raw Graph error message', array( 'message' => $raw_message, 'code' => $error_code, 'url' => $url ) );
			}
		}

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
