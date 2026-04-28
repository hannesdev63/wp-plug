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
	 * @param  array  $headers   Optional request headers.
	 * @return array|WP_Error    Decoded JSON body as an associative array, or WP_Error.
	 */
	public static function get( $endpoint, array $query = array(), array $headers = array() ) {
		$url = self::build_url( $endpoint, $query );
		return self::request( 'GET', $url, array(), $headers );
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

	/**
	 * Post a message into a Microsoft Teams channel.
	 *
	 * @param  string $team_id    Microsoft Teams team ID.
	 * @param  string $channel_id Microsoft Teams channel ID.
	 * @param  string $message    Message body text.
	 * @return array|WP_Error
	 */
	public static function post_teams_channel_message( $team_id, $channel_id, $message ) {
		$team_id    = trim( (string) $team_id );
		$channel_id = trim( (string) $channel_id );
		$message    = trim( (string) $message );

		if ( '' === $team_id || '' === $channel_id ) {
			return new WP_Error( 'ms365_invalid_teams_target', __( 'Teams team ID and channel ID are required.', 'wp-ms365-graph' ) );
		}

		if ( '' === $message ) {
			return new WP_Error( 'ms365_invalid_teams_message', __( 'Message cannot be empty.', 'wp-ms365-graph' ) );
		}

		return self::post(
			'/teams/' . rawurlencode( $team_id ) . '/channels/' . rawurlencode( $channel_id ) . '/messages',
			array(
				'body' => array(
					'contentType' => 'text',
					'content'     => $message,
				),
			)
		);
	}

	/**
	 * Post a JSON payload to a Microsoft Teams Workflow endpoint URL.
	 *
	 * @param  string $endpoint_url Teams workflow endpoint URL.
	 * @param  array  $payload      Request payload.
	 * @return array|WP_Error
	 */
	public static function post_teams_workflow_message( $endpoint_url, array $payload ) {
		$endpoint_url = trim( (string) $endpoint_url );

		if ( '' === $endpoint_url || ! wp_http_validate_url( $endpoint_url ) ) {
			return new WP_Error( 'ms365_invalid_teams_endpoint', __( 'Teams workflow endpoint URL is invalid.', 'wp-ms365-graph' ) );
		}

		$response = wp_remote_post(
			$endpoint_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = trim( (string) wp_remote_retrieve_body( $response ) );

		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'ms365_teams_workflow_failed',
				sprintf( __( 'Teams workflow endpoint request failed (%d).', 'wp-ms365-graph' ), (int) $status ),
				array( 'status' => $status, 'body' => $body )
			);
		}

		return array(
			'status' => $status,
			'body'   => $body,
		);
	}

	/**
	 * Legacy wrapper for webhook delivery.
	 *
	 * @param  string $webhook_url Teams webhook URL.
	 * @param  string $message     Message body text.
	 * @return array|WP_Error
	 */
	public static function post_teams_webhook_message( $webhook_url, $message ) {
		$webhook_url = trim( (string) $webhook_url );
		$message     = trim( (string) $message );

		if ( '' === $webhook_url || ! wp_http_validate_url( $webhook_url ) ) {
			return new WP_Error( 'ms365_invalid_teams_webhook', __( 'Teams webhook URL is invalid.', 'wp-ms365-graph' ) );
		}

		if ( '' === $message ) {
			return new WP_Error( 'ms365_invalid_teams_message', __( 'Message cannot be empty.', 'wp-ms365-graph' ) );
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode(
					array(
						'text' => $message,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = trim( (string) wp_remote_retrieve_body( $response ) );

		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'ms365_teams_webhook_failed',
				sprintf( __( 'Teams webhook request failed (%d).', 'wp-ms365-graph' ), (int) $status ),
				array( 'status' => $status, 'body' => $body )
			);
		}

		return array(
			'status' => $status,
			'body'   => $body,
		);
	}

	// ------------------------------------------------------------------
	// High-level Graph resource methods
	// ------------------------------------------------------------------

	/**
	 * Retrieve the configured target user's profile.
	 *
	 * @return array|WP_Error
	 */
	public static function get_me() {
		return self::get_target_user_profile();
	}

	/**
	 * Retrieve the configured target user (UPN or object ID), if any.
	 *
	 * @return string Empty string means no target user configured.
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
			return new WP_Error(
				'ms365_missing_specific_user',
				__( 'Specific User is required for app-only mode. Set a user principal name (user@domain.com) or object ID in plugin settings.', 'wp-ms365-graph' )
			);
		}

		return self::get( '/users/' . rawurlencode( $effective_user ) );
	}

	/**
	 * Retrieve upcoming calendar events.
	 *
	 * @param  int    $limit     Maximum number of events to return.
	 * @param  string $timezone  IANA timezone string (default UTC).
	 * @param  string $user      Optional explicit user identifier.
	 * @param  int    $past_days Include events that started in this many past days.
	 * @return array|WP_Error   Array with 'value' key containing events.
	 */
	public static function get_calendar_events( $limit = 10, $timezone = 'UTC', $user = '', $past_days = 0 ) {
		// Validate timezone against PHP's known IANA list before injecting into
		// the Prefer header. An unrecognised or malformed value falls back to UTC.
		if ( '' === $timezone || ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$timezone = 'UTC';
		}

		$past_days   = max( 0, (int) $past_days );
		$start       = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-' . $past_days . ' days' ) );
		$end   = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+30 days' ) );
		$user_prefix = self::get_user_endpoint_prefix( $user );

		return self::get(
			$user_prefix . '/calendarView',
			array(
				'startDateTime'   => $start,
				'endDateTime'     => $end,
				'$top'            => $limit,
				'$orderby'        => 'start/dateTime',
				'$select'         => 'id,subject,start,end,location,webLink,organizer,isAllDay,categories,bodyPreview',
			),
			array(
				'Prefer' => 'outlook.timezone="' . $timezone . '"',
			)
		);
	}

	/**
	 * Retrieve a specific calendar event.
	 *
	 * @param  string $event_id Calendar event ID.
	 * @param  string $user     Optional explicit user identifier.
	 * @return array|WP_Error
	 */
	public static function get_calendar_event( $event_id, $user = '' ) {
		$event_id = trim( (string) $event_id );
		if ( '' === $event_id ) {
			return new WP_Error( 'ms365_invalid_event_id', __( 'Invalid calendar event ID.', 'wp-ms365-graph' ) );
		}

		$user_prefix = self::get_user_endpoint_prefix( $user );
		return self::get(
			$user_prefix . '/events/' . rawurlencode( $event_id ),
			array( '$select' => 'id,subject,start,end,location,isAllDay,bodyPreview' )
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
		$limit       = max( 1, (int) $limit );
		$user_prefix = self::get_user_endpoint_prefix( $user );
		$folder_path = trim( (string) $folder );

		if ( '' !== $folder_path ) {
			$endpoint = $user_prefix . '/drive/root:/' . ltrim( $folder_path, '/' ) . ':/children';
		} else {
			$endpoint = $user_prefix . '/drive/root/children';
		}

		// Use an oversized page to reduce round-trips: fetch up to 3× the requested
		// limit per page so folders don't eat into visible quota.
		$page_size = min( max( $limit * 3, 20 ), 999 );

		$result = self::get(
			$endpoint,
			array(
				'$top'     => $page_size,
				'$select'  => 'id,name,size,lastModifiedDateTime,webUrl,file,folder',
				'$orderby' => 'name',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$files      = array();
		$page_guard = 0;

		do {
			$raw = isset( $result['value'] ) && is_array( $result['value'] ) ? $result['value'] : array();

			foreach ( $raw as $item ) {
				if ( isset( $item['folder'] ) ) {
					continue; // skip folders
				}
				$files[] = $item;
				if ( count( $files ) >= $limit ) {
					break 2; // filled quota — stop paging
				}
			}

			$next_link = isset( $result['@odata.nextLink'] ) ? (string) $result['@odata.nextLink'] : '';
			if ( '' === $next_link ) {
				break;
			}

			$result = self::request( 'GET', $next_link );
			if ( is_wp_error( $result ) ) {
				break;
			}
			$page_guard++;
		} while ( $page_guard < 10 );

		return array( 'value' => $files );
	}

	/**
	 * Retrieve items from a SharePoint document library drive.
	 *
	 * @param  string $site_id  SharePoint site ID.
	 * @param  string $drive_id SharePoint library drive ID.
	 * @param  string $folder   Relative path inside the drive (default: root).
	 * @param  int    $limit    Maximum number of items to return.
	 * @return array|WP_Error
	 */

	/**
	 * Retrieve all SharePoint sites accessible to the app.
	 *
	 * @param  string $search Keyword to filter sites by (default: * = all).
	 * @return array|WP_Error Array with 'value' key containing site objects.
	 */
	public static function get_sharepoint_sites( $search = '*' ) {
		$search = trim( (string) $search );
		if ( '' === $search ) {
			$search = '*';
		}

		$result = self::get(
			'/sites',
			array(
				'search'  => $search,
				'$select' => 'id,displayName,name,webUrl',
			)
		);

		// Sort sites alphabetically by displayName in PHP because the Graph
		// /sites?search= endpoint does not support $orderby.
		if ( ! is_wp_error( $result ) && ! empty( $result['value'] ) ) {
			usort( $result['value'], function ( $a, $b ) {
				return strcasecmp(
					isset( $a['displayName'] ) ? $a['displayName'] : '',
					isset( $b['displayName'] ) ? $b['displayName'] : ''
				);
			} );
		}

		return $result;
	}

	/**
	 * Retrieve all document library drives for a SharePoint site.
	 *
	 * @param  string $site_id SharePoint site ID.
	 * @return array|WP_Error Array with 'value' key containing drive objects.
	 */
	public static function get_sharepoint_site_drives( $site_id ) {
		$site_id = trim( (string) $site_id );
		if ( '' === $site_id ) {
			return new WP_Error( 'ms365_invalid_site_id', __( 'SharePoint site ID is required.', 'wp-ms365-graph' ) );
		}

		$result = self::get(
			'/sites/' . rawurlencode( $site_id ) . '/drives',
			array(
				'$select' => 'id,name,driveType,webUrl',
				'$top'    => 999,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$all_drives = array();
		if ( ! empty( $result['value'] ) && is_array( $result['value'] ) ) {
			$all_drives = $result['value'];
		}

		$next_link = isset( $result['@odata.nextLink'] ) ? (string) $result['@odata.nextLink'] : '';
		$page_guard = 0;

		while ( '' !== $next_link && $page_guard < 20 ) {
			$next_page = self::request( 'GET', $next_link );
			if ( is_wp_error( $next_page ) ) {
				return $next_page;
			}

			if ( ! empty( $next_page['value'] ) && is_array( $next_page['value'] ) ) {
				$all_drives = array_merge( $all_drives, $next_page['value'] );
			}

			$next_link = isset( $next_page['@odata.nextLink'] ) ? (string) $next_page['@odata.nextLink'] : '';
			$page_guard++;
		}

		if ( ! empty( $all_drives ) ) {
			usort(
				$all_drives,
				function ( $a, $b ) {
					$a_name = isset( $a['name'] ) ? (string) $a['name'] : '';
					$b_name = isset( $b['name'] ) ? (string) $b['name'] : '';
					return strcasecmp( $a_name, $b_name );
				}
			);
		}

		$result['value'] = $all_drives;
		unset( $result['@odata.nextLink'] );

		return $result;
	}

	public static function get_sharepoint_library_items( $site_id, $drive_id, $folder = '', $limit = 20 ) {
		$site_id     = trim( (string) $site_id );
		$drive_id    = trim( (string) $drive_id );
		$folder_path = trim( (string) $folder );
		$limit       = max( 1, (int) $limit );

		if ( '' === $site_id || '' === $drive_id ) {
			return new WP_Error( 'ms365_invalid_sharepoint_context', __( 'SharePoint site ID and drive ID are required.', 'wp-ms365-graph' ) );
		}

		$site_path = '/sites/' . rawurlencode( $site_id ) . '/drives/' . rawurlencode( $drive_id );
		if ( '' !== $folder_path ) {
			$endpoint = $site_path . '/root:/' . ltrim( $folder_path, '/' ) . ':/children';
		} else {
			$endpoint = $site_path . '/root/children';
		}

		// Use an oversized page to reduce round-trips when the folder contains
		// sub-folders that would otherwise consume quota from the visible limit.
		$page_size = min( max( $limit * 3, 20 ), 999 );

		$result = self::get(
			$endpoint,
			array(
				'$top'     => $page_size,
				'$select'  => 'id,name,size,lastModifiedDateTime,webUrl,file,folder',
				'$orderby' => 'name',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$files      = array();
		$page_guard = 0;

		do {
			$raw = isset( $result['value'] ) && is_array( $result['value'] ) ? $result['value'] : array();

			foreach ( $raw as $item ) {
				if ( isset( $item['folder'] ) ) {
					continue; // skip folders
				}
				$files[] = $item;
				if ( count( $files ) >= $limit ) {
					break 2; // filled quota — stop paging
				}
			}

			$next_link = isset( $result['@odata.nextLink'] ) ? (string) $result['@odata.nextLink'] : '';
			if ( '' === $next_link ) {
				break;
			}

			$result = self::request( 'GET', $next_link );
			if ( is_wp_error( $result ) ) {
				break;
			}
			$page_guard++;
		} while ( $page_guard < 10 );

		return array( 'value' => $files );
	}

	/**
	 * Retrieve basic metadata for a OneDrive item.
	 *
	 * @param  string $item_id OneDrive item ID.
	 * @param  string $user    Optional explicit user identifier.
	 * @return array|WP_Error
	 */
	public static function get_drive_item_info( $item_id, $user = '' ) {
		$item_id = trim( (string) $item_id );
		if ( '' === $item_id ) {
			return new WP_Error( 'ms365_invalid_item', __( 'Invalid OneDrive item ID.', 'wp-ms365-graph' ) );
		}

		$user_prefix = self::get_user_endpoint_prefix( $user );
		return self::get(
			$user_prefix . '/drive/items/' . rawurlencode( $item_id ),
			array( '$select' => 'id,name,size,file' )
		);
	}

	/**
	 * Retrieve basic metadata for a SharePoint library item.
	 *
	 * @param  string $site_id  SharePoint site ID.
	 * @param  string $drive_id SharePoint library drive ID.
	 * @param  string $item_id  Drive item ID.
	 * @return array|WP_Error
	 */
	public static function get_sharepoint_library_item_info( $site_id, $drive_id, $item_id ) {
		$site_id  = trim( (string) $site_id );
		$drive_id = trim( (string) $drive_id );
		$item_id  = trim( (string) $item_id );

		if ( '' === $site_id || '' === $drive_id || '' === $item_id ) {
			return new WP_Error( 'ms365_invalid_item', __( 'Invalid SharePoint library item identifier.', 'wp-ms365-graph' ) );
		}

		return self::get(
			'/sites/' . rawurlencode( $site_id ) . '/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $item_id ),
			array( '$select' => 'id,name,size,file' )
		);
	}

	/**
	 * Retrieve the target user's default calendar metadata.
	 *
	 * Useful for diagnostics: a 200 response confirms the mailbox and calendar
	 * are provisioned and accessible.
	 *
	 * @param  string $user Optional explicit user identifier.
	 * @return array|WP_Error
	 */
	public static function get_user_calendar_info( $user = '' ) {
		$user_prefix = self::get_user_endpoint_prefix( $user );
		return self::get( $user_prefix . '/calendar', array( '$select' => 'id,name,canEdit' ) );
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

	/**
	 * Resolve a direct download URL for a OneDrive file item.
	 *
	 * @param  string $item_id OneDrive item ID.
	 * @param  string $user    Optional explicit user identifier.
	 * @return string|WP_Error Redirect target URL or WP_Error.
	 */
	public static function get_drive_item_download_url( $item_id, $user = '' ) {
		$item_id = trim( (string) $item_id );
		if ( '' === $item_id ) {
			return new WP_Error( 'ms365_invalid_item', __( 'Invalid OneDrive item ID.', 'wp-ms365-graph' ) );
		}

		$token = WP_MS365_Auth::get_access_token();
		if ( ! $token ) {
			return new WP_Error( 'ms365_not_authenticated', __( 'Not connected to Microsoft 365. Save valid tenant/client credentials to enable app-only access.', 'wp-ms365-graph' ) );
		}

		$user_prefix = self::get_user_endpoint_prefix( $user );
		$url         = self::build_url( $user_prefix . '/drive/items/' . rawurlencode( $item_id ) . '/content' );

		$args = array(
			'method'      => 'GET',
			'timeout'     => 30,
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		);

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( in_array( $code, array( 301, 302, 307, 308 ), true ) && ! empty( $location ) ) {
			return esc_url_raw( $location );
		}

		return new WP_Error(
			'ms365_download_unavailable',
			__( 'Unable to create download link for this file.', 'wp-ms365-graph' ),
			array( 'status' => $code )
		);
	}

	/**
	 * Resolve a direct download URL for a SharePoint library item.
	 *
	 * @param  string $site_id  SharePoint site ID.
	 * @param  string $drive_id SharePoint library drive ID.
	 * @param  string $item_id  Drive item ID.
	 * @return string|WP_Error Redirect target URL or WP_Error.
	 */
	public static function get_sharepoint_library_item_download_url( $site_id, $drive_id, $item_id ) {
		$site_id  = trim( (string) $site_id );
		$drive_id = trim( (string) $drive_id );
		$item_id  = trim( (string) $item_id );

		if ( '' === $site_id || '' === $drive_id || '' === $item_id ) {
			return new WP_Error( 'ms365_invalid_item', __( 'Invalid SharePoint library item identifier.', 'wp-ms365-graph' ) );
		}

		$token = WP_MS365_Auth::get_access_token();
		if ( ! $token ) {
			return new WP_Error( 'ms365_not_authenticated', __( 'Not connected to Microsoft 365. Save valid tenant/client credentials to enable app-only access.', 'wp-ms365-graph' ) );
		}

		$url = self::build_url(
			'/sites/' . rawurlencode( $site_id ) . '/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $item_id ) . '/content'
		);

		$args = array(
			'method'      => 'GET',
			'timeout'     => 30,
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		);

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( in_array( $code, array( 301, 302, 307, 308 ), true ) && ! empty( $location ) ) {
			return esc_url_raw( $location );
		}

		return new WP_Error(
			'ms365_download_unavailable',
			__( 'Unable to create download link for this file.', 'wp-ms365-graph' ),
			array( 'status' => $code )
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
	 * Fetch the raw JPEG binary of a Microsoft user's profile photo.
	 *
	 * Returns a binary string on success or WP_Error on failure (including when
	 * the user simply has no photo set on their Entra account).
	 *
	 * @param  string $user_id_or_email  UPN (email) or Azure AD object ID.
	 * @return string|WP_Error  Binary image data, or WP_Error.
	 */
	public static function get_user_photo_data( $user_id_or_email ) {
		$user_id_or_email = trim( (string) $user_id_or_email );
		if ( '' === $user_id_or_email ) {
			return new WP_Error( 'ms365_invalid_user', __( 'User identifier is required.', 'wp-ms365-graph' ) );
		}

		$token = WP_MS365_Auth::get_access_token();
		if ( ! $token ) {
			return new WP_Error(
				'ms365_not_authenticated',
				__( 'Not connected to Microsoft 365.', 'wp-ms365-graph' )
			);
		}

		$url = self::API_BASE . '/users/' . rawurlencode( $user_id_or_email ) . '/photo/$value';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 404 === $status ) {
			return new WP_Error( 'ms365_no_photo', __( 'User has no profile photo.', 'wp-ms365-graph' ) );
		}

		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'ms365_photo_fetch_failed',
				sprintf(
					/* translators: %d = HTTP status code */
					__( 'Could not fetch profile photo (HTTP %d).', 'wp-ms365-graph' ),
					(int) $status
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return new WP_Error( 'ms365_no_photo', __( 'Profile photo response was empty.', 'wp-ms365-graph' ) );
		}

		return $body;
	}

	/**
	 * Execute an authenticated HTTP request, with one retry on 401.
	 *
	 * @param  string $method HTTP method.
	 * @param  string $url    Full URL.
	 * @param  array  $body   Optional request body for POST/PATCH.
	 * @param  array  $headers Optional request headers.
	 * @return array|WP_Error
	 */
	private static function request( $method, $url, array $body = array(), array $headers = array() ) {
		$token = WP_MS365_Auth::get_access_token();
		if ( ! $token ) {
			return new WP_Error(
				'ms365_not_authenticated',
				__( 'Not connected to Microsoft 365. Save valid tenant/client credentials to enable app-only access.', 'wp-ms365-graph' )
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

		if ( ! empty( $headers ) ) {
			$args['headers'] = array_merge( $args['headers'], $headers );
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
		$is_drive_folder_children_endpoint = ( strpos( $url, '/drive/root:/' ) !== false && strpos( $url, ':/children' ) !== false );
		$is_calendar_endpoint = ( strpos( $url, '/calendar' ) !== false );
		$is_users_endpoint = ( strpos( $url, '/users/' ) !== false );

		$drive_not_provisioned =
			( strpos( $raw_message_lc, 'mysite' ) !== false )
			|| ( strpos( $raw_message_lc, 'unable to retrieve user\'s mysite url' ) !== false )
			|| ( strpos( $raw_message_lc, 'unable to retrieve mysite url' ) !== false )
			|| ( strpos( $raw_message_lc, 'site not found' ) !== false && $is_drive_endpoint )
			|| ( $is_drive_endpoint && ! $is_drive_folder_children_endpoint && strpos( $error_code_lc, 'itemnotfound' ) !== false )
			|| ( $is_drive_endpoint && ! $is_drive_folder_children_endpoint && strpos( $error_code_lc, 'erroritemnotfound' ) !== false )
			|| ( $is_drive_endpoint && ! $is_drive_folder_children_endpoint && strpos( $raw_message_lc, 'object was not found in the store' ) !== false );

		$drive_folder_not_found =
			$is_drive_folder_children_endpoint
			&& ( 404 === $code || strpos( $error_code_lc, 'itemnotfound' ) !== false || strpos( $error_code_lc, 'erroritemnotfound' ) !== false );

		if ( $drive_folder_not_found ) {
			$error_message = __( 'The requested OneDrive folder was not found for the selected user. Verify the folder path used in the shortcode (for example folder="Documents").', 'wp-ms365-graph' );
		}

		$calendar_not_provisioned =
			( strpos( $raw_message_lc, 'mailbox' ) !== false && strpos( $raw_message_lc, 'not enabled' ) !== false )
			|| ( strpos( $error_code_lc, 'mailboxnotenabledforrestapi' ) !== false )
			|| ( strpos( $raw_message_lc, 'inactive, soft-deleted, or is hosted on-premise' ) !== false )
			|| ( $is_calendar_endpoint && strpos( $error_code_lc, 'erroritemnotfound' ) !== false )
			|| ( $is_calendar_endpoint && strpos( $raw_message_lc, 'object was not found in the store' ) !== false );

		if ( $is_drive_endpoint && $drive_not_provisioned ) {
			$error_message = __( 'OneDrive for the selected user is not provisioned yet. The license may be assigned but OneDrive has not been initialized. Have the user sign into OneDrive (onedrive.live.com or the SharePoint app) once — this triggers drive creation. It may also take up to 24 hours after license assignment.', 'wp-ms365-graph' );
		}

		if ( $is_calendar_endpoint && $calendar_not_provisioned ) {
			$error_message = __( 'Mailbox/calendar for the selected user is not provisioned yet. The license is assigned but Exchange has not initialized the mailbox. Have the user sign into Outlook on the web (outlook.office.com) once — this triggers mailbox creation. It may also take up to 24 hours after license assignment.', 'wp-ms365-graph' );
		}

		$insufficient_privileges =
			( 403 === $code )
			&& (
				( strpos( $raw_message_lc, 'insufficient privileges' ) !== false )
				|| ( strpos( $raw_message_lc, 'access is denied' ) !== false )
				|| ( strpos( $error_code_lc, 'authorization_requestdenied' ) !== false )
			);

		if ( $is_users_endpoint && $insufficient_privileges ) {
			$error_message = __( 'Insufficient privileges to read the selected user profile. Add Microsoft Graph application permission User.Read.All and grant admin consent.', 'wp-ms365-graph' );
		}

		if ( ! $drive_not_provisioned && ! $calendar_not_provisioned && ! $drive_folder_not_found && ( $code === 404 || stripos( $raw_message, 'object was not found in the store' ) !== false || stripos( $error_code, 'itemnotfound' ) !== false ) ) {
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
	 * Get endpoint prefix for either explicit user or configured user.
	 *
	 * @param  string $user Optional explicit user identifier.
	 * @return string
	 */
	public static function get_user_endpoint_prefix( $user = '' ) {
		$effective_user = self::get_effective_user( $user );
		if ( '' === $effective_user ) {
			return '/me';
		}

		return '/users/' . rawurlencode( $effective_user );
	}
}
