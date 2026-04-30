<?php
/**
 * Front-end shortcodes for WP Microsoft 365 Graph.
 *
 * [msgraph_calendar]   – renders upcoming calendar events.
 * [msgraph_files]      – renders OneDrive file listing.
 * [msgraph_sharepoint_library] – renders SharePoint document library file listing.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Shortcodes {
	const RENDER_COUNTS_OPTION = 'wp_ms365_shortcode_render_counts';

	public function __construct() {
		add_shortcode( 'msgraph_calendar', array( $this, 'render_calendar' ) );
		add_shortcode( 'msgraph_files',    array( $this, 'render_files' ) );
		add_shortcode( 'msgraph_sharepoint_library', array( $this, 'render_sharepoint_library' ) );
		add_shortcode( 'msgraph_teams_message_form', array( $this, 'render_teams_message_form' ) );
		add_shortcode( 'msgraph_login_button',       array( $this, 'render_login_button' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'maybe_handle_download' ) );
		add_action( 'admin_post_wp_ms365_submit_teams_message', array( $this, 'handle_teams_message_submission' ) );
		add_action( 'admin_post_nopriv_wp_ms365_submit_teams_message', array( $this, 'handle_teams_message_submission' ) );
	}

	/**
	 * Handle public download requests for OneDrive files.
	 *
	 * @return void
	 */
	public function maybe_handle_download() {
		if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			$posted_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
			if ( 'wp_ms365_submit_teams_message' === $posted_action ) {
				$this->handle_teams_message_submission();
				return;
			}
		}

		if ( isset( $_GET['ms365_download'] ) ) {
			$this->handle_drive_download();
			return;
		}

		if ( isset( $_GET['ms365_sp_download'] ) ) {
			$this->handle_sharepoint_drive_download();
			return;
		}

		if ( isset( $_GET['ms365_calendar_ics'] ) ) {
			$this->handle_calendar_ics_download();
		}
	}

	/**
	 * Stream OneDrive file content to anonymous visitors.
	 *
	 * @return void
	 */
	private function handle_drive_download() {
		$item_id = $this->decode_local_token_param( 'ms365_download' );
		if ( '' === $item_id ) {
			wp_die( esc_html__( 'Invalid download request.', 'wp-ms365-graph' ), 400 );
		}

		$item_info = WP_MS365_Graph::get_drive_item_info( $item_id, WP_MS365_Graph::get_configured_user() );
		$filename  = 'download.bin';
		$mime_type = '';
		if ( ! is_wp_error( $item_info ) ) {
			if ( ! empty( $item_info['name'] ) ) {
				$filename = (string) $item_info['name'];
			}
			if ( ! empty( $item_info['file']['mimeType'] ) ) {
				$mime_type = (string) $item_info['file']['mimeType'];
			}
		}

		$download_url = WP_MS365_Graph::get_drive_item_download_url( $item_id, WP_MS365_Graph::get_configured_user() );
		if ( is_wp_error( $download_url ) ) {
			wp_die( esc_html( $download_url->get_error_message() ), 403 );
		}

		$item_web_url = ( ! is_wp_error( $item_info ) && ! empty( $item_info['webUrl'] ) ) ? (string) $item_info['webUrl'] : '';
		WP_MS365_WP_Access_Stats::track_external_access( 'onedrive', $item_id, $filename, $item_web_url );

		// Use a local safe filename and MIME type from Graph metadata — never
		// forward the upstream Content-Disposition to prevent header injection.
		$safe_name = sanitize_file_name( $filename );
		$safe_mime = sanitize_mime_type( $mime_type ? $mime_type : 'application/octet-stream' );

		// Stream the remote file directly to the client to avoid buffering
		// the full body in PHP memory (prevents worker/memory exhaustion).
		$context = stream_context_create(
			array(
				'http' => array(
					'timeout'        => 60,
					'ignore_errors'  => true,
				),
				'ssl' => array(
					'verify_peer'      => true,
					'verify_peer_name' => true,
				),
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$handle = @fopen( $download_url, 'rb', false, $context );
		if ( false === $handle ) {
			wp_die( esc_html__( 'Unable to download this file right now.', 'wp-ms365-graph' ), 502 );
		}

		nocache_headers();
		header( 'Content-Type: ' . $safe_mime );
		header(
			'Content-Disposition: attachment; filename="' . str_replace( '"', '', $safe_name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $filename )
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fpassthru
		fpassthru( $handle );
		fclose( $handle );
		exit;
	}

	/**
	 * Stream SharePoint library file content to anonymous visitors.
	 *
	 * @return void
	 */
	private function handle_sharepoint_drive_download() {
		$raw_context = $this->decode_local_token_param( 'ms365_sp_download' );
		$context     = json_decode( (string) $raw_context, true );

		if ( ! is_array( $context ) || empty( $context['site_id'] ) || empty( $context['drive_id'] ) || empty( $context['item_id'] ) ) {
			wp_die( esc_html__( 'Invalid download request.', 'wp-ms365-graph' ), 400 );
		}

		$site_id  = trim( (string) $context['site_id'] );
		$drive_id = trim( (string) $context['drive_id'] );
		$item_id  = trim( (string) $context['item_id'] );

		$item_info = WP_MS365_Graph::get_sharepoint_library_item_info( $site_id, $drive_id, $item_id );
		$filename  = 'download.bin';
		$mime_type = '';
		if ( ! is_wp_error( $item_info ) ) {
			if ( ! empty( $item_info['name'] ) ) {
				$filename = (string) $item_info['name'];
			}
			if ( ! empty( $item_info['file']['mimeType'] ) ) {
				$mime_type = (string) $item_info['file']['mimeType'];
			}
		}

		$download_url = WP_MS365_Graph::get_sharepoint_library_item_download_url( $site_id, $drive_id, $item_id );
		if ( is_wp_error( $download_url ) ) {
			wp_die( esc_html( $download_url->get_error_message() ), 403 );
		}

		$item_web_url = ( ! is_wp_error( $item_info ) && ! empty( $item_info['webUrl'] ) ) ? (string) $item_info['webUrl'] : '';
		WP_MS365_WP_Access_Stats::track_external_access( 'sharepoint', $site_id . ':' . $drive_id . ':' . $item_id, $filename, $item_web_url );

		// Use a local safe filename and MIME type from Graph metadata — never
		// forward the upstream Content-Disposition to prevent header injection.
		$safe_name = sanitize_file_name( $filename );
		$safe_mime = sanitize_mime_type( $mime_type ? $mime_type : 'application/octet-stream' );

		// Stream the remote file directly to the client to avoid buffering
		// the full body in PHP memory (prevents worker/memory exhaustion).
		$context = stream_context_create(
			array(
				'http' => array(
					'timeout'        => 60,
					'ignore_errors'  => true,
				),
				'ssl' => array(
					'verify_peer'      => true,
					'verify_peer_name' => true,
				),
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$handle = @fopen( $download_url, 'rb', false, $context );
		if ( false === $handle ) {
			wp_die( esc_html__( 'Unable to download this file right now.', 'wp-ms365-graph' ), 502 );
		}

		nocache_headers();
		header( 'Content-Type: ' . $safe_mime );
		header(
			'Content-Disposition: attachment; filename="' . str_replace( '"', '', $safe_name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $filename )
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fpassthru
		fpassthru( $handle );
		fclose( $handle );
		exit;
	}

	/**
	 * Export a calendar event as ICS for anonymous visitors.
	 *
	 * @return void
	 */
	private function handle_calendar_ics_download() {
		$event_id = $this->decode_local_token_param( 'ms365_calendar_ics' );
		if ( '' === $event_id ) {
			wp_die( esc_html__( 'Invalid calendar export request.', 'wp-ms365-graph' ), 400 );
		}

		$event = WP_MS365_Graph::get_calendar_event( $event_id, WP_MS365_Graph::get_configured_user() );
		if ( is_wp_error( $event ) ) {
			wp_die( esc_html( $event->get_error_message() ), 403 );
		}

		$ics      = $this->build_ics_content( $event );
		$subject  = isset( $event['subject'] ) ? (string) $event['subject'] : 'event';
		$filename = sanitize_file_name( $subject ) . '.ics';

		$event_url = isset( $event['webLink'] ) ? (string) $event['webLink'] : '';
		WP_MS365_WP_Access_Stats::track_external_access( 'outlook', $event_id, $subject, $event_url );

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $ics;
		exit;
	}

	/**
	 * Build ICS content from a Graph event payload.
	 *
	 * @param  array $event Graph event payload.
	 * @return string
	 */
	private function build_ics_content( array $event ) {
		$subject  = isset( $event['subject'] ) ? $this->escape_ics_text( (string) $event['subject'] ) : 'Event';
		$location = isset( $event['location']['displayName'] ) ? $this->escape_ics_text( (string) $event['location']['displayName'] ) : '';
		$preview  = isset( $event['bodyPreview'] ) ? $this->escape_ics_text( (string) $event['bodyPreview'] ) : '';
		$uid      = isset( $event['id'] ) ? $this->escape_ics_text( (string) $event['id'] ) . '@wp-ms365-graph' : wp_generate_uuid4() . '@wp-ms365-graph';

		$start_dt = isset( $event['start']['dateTime'] ) ? (string) $event['start']['dateTime'] : '';
		$end_dt   = isset( $event['end']['dateTime'] ) ? (string) $event['end']['dateTime'] : '';
		$is_all_day = ! empty( $event['isAllDay'] );

		if ( $is_all_day ) {
			$dtstart = 'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $start_dt ) );
			$dtend   = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $end_dt ) );
		} else {
			$dtstart = 'DTSTART:' . gmdate( 'Ymd\THis\Z', strtotime( $start_dt ) );
			$dtend   = 'DTEND:' . gmdate( 'Ymd\THis\Z', strtotime( $end_dt ) );
		}

		$lines   = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//WP Microsoft 365 Graph//EN',
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			$dtstart,
			$dtend,
			'SUMMARY:' . $subject,
		);

		if ( '' !== $location ) {
			$lines[] = 'LOCATION:' . $location;
		}

		if ( '' !== $preview ) {
			$lines[] = 'DESCRIPTION:' . $preview;
		}

		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Escape text for ICS output.
	 *
	 * @param  string $text Raw text.
	 * @return string
	 */
	private function escape_ics_text( $text ) {
		$text = str_replace( array( '\\', ';', ',', "\r\n", "\n", "\r" ), array( '\\\\', '\\;', '\\,', '\\n', '\\n', '\\n' ), (string) $text );
		return trim( $text );
	}

	/**
	 * Encode a local query param payload as a signed, expiring token.
	 *
	 * Format: base64url(json({id, exp})).base64url(hmac-sha256)
	 * Expiry is 1 hour from generation.
	 *
	 * @param  string $value Raw identifier.
	 * @return string
	 */
	private function encode_local_token_param( $value ) {
		$payload = $this->base64url_encode(
			wp_json_encode(
				array(
					'id'  => (string) $value,
					'exp' => time() + HOUR_IN_SECONDS,
				)
			)
		);
		$sig = $this->base64url_encode(
			hash_hmac( 'sha256', $payload, wp_salt( 'auth' ), true )
		);
		return $payload . '.' . $sig;
	}

	/**
	 * Decode and validate a signed expiring token from a query parameter.
	 *
	 * @param  string $key Query parameter key.
	 * @return string Decoded identifier, or empty string on any failure.
	 */
	private function decode_local_token_param( $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return '';
		}

		$token = trim( (string) wp_unslash( $_GET[ $key ] ) );
		if ( '' === $token ) {
			return '';
		}

		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return '';
		}

		list( $payload_b64, $sig_b64 ) = $parts;

		// Constant-time HMAC verification.
		$expected = $this->base64url_encode(
			hash_hmac( 'sha256', $payload_b64, wp_salt( 'auth' ), true )
		);
		if ( ! hash_equals( $expected, $sig_b64 ) ) {
			return '';
		}

		$json = $this->base64url_decode( $payload_b64 );
		if ( false === $json ) {
			return '';
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) || ! isset( $payload['id'], $payload['exp'] ) ) {
			return '';
		}

		if ( (int) $payload['exp'] < time() ) {
			wp_die( esc_html__( 'This link has expired. Please reload the page to get a new one.', 'wp-ms365-graph' ), 410 );
		}

		return (string) $payload['id'];
	}

	/**
	 * Base64url-encode binary data.
	 *
	 * @param  string $data Raw bytes.
	 * @return string
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url-decode a string.
	 *
	 * @param  string $data Encoded string.
	 * @return string|false Decoded string, or false on failure.
	 */
	private function base64url_decode( $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ), true );
	}

	// ------------------------------------------------------------------
	// Shortcode: [msgraph_login_button]
	// ------------------------------------------------------------------

	/**
	 * Render a "Sign in with Microsoft" button for use on custom login pages.
	 *
	 * Attributes:
	 *   redirect_to  – Internal URL to redirect to after sign-in. Defaults to home.
	 *   label        – Button label. Defaults to translated "Sign in with Microsoft".
	 *   class        – Extra CSS class(es) added to the anchor.
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string      HTML output.
	 */
	public function render_login_button( $atts ) {
		$this->track_shortcode_render( 'msgraph_login_button' );
		$settings = WP_MS365_Auth::get_settings();
		if ( empty( $settings['sso_enabled'] ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'redirect_to' => '',
				'label'       => '',
				'class'       => '',
			),
			$atts,
			'msgraph_login_button'
		);

		if ( '' !== $atts['label'] ) {
			$label = esc_html( $atts['label'] );
		} elseif ( ! empty( $settings['sso_signin_button_text'] ) ) {
			$label = esc_html( (string) $settings['sso_signin_button_text'] );
		} else {
			$label = esc_html__( 'Sign in with Microsoft', 'wp-ms365-graph' );
		}
		$redirect_to = esc_url_raw( (string) $atts['redirect_to'] );
		$extra_class = '' !== $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '';

		$login_url = WP_MS365_Auth::get_sso_login_url( $redirect_to );
		if ( ! $login_url ) {
			return '';
		}

		wp_enqueue_style(
			'wp-ms365-login',
			WP_MS365_PLUGIN_URL . 'assets/css/login.css',
			array(),
			WP_MS365_VERSION
		);

		ob_start();
		$button_image_url = ! empty( $settings['sso_signin_button_image'] ) ? esc_url( $settings['sso_signin_button_image'] ) : '';
		?>
		<div class="ms365-signin-wrap">
			<a href="<?php echo esc_url( $login_url ); ?>" class="ms365-signin-button<?php echo esc_attr( $extra_class ); ?>">
				<?php if ( $button_image_url ) : ?>
				<img src="<?php echo $button_image_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped above. ?>" alt="" width="20" height="20" aria-hidden="true" class="ms365-signin-button__icon" />
				<?php else : ?>
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23" width="20" height="20" aria-hidden="true" focusable="false">
					<rect x="1"  y="1"  width="10" height="10" fill="#F25022"/>
					<rect x="12" y="1"  width="10" height="10" fill="#7FBA00"/>
					<rect x="1"  y="12" width="10" height="10" fill="#00A4EF"/>
					<rect x="12" y="12" width="10" height="10" fill="#FFB900"/>
				</svg>
				<?php endif; ?>
				<span><?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped above. ?></span>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Shortcode: [msgraph_calendar]
	// ------------------------------------------------------------------

	/**
	 * Render upcoming Microsoft 365 calendar events.
	 *
	 * Attributes:
	 *   limit    – max number of events (default 5)
	 *   timezone – IANA timezone string (default WP site timezone)
	 *   title    – heading text (default "Upcoming Events")
	 *   past_days – include events that ended in the last N days (default 0)
	 *   columns – comma-separated list of columns to show (default: all).
	 *             Supported values: date, event, duration, location.
	 *             Example: columns="date,event"
	 *   duration_display – duration column mode: "hours_minutes" or "start_end" (default "hours_minutes")
	 *   group_by_date – group events by start date (default false)
	 *   categories – comma-separated category names to include (default: all)
	 *   show_headers – whether to render table headers (default true)
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_calendar( $atts ) {
		$this->track_shortcode_render( 'msgraph_calendar' );

		$default_timezone = $this->get_default_calendar_timezone();
		$wording          = $this->get_shortcode_wording();

		$atts = shortcode_atts(
			array(
				'limit'              => 5,
				'timezone'           => $default_timezone,
				'title'              => '',
				'class'              => '',
				'table_class'        => '',
				'item_class'         => '',
				'past_days'          => 0,
				'columns'            => '',
				'duration_display'   => 'hours_minutes',
				'group_by_date'      => 'false',
				'categories'         => '',
				'show_headers'       => 'true',
				'template'           => '',
			),
			$atts,
			'msgraph_calendar'
		);

			$show_headers = $this->shortcode_att_to_bool( $atts['show_headers'], true );
		$calendar_wrap_class  = $this->merge_css_classes( 'msgraph_calendar', $atts['class'] );
		$calendar_table_class = $this->merge_css_classes( 'msgraph_table msgraph_calendar__table', $atts['table_class'] );
		$calendar_item_class  = $this->merge_css_classes( 'msgraph_calendar__item', $atts['item_class'] );

		$available_columns = array(
			'date'     => $wording['calendar_header_date'],
			'event'    => $wording['calendar_header_event'],
			'duration' => $wording['calendar_header_duration'],
			'location' => $wording['calendar_header_location'],
		);
		$all_day_label = isset( $wording['calendar_all_day_text'] ) ? (string) $wording['calendar_all_day_text'] : __( 'All day', 'wp-ms365-graph' );

		$requested_columns = array_filter(
			array_map( 'trim', explode( ',', strtolower( (string) $atts['columns'] ) ) ),
			function ( $column ) {
				return '' !== $column;
			}
		);

		if ( empty( $requested_columns ) ) {
			$active_columns = array_keys( $available_columns );
		} else {
			$active_columns = array();
			foreach ( $requested_columns as $column ) {
				if ( isset( $available_columns[ $column ] ) && ! in_array( $column, $active_columns, true ) ) {
					$active_columns[] = $column;
				}
			}

			if ( empty( $active_columns ) ) {
				$active_columns = array_keys( $available_columns );
			}
		}

		$duration_display_mode = strtolower( trim( (string) $atts['duration_display'] ) );
		if ( ! in_array( $duration_display_mode, array( 'hours_minutes', 'start_end' ), true ) ) {
			$duration_display_mode = 'hours_minutes';
		}

		$group_by_date = $this->shortcode_att_to_bool( $atts['group_by_date'], false );

		$category_filter = array_filter(
			array_map( 'trim', explode( ',', (string) $atts['categories'] ) ),
			function ( $category ) {
				return '' !== $category;
			}
		);
		$category_filter = array_map( 'strtolower', $category_filter );

		if ( ! WP_MS365_Auth::is_connected() ) {
			return $this->not_connected_notice();
		}

		$limit           = max( 1, (int) $atts['limit'] );
		$past_days       = max( 0, (int) $atts['past_days'] );
		$configured_user = WP_MS365_Graph::get_configured_user();

		$calendar_cache_key = $this->get_shortcode_cache_key(
			'calendar',
			array(
				'limit'      => $limit,
				'timezone'   => (string) $atts['timezone'],
				'past_days'  => $past_days,
				'categories' => $category_filter,
				'user'       => $configured_user,
			)
		);

		$cache_busted = $this->is_cache_busted();
		if ( $cache_busted ) {
			delete_transient( $calendar_cache_key );
		}

		$items = $cache_busted ? false : get_transient( $calendar_cache_key );
		if ( false === $items || ! is_array( $items ) ) {
			// Fast-first-paint strategy: fetch a smaller horizon and stop early
			// once enough rows are gathered for the requested output limit.
			$query_limit_multiplier = empty( $category_filter ) ? 2 : 4;
			$query_limit_max        = empty( $category_filter ) ? 60 : 120;
			$query_limit            = min( max( $limit * $query_limit_multiplier, 12 ), $query_limit_max );
			$query_past_days        = max( $past_days, 14 );

			$events = WP_MS365_Graph::get_calendar_events( $query_limit, $atts['timezone'], '', $query_past_days );
			if ( is_wp_error( $events ) ) {
				return $this->error_notice( $events->get_error_message() );
			}

			$raw_items           = isset( $events['value'] ) && is_array( $events['value'] ) ? $events['value'] : array();
			$threshold_timestamp = time() - ( $past_days * DAY_IN_SECONDS );
			$items               = array();

			foreach ( $raw_items as $event ) {
				$event_timezone = isset( $event['start']['timeZone'] ) ? (string) $event['start']['timeZone'] : (string) $atts['timezone'];
				$end_raw        = isset( $event['end']['dateTime'] ) ? (string) $event['end']['dateTime'] : '';
				$end_ts         = $this->parse_graph_datetime_to_timestamp( $end_raw, $event_timezone );

				if ( false === $end_ts ) {
					continue;
				}

				// Upcoming means event has not finished yet. When past_days > 0,
				// include recently finished events within that lookback window.
				if ( $end_ts < $threshold_timestamp ) {
					continue;
				}

				if ( ! empty( $category_filter ) ) {
					if ( empty( $event['categories'] ) || ! is_array( $event['categories'] ) ) {
						continue;
					}

					$event_categories = array_map( 'strtolower', array_map( 'trim', $event['categories'] ) );
					if ( 0 === count( array_intersect( $category_filter, $event_categories ) ) ) {
						continue;
					}
				}

				$items[] = $event;
				if ( count( $items ) >= $limit ) {
					break;
				}
			}

			set_transient( $calendar_cache_key, $items, $this->get_shortcode_cache_ttl() );
		}

		// Build per-item context for custom templates.
		$template_items = array();
		foreach ( $items as $event ) {
			$ev_subject  = isset( $event['subject'] ) ? (string) $event['subject'] : '';
			$ev_tz       = isset( $event['start']['timeZone'] ) ? (string) $event['start']['timeZone'] : (string) $atts['timezone'];
			$ev_start    = isset( $event['start']['dateTime'] ) ? (string) $event['start']['dateTime'] : '';
			$ev_end      = isset( $event['end']['dateTime'] ) ? (string) $event['end']['dateTime'] : '';
			$ev_location = isset( $event['location']['displayName'] ) ? (string) $event['location']['displayName'] : '';
			$ev_desc     = $this->get_event_description_for_display( $event );
			$ev_all_day  = ! empty( $event['isAllDay'] );
			$ev_date_display = $ev_start ? $this->format_event_datetime_for_display( $ev_start, $ev_tz, $ev_all_day ) : '';
			$ev_duration     = 'start_end' === $duration_display_mode
				? $this->format_event_time_range_for_display( $ev_start, $ev_end, $ev_tz, $ev_all_day, $all_day_label )
				: $this->format_event_duration_for_display( $ev_start, $ev_end, $ev_all_day, $ev_tz );
			$ev_categories   = isset( $event['categories'] ) && is_array( $event['categories'] )
				? implode( ', ', $event['categories'] )
				: '';
			$template_items[] = array(
				'subject'     => $ev_subject,
				'date'        => $ev_date_display,
				'duration'    => $ev_duration,
				'location'    => $ev_location,
				'description' => $ev_desc,
				'is_all_day'  => $ev_all_day ? 'true' : 'false',
				'start_raw'   => $ev_start,
				'end_raw'     => $ev_end,
				'categories'  => $ev_categories,
			);
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $calendar_wrap_class ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="msgraph_calendar__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<p class="msgraph_calendar__empty"><?php echo esc_html( $wording['calendar_empty_text'] ); ?></p>
			<?php else : ?>
				<?php
				// When group_by_date is true, pre-build groups keyed by Y-m-d.
				$grouped_rows = array();
				if ( $group_by_date ) {
					foreach ( $items as $event ) {
						$start_raw      = isset( $event['start']['dateTime'] ) ? (string) $event['start']['dateTime'] : '';
						$event_timezone = isset( $event['start']['timeZone'] ) ? (string) $event['start']['timeZone'] : (string) $atts['timezone'];
						$start_ts       = $this->parse_graph_datetime_to_timestamp( $start_raw, $event_timezone );

						if ( false === $start_ts ) {
							$group_key   = 'unknown';
							$group_label = '';
						} else {
							$tz_name     = $this->normalize_graph_timezone( $event_timezone );
							$group_key   = wp_date( 'Y-m-d', $start_ts, new DateTimeZone( $tz_name ) );
							$group_label = wp_date( get_option( 'date_format' ), $start_ts, new DateTimeZone( $tz_name ) );
						}

						if ( ! isset( $grouped_rows[ $group_key ] ) ) {
							$grouped_rows[ $group_key ] = array(
								'date_label' => $group_label,
								'events'     => array(),
							);
						}

						$grouped_rows[ $group_key ]['events'][] = $event;
					}
				}
				$col_count = count( $active_columns );
				?>
				<table class="<?php echo esc_attr( $calendar_table_class ); ?>">
					<?php if ( $show_headers && ! $group_by_date ) : ?>
						<thead>
							<tr>
								<?php foreach ( $active_columns as $column_key ) : ?>
									<th scope="col"><?php echo esc_html( $available_columns[ $column_key ] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
					<?php endif; ?>
					<tbody>
						<?php if ( $group_by_date ) : ?>
							<?php foreach ( $grouped_rows as $group_key => $group ) : ?>
								<?php
								$group_events = isset( $group['events'] ) && is_array( $group['events'] ) ? $group['events'] : array();
								if ( empty( $group_events ) ) {
									continue;
								}
								?>
								<tr class="msgraph_calendar__group-heading">
									<th class="msgraph_calendar__group-date" colspan="<?php echo esc_attr( $col_count ); ?>" scope="rowgroup">
										<?php echo esc_html( isset( $group['date_label'] ) ? (string) $group['date_label'] : $group_key ); ?>
									</th>
								</tr>
								<?php foreach ( $group_events as $event ) : ?>
									<?php
									$subject        = isset( $event['subject'] ) ? $event['subject'] : __( '(No subject)', 'wp-ms365-graph' );
									$start          = isset( $event['start']['dateTime'] ) ? $event['start']['dateTime'] : '';
									$end            = isset( $event['end']['dateTime'] ) ? $event['end']['dateTime'] : '';
									$location       = isset( $event['location']['displayName'] ) ? $event['location']['displayName'] : '';
									$description    = $this->get_event_description_for_display( $event );
									$event_timezone = isset( $event['start']['timeZone'] ) ? (string) $event['start']['timeZone'] : (string) $atts['timezone'];
									$all_day          = ! empty( $event['isAllDay'] );
									$start_display    = $start
										? ( $all_day
											? $this->format_event_datetime_for_display( $start, $event_timezone, true )
											: wp_date( get_option( 'time_format' ), $this->parse_graph_datetime_to_timestamp( $start, $event_timezone ), new DateTimeZone( $this->normalize_graph_timezone( $event_timezone ) ) ) )
										: '';
									$duration_display = 'start_end' === $duration_display_mode
										? $this->format_event_time_range_for_display( $start, $end, $event_timezone, $all_day, $all_day_label )
										: $this->format_event_duration_for_display( $start, $end, $all_day, $event_timezone );
									?>
									<tr class="<?php echo esc_attr( $calendar_item_class ); ?>">
										<?php foreach ( $active_columns as $column_key ) : ?>
											<?php if ( 'date' === $column_key ) : ?>
												<td class="msgraph_calendar__date" data-label="<?php echo esc_attr( $available_columns['date'] ); ?>"><?php echo esc_html( $start_display ); ?></td>
											<?php elseif ( 'event' === $column_key ) : ?>
												<td class="msgraph_calendar__subject" data-label="<?php echo esc_attr( $available_columns['event'] ); ?>">
													<?php if ( '' !== $description ) : ?>
														<details class="msgraph_calendar__event-details">
															<summary class="msgraph_calendar__event-summary"><?php echo esc_html( $subject ); ?></summary>
															<div class="msgraph_calendar__event-description"><?php echo esc_html( $description ); ?></div>
														</details>
													<?php else : ?>
														<?php echo esc_html( $subject ); ?>
													<?php endif; ?>
												</td>
											<?php elseif ( 'duration' === $column_key ) : ?>
												<td class="msgraph_calendar__duration" data-label="<?php echo esc_attr( $available_columns['duration'] ); ?>"><?php echo esc_html( $duration_display ); ?></td>
											<?php elseif ( 'location' === $column_key ) : ?>
												<td class="msgraph_calendar__location" data-label="<?php echo esc_attr( $available_columns['location'] ); ?>"><?php echo esc_html( $location ); ?></td>
											<?php endif; ?>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							<?php endforeach; ?>
						<?php else : ?>
							<?php foreach ( $items as $event ) : ?>
								<?php
								$subject  = isset( $event['subject'] ) ? $event['subject'] : __( '(No subject)', 'wp-ms365-graph' );
								$start    = isset( $event['start']['dateTime'] ) ? $event['start']['dateTime'] : '';
								$end      = isset( $event['end']['dateTime'] ) ? $event['end']['dateTime'] : '';
								$location = isset( $event['location']['displayName'] ) ? $event['location']['displayName'] : '';
								$description = $this->get_event_description_for_display( $event );
								$event_timezone = isset( $event['start']['timeZone'] ) ? (string) $event['start']['timeZone'] : (string) $atts['timezone'];
								$all_day  = ! empty( $event['isAllDay'] );
								$start_display = $start
									? ( $all_day
										? $this->format_event_datetime_for_display( $start, $event_timezone, true )
										: $this->format_event_datetime_for_display( $start, $event_timezone, false ) )
									: '';
								$duration_display = 'start_end' === $duration_display_mode
									? $this->format_event_time_range_for_display( $start, $end, $event_timezone, $all_day, $all_day_label )
									: $this->format_event_duration_for_display( $start, $end, $all_day, $event_timezone );
								?>
								<tr class="<?php echo esc_attr( $calendar_item_class ); ?>">
									<?php foreach ( $active_columns as $column_key ) : ?>
										<?php if ( 'date' === $column_key ) : ?>
											<td class="msgraph_calendar__date" data-label="<?php echo esc_attr( $available_columns['date'] ); ?>"><?php echo esc_html( $start_display ); ?></td>
										<?php elseif ( 'event' === $column_key ) : ?>
											<td class="msgraph_calendar__subject" data-label="<?php echo esc_attr( $available_columns['event'] ); ?>">
												<?php if ( '' !== $description ) : ?>
													<details class="msgraph_calendar__event-details">
														<summary class="msgraph_calendar__event-summary"><?php echo esc_html( $subject ); ?></summary>
														<div class="msgraph_calendar__event-description"><?php echo esc_html( $description ); ?></div>
													</details>
												<?php else : ?>
													<?php echo esc_html( $subject ); ?>
												<?php endif; ?>
											</td>
										<?php elseif ( 'duration' === $column_key ) : ?>
											<td class="msgraph_calendar__duration" data-label="<?php echo esc_attr( $available_columns['duration'] ); ?>"><?php echo esc_html( $duration_display ); ?></td>
										<?php elseif ( 'location' === $column_key ) : ?>
											<td class="msgraph_calendar__location" data-label="<?php echo esc_attr( $available_columns['location'] ); ?>"><?php echo esc_html( $location ); ?></td>
										<?php endif; ?>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		$default_html = ob_get_clean();
		return $this->maybe_render_custom_template(
			'calendar',
			$default_html,
			array(
				'title'               => (string) $atts['title'],
				'item_count'          => count( $items ),
				'active_column_count' => count( $active_columns ),
				'show_headers'        => $show_headers,
				'items'               => $template_items,
			),
			sanitize_key( (string) $atts['template'] )
		);
	}

	/**
	 * Get a plain-text event description for inline display.
	 *
	 * @param  array $event Graph event payload.
	 * @return string
	 */
	private function get_event_description_for_display( array $event ) {
		if ( isset( $event['bodyPreview'] ) && '' !== trim( (string) $event['bodyPreview'] ) ) {
			return trim( (string) $event['bodyPreview'] );
		}

		if ( isset( $event['body']['content'] ) && '' !== trim( (string) $event['body']['content'] ) ) {
			return trim( wp_strip_all_tags( (string) $event['body']['content'] ) );
		}

		return '';
	}

	/**
	 * Format a Graph event date/time for site display.
	 *
	 * @param  string $date_time Graph date/time string.
	 * @param  string $source_timezone Graph timezone (IANA or common Windows value).
	 * @param  bool   $all_day Whether this is an all-day event.
	 * @return string
	 */
	private function format_event_datetime_for_display( $date_time, $source_timezone, $all_day = false ) {
		$source_tz_name = $this->normalize_graph_timezone( (string) $source_timezone );
		$display_tz     = new DateTimeZone( $source_tz_name );
		$format         = $all_day ? get_option( 'date_format' ) : get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$timestamp      = $this->parse_graph_datetime_to_timestamp( $date_time, $source_timezone );

		if ( false === $timestamp ) {
			return '';
		}

		return wp_date( $format, $timestamp, $display_tz );
	}

	/**
	 * Format an event duration for display.
	 *
	 * @param  string $start_date_time Event start date/time.
	 * @param  string $end_date_time Event end date/time.
	 * @param  bool   $all_day Whether this is an all-day event.
	 * @param  string $source_timezone Graph timezone (IANA or common Windows value).
	 * @return string
	 */
	private function format_event_duration_for_display( $start_date_time, $end_date_time, $all_day = false, $source_timezone = '' ) {
		$start_ts = $this->parse_graph_datetime_to_timestamp( $start_date_time, $source_timezone );
		$end_ts   = $this->parse_graph_datetime_to_timestamp( $end_date_time, $source_timezone );

		if ( false === $start_ts || false === $end_ts || $end_ts <= $start_ts ) {
			return '';
		}

		$seconds = (int) ( $end_ts - $start_ts );

		if ( $all_day ) {
			$days = max( 1, (int) round( $seconds / DAY_IN_SECONDS ) );
			return sprintf(
				/* translators: %d: number of days */
				_n( '%d day', '%d days', $days, 'wp-ms365-graph' ),
				$days
			);
		}

		$hours   = (int) floor( $seconds / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

		if ( $hours > 0 && $minutes > 0 ) {
			$hours_part = sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour', '%d hours', $hours, 'wp-ms365-graph' ),
				$hours
			);
			$minutes_part = sprintf(
				/* translators: %d: number of minutes */
				_n( '%d minute', '%d minutes', $minutes, 'wp-ms365-graph' ),
				$minutes
			);

			return $hours_part . ' ' . $minutes_part;
		}

		if ( $hours > 0 ) {
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour', '%d hours', $hours, 'wp-ms365-graph' ),
				$hours
			);
		}

		$minutes = max( 1, $minutes );
		return sprintf(
			/* translators: %d: number of minutes */
			_n( '%d minute', '%d minutes', $minutes, 'wp-ms365-graph' ),
			$minutes
		);
	}

	/**
	 * Format an event start/end time range for display.
	 *
	 * @param  string $start_date_time Event start date/time.
	 * @param  string $end_date_time Event end date/time.
	 * @param  string $source_timezone Graph timezone (IANA or common Windows value).
	 * @param  bool   $all_day Whether this is an all-day event.
	 * @param  string $all_day_label Localized all-day label.
	 * @return string
	 */
	private function format_event_time_range_for_display( $start_date_time, $end_date_time, $source_timezone, $all_day = false, $all_day_label = '' ) {
		if ( $all_day ) {
			if ( '' !== trim( (string) $all_day_label ) ) {
				return (string) $all_day_label;
			}
			return __( 'All day', 'wp-ms365-graph' );
		}

		$start_ts = $this->parse_graph_datetime_to_timestamp( $start_date_time, $source_timezone );
		$end_ts   = $this->parse_graph_datetime_to_timestamp( $end_date_time, $source_timezone );
		if ( false === $start_ts || false === $end_ts || $end_ts <= $start_ts ) {
			return '';
		}

		$source_tz_name = $this->normalize_graph_timezone( (string) $source_timezone );
		$display_tz     = new DateTimeZone( $source_tz_name );
		$time_format    = get_option( 'time_format' );

		$start_display = wp_date( $time_format, $start_ts, $display_tz );
		$end_display   = wp_date( $time_format, $end_ts, $display_tz );

		return $start_display . ' - ' . $end_display;
	}

	/**
	 * Format an event start/end time range in 24-hour HH:MM format.
	 *
	 * @param  string $start_date_time Event start date/time.
	 * @param  string $end_date_time Event end date/time.
	 * @param  string $source_timezone Graph timezone (IANA or common Windows value).
	 * @param  bool   $all_day Whether this is an all-day event.
	 * @param  string $all_day_label Localized all-day label.
	 * @return string
	 */
	private function format_event_time_range_hm_for_display( $start_date_time, $end_date_time, $source_timezone, $all_day = false, $all_day_label = '' ) {
		if ( $all_day ) {
			if ( '' !== trim( (string) $all_day_label ) ) {
				return (string) $all_day_label;
			}
			return __( 'All day', 'wp-ms365-graph' );
		}

		$start_ts = $this->parse_graph_datetime_to_timestamp( $start_date_time, $source_timezone );
		$end_ts   = $this->parse_graph_datetime_to_timestamp( $end_date_time, $source_timezone );
		if ( false === $start_ts || false === $end_ts || $end_ts <= $start_ts ) {
			return '';
		}

		$source_tz_name = $this->normalize_graph_timezone( (string) $source_timezone );
		$display_tz     = new DateTimeZone( $source_tz_name );

		$start_display = wp_date( 'H:i', $start_ts, $display_tz );
		$end_display   = wp_date( 'H:i', $end_ts, $display_tz );

		return $start_display . ' - ' . $end_display;
	}

	/**
	 * Parse a Graph dateTime string into a Unix timestamp using event timezone.
	 *
	 * @param  string $date_time Graph date/time string.
	 * @param  string $source_timezone Graph timezone (IANA or common Windows value).
	 * @return int|false
	 */
	private function parse_graph_datetime_to_timestamp( $date_time, $source_timezone = '' ) {
		$date_time = (string) $date_time;
		if ( '' === trim( $date_time ) ) {
			return false;
		}

		$source_tz_name = $this->normalize_graph_timezone( (string) $source_timezone );
		$source_tz      = new DateTimeZone( $source_tz_name );

		try {
			if ( preg_match( '/(Z|[+\-]\d{2}:\d{2})$/', $date_time ) ) {
				$dt = new DateTimeImmutable( $date_time );
			} else {
				$dt = new DateTimeImmutable( $date_time, $source_tz );
			}
			return $dt->getTimestamp();
		} catch ( Exception $e ) {
			$timestamp = strtotime( $date_time );
			return false === $timestamp ? false : $timestamp;
		}
	}

	/**
	 * Normalize Graph timezone names to PHP-compatible timezone IDs.
	 *
	 * @param  string $timezone Graph timezone name.
	 * @return string
	 */
	private function normalize_graph_timezone( $timezone ) {
		$timezone = trim( (string) $timezone );
		if ( '' === $timezone ) {
			return $this->get_default_calendar_timezone();
		}

		$map = array(
			'W. Europe Standard Time' => 'Europe/Berlin',
			'GMT Standard Time'       => 'Europe/London',
			'Romance Standard Time'   => 'Europe/Paris',
			'UTC'                     => 'UTC',
		);

		if ( isset( $map[ $timezone ] ) ) {
			return $map[ $timezone ];
		}

		if ( in_array( $timezone, timezone_identifiers_list(), true ) ) {
			return $timezone;
		}

		return $this->get_default_calendar_timezone();
	}

	/**
	 * Return the default calendar timezone based on WordPress settings.
	 *
	 * @return string
	 */
	private function get_default_calendar_timezone() {
		$wp_tz = wp_timezone_string();
		if ( '' !== $wp_tz ) {
			return $wp_tz;
		}

		return 'UTC';
	}

	// ------------------------------------------------------------------
	// Shortcode: [msgraph_files]
	// ------------------------------------------------------------------

	/**
	 * Render a OneDrive file listing.
	 *
	 * Attributes:
	 *   limit        – max number of items (default 50)
	 *   folder       – OneDrive folder path (default: root)
	 *   title        – heading text (default "My Files")
	 *   columns      – comma-separated list of columns to show (default: all).
	 *                  Supported values: file, size, modified.
	 *                  Example: columns="file,modified"
	 *   show_headers – whether to render table headers (default true)
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_files( $atts ) {
		$this->track_shortcode_render( 'msgraph_files' );

		$wording = $this->get_shortcode_wording();

		$atts = shortcode_atts(
			array(
				'limit'        => 50,
				'folder'       => '',
				'title'        => '',
				'class'        => '',
				'table_class'  => '',
				'item_class'   => '',
				'columns'      => '',
				'show_headers' => 'true',
				'template'     => '',
			),
			$atts,
			'msgraph_files'
		);

			$show_headers = $this->shortcode_att_to_bool( $atts['show_headers'], true );
			$files_wrap_class  = $this->merge_css_classes( 'msgraph_files', $atts['class'] );
			$files_table_class = $this->merge_css_classes( 'msgraph_table msgraph_files__table', $atts['table_class'] );
		$files_item_class  = $this->merge_css_classes( 'msgraph_files__item', $atts['item_class'] );

		$files_available_columns = array(
			'file'     => $wording['files_header_file'],
			'size'     => $wording['files_header_size'],
			'modified' => $wording['files_header_modified'],
		);
		$files_requested_columns = array_filter(
			array_map( 'trim', explode( ',', strtolower( (string) $atts['columns'] ) ) ),
			function ( $column ) {
				return '' !== $column;
			}
		);
		if ( empty( $files_requested_columns ) ) {
			$files_active_columns = array_keys( $files_available_columns );
		} else {
			$files_active_columns = array();
			foreach ( $files_requested_columns as $column ) {
				if ( isset( $files_available_columns[ $column ] ) && ! in_array( $column, $files_active_columns, true ) ) {
					$files_active_columns[] = $column;
				}
			}
			if ( empty( $files_active_columns ) ) {
				$files_active_columns = array_keys( $files_available_columns );
			}
		}

		if ( ! WP_MS365_Auth::is_connected() ) {
			return $this->not_connected_notice();
		}

		$folder          = trim( (string) $atts['folder'] );
		$limit           = max( 1, (int) $atts['limit'] );
		$configured_user = WP_MS365_Graph::get_configured_user();
		$files_cache_key = $this->get_shortcode_cache_key(
			'files',
			array(
				'folder' => $folder,
				'limit'  => $limit,
				'user'   => $configured_user,
			)
		);

		$cache_busted = $this->is_cache_busted();
		if ( $cache_busted ) {
			delete_transient( $files_cache_key );
		}

		$items = $cache_busted ? false : get_transient( $files_cache_key );
		if ( false === $items || ! is_array( $items ) ) {
			$result = WP_MS365_Graph::get_drive_items( $folder, $limit, $configured_user );
			if ( is_wp_error( $result ) ) {
				return $this->error_notice( $result->get_error_message() );
			}

			$items = isset( $result['value'] ) ? $result['value'] : array();
			$items = array_values(
				array_filter(
					$items,
					function ( $item ) {
						return ! isset( $item['folder'] );
					}
				)
			);

			set_transient( $files_cache_key, $items, $this->get_shortcode_cache_ttl() );
		}

		// Build per-item context for custom templates.
		$template_items = array();
		foreach ( $items as $item ) {
			$fi_name     = isset( $item['name'] ) ? (string) $item['name'] : '';
			$fi_size     = isset( $item['size'] ) ? self::format_bytes_public( $item['size'] ) : '';
			$fi_modified = isset( $item['lastModifiedDateTime'] )
				? date_i18n( get_option( 'date_format' ), strtotime( $item['lastModifiedDateTime'] ) )
				: '';
			$fi_url = ( isset( $item['id'] ) && '' !== (string) $item['id'] )
				? add_query_arg( 'ms365_download', $this->encode_local_token_param( (string) $item['id'] ), home_url( '/' ) )
				: '';
			$template_items[] = array(
				'name'         => $fi_name,
				'size'         => $fi_size,
				'modified'     => $fi_modified,
				'download_url' => $fi_url,
				'item_id'      => isset( $item['id'] ) ? (string) $item['id'] : '',
			);
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $files_wrap_class ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="msgraph_files__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<p class="msgraph_files__empty"><?php echo esc_html( $wording['files_empty_text'] ); ?></p>
			<?php else : ?>
				<table class="<?php echo esc_attr( $files_table_class ); ?>">
					<?php if ( $show_headers ) : ?>
						<thead>
							<tr>
								<?php foreach ( $files_active_columns as $col ) : ?>
									<th scope="col"><?php echo esc_html( $files_available_columns[ $col ] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
					<?php endif; ?>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<?php
							$name     = isset( $item['name'] ) ? $item['name'] : '';
							$size     = isset( $item['size'] ) ? self::format_bytes_public( $item['size'] ) : '';
							$modified = isset( $item['lastModifiedDateTime'] )
								? date_i18n( get_option( 'date_format' ), strtotime( $item['lastModifiedDateTime'] ) )
								: '';
							$download_link = ( isset( $item['id'] ) && '' !== (string) $item['id'] )
								? add_query_arg( 'ms365_download', $this->encode_local_token_param( (string) $item['id'] ), home_url( '/' ) )
								: '';
							?>
							<tr class="<?php echo esc_attr( $files_item_class ); ?>">
								<?php foreach ( $files_active_columns as $col ) : ?>
									<?php if ( 'file' === $col ) : ?>
										<td class="msgraph_files__name" data-label="<?php echo esc_attr( $files_available_columns[ $col ] ); ?>">
											<?php if ( $download_link ) : ?>
												<a href="<?php echo esc_url( $download_link ); ?>" rel="nofollow">
													<?php echo esc_html( $name ); ?>
												</a>
											<?php else : ?>
												<?php echo esc_html( $name ); ?>
											<?php endif; ?>
										</td>
									<?php elseif ( 'size' === $col ) : ?>
										<td class="msgraph_files__meta msgraph_files__meta--size" data-label="<?php echo esc_attr( $files_available_columns[ $col ] ); ?>"><?php echo esc_html( $size ); ?></td>
									<?php elseif ( 'modified' === $col ) : ?>
										<td class="msgraph_files__meta msgraph_files__meta--modified" data-label="<?php echo esc_attr( $files_available_columns[ $col ] ); ?>"><?php echo esc_html( $modified ); ?></td>
									<?php endif; ?>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		$default_html = ob_get_clean();
		return $this->maybe_render_custom_template(
			'files',
			$default_html,
			array(
				'title'        => (string) $atts['title'],
				'item_count'   => count( $items ),
				'show_headers' => $show_headers,
				'items'        => $template_items,
			),
			sanitize_key( (string) $atts['template'] )
		);
	}

	// ------------------------------------------------------------------
	// Shortcode: [msgraph_sharepoint_library]
	// ------------------------------------------------------------------

	/**
	 * Render a SharePoint document library file listing.
	 *
	 * Attributes:
	 *   site_id      – SharePoint site ID (required)
	 *   drive_id     – SharePoint document library drive ID (required)
	 *   limit        – max number of items (default 50)
	 *   folder       – folder path inside the library (default: root)
	 *   title        – heading text (default empty)
	 *   columns      – comma-separated list of columns to show (default: all).
	 *                  Supported values: file, size, modified.
	 *                  Example: columns="file,modified"
	 *   show_headers – whether to render table headers (default true)
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_sharepoint_library( $atts ) {
		$this->track_shortcode_render( 'msgraph_sharepoint_library' );

		$wording = $this->get_shortcode_wording();

		$atts = shortcode_atts(
			array(
				'site_id'      => '',
				'drive_id'     => '',
				'limit'        => 50,
				'folder'       => '',
				'title'        => '',
				'class'        => '',
				'table_class'  => '',
				'item_class'   => '',
				'columns'      => '',
				'show_headers' => 'true',
				'template'     => '',
			),
			$atts,
			'msgraph_sharepoint_library'
		);

			$show_headers   = $this->shortcode_att_to_bool( $atts['show_headers'], true );
			$sp_wrap_class  = $this->merge_css_classes( 'msgraph_files msgraph_files--sharepoint', $atts['class'] );
			$sp_table_class = $this->merge_css_classes( 'msgraph_table msgraph_files__table', $atts['table_class'] );
			$sp_item_class  = $this->merge_css_classes( 'msgraph_files__item', $atts['item_class'] );
		$site_id      = trim( (string) $atts['site_id'] );
		$drive_id     = trim( (string) $atts['drive_id'] );
		$folder       = trim( (string) $atts['folder'] );

		$sp_available_columns = array(
			'file'     => $wording['files_header_file'],
			'size'     => $wording['files_header_size'],
			'modified' => $wording['files_header_modified'],
		);
		$sp_requested_columns = array_filter(
			array_map( 'trim', explode( ',', strtolower( (string) $atts['columns'] ) ) ),
			function ( $column ) {
				return '' !== $column;
			}
		);
		if ( empty( $sp_requested_columns ) ) {
			$sp_active_columns = array_keys( $sp_available_columns );
		} else {
			$sp_active_columns = array();
			foreach ( $sp_requested_columns as $column ) {
				if ( isset( $sp_available_columns[ $column ] ) && ! in_array( $column, $sp_active_columns, true ) ) {
					$sp_active_columns[] = $column;
				}
			}
			if ( empty( $sp_active_columns ) ) {
				$sp_active_columns = array_keys( $sp_available_columns );
			}
		}

		if ( '' === $site_id || '' === $drive_id ) {
			return $this->error_notice( __( 'SharePoint site_id and drive_id are required.', 'wp-ms365-graph' ) );
		}

		if ( ! WP_MS365_Auth::is_connected() ) {
			return $this->not_connected_notice();
		}

		$limit = max( 1, (int) $atts['limit'] );
		$sp_files_cache_key = $this->get_shortcode_cache_key(
			'sharepoint_library',
			array(
				'site_id'  => $site_id,
				'drive_id' => $drive_id,
				'folder'   => $folder,
				'limit'    => $limit,
			)
		);

		$cache_busted = $this->is_cache_busted();
		if ( $cache_busted ) {
			delete_transient( $sp_files_cache_key );
		}

		$items = $cache_busted ? false : get_transient( $sp_files_cache_key );
		if ( false === $items || ! is_array( $items ) ) {
			$result = WP_MS365_Graph::get_sharepoint_library_items( $site_id, $drive_id, $folder, $limit );
			if ( is_wp_error( $result ) ) {
				return $this->error_notice( $result->get_error_message() );
			}

			$items = isset( $result['value'] ) ? $result['value'] : array();
			$items = array_values(
				array_filter(
					$items,
					function ( $item ) {
						return ! isset( $item['folder'] );
					}
				)
			);

			set_transient( $sp_files_cache_key, $items, $this->get_shortcode_cache_ttl() );
		}

		// Build per-item context for custom templates.
		$template_items = array();
		foreach ( $items as $item ) {
			$sp_name     = isset( $item['name'] ) ? (string) $item['name'] : '';
			$sp_size     = isset( $item['size'] ) ? self::format_bytes_public( $item['size'] ) : '';
			$sp_modified = isset( $item['lastModifiedDateTime'] )
				? date_i18n( get_option( 'date_format' ), strtotime( $item['lastModifiedDateTime'] ) )
				: '';
			$sp_url = '';
			if ( isset( $item['id'] ) && '' !== (string) $item['id'] ) {
				$sp_url = add_query_arg(
					'ms365_sp_download',
					$this->encode_local_token_param(
						wp_json_encode(
							array(
								'site_id'  => $site_id,
								'drive_id' => $drive_id,
								'item_id'  => (string) $item['id'],
							)
						)
					),
					home_url( '/' )
				);
			}
			$template_items[] = array(
				'name'         => $sp_name,
				'size'         => $sp_size,
				'modified'     => $sp_modified,
				'download_url' => $sp_url,
				'item_id'      => isset( $item['id'] ) ? (string) $item['id'] : '',
			);
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $sp_wrap_class ); ?>">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="msgraph_files__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<p class="msgraph_files__empty"><?php echo esc_html( $wording['files_empty_text'] ); ?></p>
			<?php else : ?>
				<table class="<?php echo esc_attr( $sp_table_class ); ?>">
					<?php if ( $show_headers ) : ?>
						<thead>
							<tr>
								<?php foreach ( $sp_active_columns as $col ) : ?>
									<th scope="col"><?php echo esc_html( $sp_available_columns[ $col ] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
					<?php endif; ?>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<?php
							$name     = isset( $item['name'] ) ? $item['name'] : '';
							$size     = isset( $item['size'] ) ? self::format_bytes_public( $item['size'] ) : '';
							$modified = isset( $item['lastModifiedDateTime'] )
								? date_i18n( get_option( 'date_format' ), strtotime( $item['lastModifiedDateTime'] ) )
								: '';
							$download_link = '';
							if ( isset( $item['id'] ) && '' !== (string) $item['id'] ) {
								$download_link = add_query_arg(
									'ms365_sp_download',
									$this->encode_local_token_param(
										wp_json_encode(
											array(
												'site_id'  => $site_id,
												'drive_id' => $drive_id,
												'item_id'  => (string) $item['id'],
											)
										)
									),
									home_url( '/' )
								);
							}
							?>
							<tr class="<?php echo esc_attr( $sp_item_class ); ?>">
								<?php foreach ( $sp_active_columns as $col ) : ?>
									<?php if ( 'file' === $col ) : ?>
										<td class="msgraph_files__name" data-label="<?php echo esc_attr( $sp_available_columns[ $col ] ); ?>">
											<?php if ( $download_link ) : ?>
												<a href="<?php echo esc_url( $download_link ); ?>" rel="nofollow">
													<?php echo esc_html( $name ); ?>
												</a>
											<?php else : ?>
												<?php echo esc_html( $name ); ?>
											<?php endif; ?>
										</td>
									<?php elseif ( 'size' === $col ) : ?>
										<td class="msgraph_files__meta msgraph_files__meta--size" data-label="<?php echo esc_attr( $sp_available_columns[ $col ] ); ?>"><?php echo esc_html( $size ); ?></td>
									<?php elseif ( 'modified' === $col ) : ?>
										<td class="msgraph_files__meta msgraph_files__meta--modified" data-label="<?php echo esc_attr( $sp_available_columns[ $col ] ); ?>"><?php echo esc_html( $modified ); ?></td>
									<?php endif; ?>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		$default_html = ob_get_clean();
		return $this->maybe_render_custom_template(
			'sharepoint',
			$default_html,
			array(
				'title'        => (string) $atts['title'],
				'item_count'   => count( $items ),
				'show_headers' => $show_headers,
				'site_id'      => $site_id,
				'drive_id'     => $drive_id,
				'items'        => $template_items,
			),
			sanitize_key( (string) $atts['template'] )
		);
	}

	// ------------------------------------------------------------------
	// Shortcode: [msgraph_teams_message_form]
	// ------------------------------------------------------------------

	/**
	 * Render a public form that submits a message to a Teams channel.
	 *
	 * Routing (endpoint, team, channel) is taken exclusively from plugin settings.
	 *
	 * Attributes:
	 *   title        – optional heading text
	 *   placeholder  – textarea placeholder
	 *   button_text  – submit button label
	 *   max_length   – max message length (default 1000, hard max 4000)
	 *   class        – extra CSS class on the outer wrapper
	 *   form_class   – extra CSS class on the <form> element
	 *   input_class  – extra CSS class on text/email inputs
	 *   textarea_class – extra CSS class on the textarea
	 *   submit_class – extra CSS class on the submit button
	 *   template     – named render template key
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_teams_message_form( $atts ) {
		$this->track_shortcode_render( 'msgraph_teams_message_form' );

		$settings = WP_MS365_Auth::get_settings();
		$wording  = $this->get_shortcode_wording();
		$atts     = shortcode_atts(
			array(
				'title'          => '',
				'class'          => '',
				'form_class'     => '',
				'input_class'    => '',
				'textarea_class' => '',
				'submit_class'   => '',
				'placeholder'    => $wording['teams_form_placeholder'],
				'button_text'    => $wording['teams_form_button_text'],
				'max_length'     => 1000,
				'template'       => '',
			),
			$atts,
			'msgraph_teams_message_form'
		);

		$team_id    = ! empty( $settings['teams_team_id'] ) ? trim( (string) $settings['teams_team_id'] ) : '';
		$channel_id = ! empty( $settings['teams_channel_id'] ) ? trim( (string) $settings['teams_channel_id'] ) : '';

		$endpoint_url = '';
		if ( ! empty( $settings['teams_workflow_url'] ) ) {
			$endpoint_url = trim( (string) $settings['teams_workflow_url'] );
		}
		if ( '' === $endpoint_url && ! empty( $settings['teams_webhook_url'] ) ) {
			$endpoint_url = trim( (string) $settings['teams_webhook_url'] );
		}

		if ( '' === $endpoint_url || ! wp_http_validate_url( $endpoint_url ) ) {
			return $this->error_notice( __( 'Teams form is not configured. Set a valid Teams endpoint URL in the plugin settings.', 'wp-ms365-graph' ) );
		}

		$max_length    = max( 20, min( 4000, (int) $atts['max_length'] ) );
		$adaptive_mode = $this->should_use_teams_adaptive_card( $endpoint_url, 'auto' );
		$teams_wrap_class     = $this->merge_css_classes( 'msgraph_teams_form-wrap', $atts['class'] );
		$teams_form_class     = $this->merge_css_classes( 'msgraph_teams_form', $atts['form_class'] );
		$teams_input_class    = $this->merge_css_classes( 'msgraph_teams_form__input', $atts['input_class'] );
		$teams_textarea_class = $this->merge_css_classes( 'msgraph_teams_form__textarea', $atts['textarea_class'] );
		$teams_submit_class   = $this->merge_css_classes( 'msgraph_teams_form__submit', $atts['submit_class'] );
		if ( $adaptive_mode ) {
			$team_id    = '';
			$channel_id = '';
		}
		$form_id    = 'msgraph_teams_form_' . wp_generate_password( 8, false, false );
		$form_started_at = time();
		$route_key       = $this->get_teams_form_route_key( $endpoint_url, $team_id, $channel_id );
		$form_token      = $this->sign_teams_form_token( $form_started_at, $route_key );

		$status = isset( $_GET['ms365_teams_status'] ) ? sanitize_key( wp_unslash( $_GET['ms365_teams_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reason = isset( $_GET['ms365_teams_reason'] ) ? sanitize_key( wp_unslash( $_GET['ms365_teams_reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		?>
		<div class="<?php echo esc_attr( $teams_wrap_class ); ?>">
			<?php if ( '' !== trim( (string) $atts['title'] ) ) : ?>
				<h3 class="msgraph_teams_form__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( 'success' === $status ) : ?>
				<p class="msgraph_notice msgraph_notice--success"><?php echo esc_html( $wording['teams_form_success'] ); ?></p>
			<?php elseif ( 'error' === $status ) : ?>
				<?php
				$error_map = array(
					'invalid_nonce'   => $wording['teams_form_error_invalid_nonce'],
					'missing_fields'  => $wording['teams_form_error_missing_fields'],
					'invalid_email'   => $wording['teams_form_error_invalid_email'],
					'invalid_form'    => $wording['teams_form_error_invalid_form'],
					'submitted_too_fast' => $wording['teams_form_error_submitted_too_fast'],
					'rate_limited'    => $wording['teams_form_error_rate_limited'],
					'invalid_endpoint' => $wording['teams_form_error_invalid_endpoint'],
					'invalid_webhook' => $wording['teams_form_error_invalid_endpoint'],
					'teams_post_fail' => $wording['teams_form_error_post_fail'],
					'unknown'         => $wording['teams_form_error_unknown'],
				);
				$error_text = isset( $error_map[ $reason ] ) ? $error_map[ $reason ] : $error_map['unknown'];
				?>
				<p class="msgraph_notice msgraph_notice--error"><?php echo esc_html( $error_text ); ?></p>
			<?php endif; ?>

			<form id="<?php echo esc_attr( $form_id ); ?>" class="<?php echo esc_attr( $teams_form_class ); ?>" method="post" action="<?php echo esc_url( $this->get_current_request_url() ); ?>">
				<input type="hidden" name="action" value="wp_ms365_submit_teams_message" />
				<input type="hidden" name="ms365_form_started_at" value="<?php echo esc_attr( (string) $form_started_at ); ?>" />
				<input type="hidden" name="ms365_form_token" value="<?php echo esc_attr( $form_token ); ?>" />
				<?php wp_nonce_field( 'wp_ms365_submit_teams_message', 'wp_ms365_teams_nonce' ); ?>

				<div class="msgraph_teams_form__honeypot" aria-hidden="true">
					<label for="<?php echo esc_attr( $form_id . '_website' ); ?>"><?php esc_html_e( 'Website', 'wp-ms365-graph' ); ?></label>
					<input id="<?php echo esc_attr( $form_id . '_website' ); ?>" type="text" name="website" value="" tabindex="-1" autocomplete="off" />
				</div>

				<label class="msgraph_teams_form__label" for="<?php echo esc_attr( $form_id . '_sender_name' ); ?>">
					<?php echo esc_html( $wording['teams_form_label_name'] ); ?>
				</label>
				<input
					id="<?php echo esc_attr( $form_id . '_sender_name' ); ?>"
					type="text"
					name="sender_name"
					class="<?php echo esc_attr( $teams_input_class ); ?>"
					maxlength="120"
					autocomplete="name"
					required
				/>

				<label class="msgraph_teams_form__label" for="<?php echo esc_attr( $form_id . '_sender_email' ); ?>">
					<?php echo esc_html( $wording['teams_form_label_email'] ); ?>
				</label>
				<input
					id="<?php echo esc_attr( $form_id . '_sender_email' ); ?>"
					type="email"
					name="sender_email"
					class="<?php echo esc_attr( $teams_input_class ); ?>"
					maxlength="190"
					autocomplete="email"
					required
				/>

				<label class="msgraph_teams_form__label" for="<?php echo esc_attr( $form_id . '_message' ); ?>">
					<?php echo esc_html( $wording['teams_form_label_message'] ); ?>
				</label>
				<textarea
					id="<?php echo esc_attr( $form_id . '_message' ); ?>"
					name="message"
					rows="5"
					class="<?php echo esc_attr( $teams_textarea_class ); ?>"
					maxlength="<?php echo esc_attr( $max_length ); ?>"
					placeholder="<?php echo esc_attr( (string) $atts['placeholder'] ); ?>"
					required
				></textarea>

				<button type="submit" class="<?php echo esc_attr( $teams_submit_class ); ?>"><?php echo esc_html( (string) $atts['button_text'] ); ?></button>
			</form>
		</div>
		<?php
		$default_html = ob_get_clean();
		return $this->maybe_render_custom_template(
			'teams_form',
			$default_html,
			array(
				'title'             => (string) $atts['title'],
				'endpoint_url'      => $endpoint_url,
				'use_adaptive_card' => $adaptive_mode,
			),
			sanitize_key( (string) $atts['template'] )
		);
	}

	/**
	 * Handle public form submission for Teams channel messages.
	 *
	 * @return void
	 */
	public function handle_teams_message_submission() {
		$redirect_to = $this->get_current_request_url();

		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'unknown' ) );
			exit;
		}

		$nonce = isset( $_POST['wp_ms365_teams_nonce'] ) ? (string) wp_unslash( $_POST['wp_ms365_teams_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_ms365_submit_teams_message' ) ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'invalid_nonce' ) );
			exit;
		}

		$team_id      = '';
		$channel_id   = '';
		$endpoint_url = '';
		$message      = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
		$sender_name  = isset( $_POST['sender_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['sender_name'] ) ) ) : '';
		$sender_email = isset( $_POST['sender_email'] ) ? trim( sanitize_email( wp_unslash( $_POST['sender_email'] ) ) ) : '';
		$honeypot     = isset( $_POST['website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['website'] ) ) ) : '';
		$form_started = isset( $_POST['ms365_form_started_at'] ) ? (int) wp_unslash( $_POST['ms365_form_started_at'] ) : 0;
		$form_token   = isset( $_POST['ms365_form_token'] ) ? trim( (string) wp_unslash( $_POST['ms365_form_token'] ) ) : '';

		$settings = WP_MS365_Auth::get_settings();
		if ( '' === $endpoint_url && ! empty( $settings['teams_workflow_url'] ) ) {
			$endpoint_url = trim( (string) $settings['teams_workflow_url'] );
		}
		if ( '' === $endpoint_url && ! empty( $settings['teams_webhook_url'] ) ) {
			$endpoint_url = trim( (string) $settings['teams_webhook_url'] );
		}

		if ( '' === $endpoint_url || ! wp_http_validate_url( $endpoint_url ) ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'invalid_endpoint' ) );
			exit;
		}

		$adaptive_mode = $this->should_use_teams_adaptive_card( $endpoint_url, 'auto' );
		if ( ! $adaptive_mode ) {
			if ( ! empty( $settings['teams_team_id'] ) ) {
				$team_id = trim( (string) $settings['teams_team_id'] );
			}
			if ( ! empty( $settings['teams_channel_id'] ) ) {
				$channel_id = trim( (string) $settings['teams_channel_id'] );
			}
		} else {
			$team_id    = '';
			$channel_id = '';
		}

		$route_key = $this->get_teams_form_route_key( $endpoint_url, $team_id, $channel_id );

		if ( '' === $sender_name || '' === $sender_email || '' === $message ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'missing_fields' ) );
			exit;
		}

		if ( ! is_email( $sender_email ) ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'invalid_email' ) );
			exit;
		}

		if ( '' !== $honeypot ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'rate_limited' ) );
			exit;
		}

		if ( ! $this->verify_teams_form_token( $form_started, $form_token, $route_key ) ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'invalid_form' ) );
			exit;
		}

		$minimum_submit_seconds_setting = isset( $settings['teams_min_submit_seconds'] ) ? (int) $settings['teams_min_submit_seconds'] : 3;
		$minimum_submit_seconds = max( 1, min( 120, (int) apply_filters( 'wp_ms365_teams_form_min_submit_seconds', $minimum_submit_seconds_setting ) ) );
		if ( ( time() - $form_started ) < $minimum_submit_seconds ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'submitted_too_fast' ) );
			exit;
		}

		$rate_limit_max    = isset( $settings['teams_rate_limit_max'] ) ? max( 1, min( 50, (int) $settings['teams_rate_limit_max'] ) ) : 3;
		$rate_limit_window = isset( $settings['teams_rate_limit_window'] ) ? max( 30, min( 86400, (int) $settings['teams_rate_limit_window'] ) ) : 300;

		if ( $this->is_teams_form_rate_limited( $route_key, $rate_limit_max, $rate_limit_window ) ) {
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'rate_limited' ) );
			exit;
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$origin    = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		$header_lines = array( '[' . $site_name . ' | ' . $origin . ']' );
		if ( '' !== $sender_name && '' !== $sender_email ) {
			$header_lines[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
		} elseif ( '' !== $sender_name ) {
			$header_lines[] = 'From: ' . $sender_name;
		} elseif ( '' !== $sender_email ) {
			$header_lines[] = 'From: <' . $sender_email . '>';
		}

		$text_payload = implode( "\n", $header_lines ) . "\n\n" . $message;

		if ( $adaptive_mode ) {
			$payload = $this->build_teams_workflow_payload( $text_payload, $message, $sender_name, $sender_email );
			$result  = WP_MS365_Graph::post_teams_workflow_message( $endpoint_url, $payload );
		} else {
			$result = WP_MS365_Graph::post_teams_webhook_message( $endpoint_url, $text_payload );
		}

		if ( is_wp_error( $result ) ) {
			WP_MS365_Logger::log( 'error', 'Teams form submission failed', array( 'reason' => $result->get_error_message() ) );
			wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'error', 'teams_post_fail' ) );
			exit;
		}

		wp_safe_redirect( $this->append_teams_form_status( $redirect_to, 'success' ) );
		exit;
	}

	/**
	 * Check and consume Teams form rate-limit quota.
	 *
	 * @param  string $route_key          Routing key of the Teams target.
	 * @param  int    $max_requests       Max requests per window.
	 * @param  int    $window_seconds     Window length in seconds.
	 * @return bool True when rate limited.
	 */
	private function is_teams_form_rate_limited( $route_key, $max_requests, $window_seconds ) {
		$identity   = $this->get_teams_form_submitter_identity();
		$bucket_key = 'wp_ms365_teams_form_rl_' . md5( $identity . '|' . $route_key );
		$current    = get_transient( $bucket_key );

		if ( ! is_array( $current ) ) {
			$current = array( 'count' => 0 );
		}

		$count = isset( $current['count'] ) ? (int) $current['count'] : 0;
		if ( $count >= $max_requests ) {
			return true;
		}

		set_transient(
			$bucket_key,
			array( 'count' => $count + 1 ),
			$window_seconds
		);

		return false;
	}

	/**
	 * Build a stable identity key for the current submitter.
	 *
	 * Uses REMOTE_ADDR exclusively. HTTP_X_FORWARDED_FOR is client-controlled
	 * and must not be trusted for rate-limiting: an attacker can cycle arbitrary
	 * IP values to bypass the per-IP submission throttle.
	 *
	 * @return string
	 */
	private function get_teams_form_submitter_identity() {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			return 'u:' . $user_id;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return 'ip:' . sanitize_text_field( $ip );
	}

	/**
	 * Build a stable route key for Teams form target.
	 *
	 * @param  string $endpoint_url Teams endpoint URL.
	 * @param  string $team_id     Optional team ID metadata.
	 * @param  string $channel_id  Optional channel ID metadata.
	 * @return string
	 */
	private function get_teams_form_route_key( $endpoint_url, $team_id = '', $channel_id = '' ) {
		$endpoint_url = trim( (string) $endpoint_url );
		if ( '' !== $endpoint_url ) {
			return 'ep:' . md5( $endpoint_url );
		}

		return 'ch:' . trim( (string) $team_id ) . '|' . trim( (string) $channel_id );
	}

	/**
	 * Build payload for Teams workflow endpoint.
	 *
	 * @param  string $text_payload Formatted message text.
	 * @param  string $message      Original message body.
	 * @param  string $sender_name  Optional sender name.
	 * @param  string $sender_email Optional sender email.
	 * @return array
	 */
	private function build_teams_workflow_payload( $text_payload, $message, $sender_name, $sender_email ) {
		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site_url     = home_url( '/' );
		$submitted_at = gmdate( 'c' );

		$adaptive_card = array(
			'type'    => 'AdaptiveCard',
			'version' => '1.4',
			'body'    => array(
				array(
					'type'   => 'TextBlock',
					'weight' => 'Bolder',
					'size'   => 'Medium',
					'text'   => sprintf( 'New website message (%s)', $site_name ),
					'wrap'   => true,
				),
				array(
					'type'  => 'FactSet',
					'facts' => array(
						array(
							'title' => 'Sender',
							'value' => ( '' !== $sender_name ) ? (string) $sender_name : 'Anonymous',
						),
						array(
							'title' => 'Email',
							'value' => ( '' !== $sender_email ) ? (string) $sender_email : '-',
						),
						array(
							'title' => 'Submitted',
							'value' => $submitted_at,
						),
					),
				),
				array(
					'type'    => 'TextBlock',
					'text'    => (string) $message,
					'wrap'    => true,
					'spacing' => 'Medium',
				),
			),
			'$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
			'msteams' => array(
				'width' => 'Full',
			),
		);

		return array(
			'text'         => (string) $text_payload,
			'message'      => (string) $message,
			'sender_name'  => (string) $sender_name,
			'sender_email' => (string) $sender_email,
			'site_name'    => $site_name,
			'site_url'     => $site_url,
			'submitted_at' => $submitted_at,
			'adaptive_card' => $adaptive_card,
		);
	}

	/**
	 * Determine whether adaptive card mode should be used.
	 *
	 * @param  string $endpoint_url Endpoint URL.
	 * @param  string $preference   auto|true|false.
	 * @return bool
	 */
	private function should_use_teams_adaptive_card( $endpoint_url, $preference = 'auto' ) {
		$preference = sanitize_key( (string) $preference );

		if ( in_array( $preference, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $preference, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}

		$host = (string) wp_parse_url( $endpoint_url, PHP_URL_HOST );
		$path = (string) wp_parse_url( $endpoint_url, PHP_URL_PATH );

		$detected = false;
		if ( false !== strpos( $host, 'logic.azure.com' ) ) {
			$detected = true;
		}
 
		if ( false !== strpos( $path, '/workflows/' ) ) {
			$detected = true;
		}

		return (bool) apply_filters( 'wp_ms365_teams_form_use_adaptive_card', $detected, $endpoint_url, $preference );
	}

	/**
	 * Build a signed token for Teams form anti-tampering checks.
	 *
	 * @param  int    $started_at Unix timestamp when form was rendered.
	 * @param  string $route_key Routing key for Teams target.
	 * @return string
	 */
	private function sign_teams_form_token( $started_at, $route_key ) {
		$data = (int) $started_at . '|' . trim( (string) $route_key );
		return hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
	}

	/**
	 * Verify Teams form anti-tampering token and timestamp freshness.
	 *
	 * @param  int    $started_at Unix timestamp from form payload.
	 * @param  string $token HMAC token from form payload.
	 * @param  string $route_key Routing key for Teams target.
	 * @return bool
	 */
	private function verify_teams_form_token( $started_at, $token, $route_key ) {
		if ( $started_at <= 0 || '' === $token ) {
			return false;
		}

		// Reject stale forms to reduce replay opportunities.
		if ( ( time() - $started_at ) > HOUR_IN_SECONDS * 2 ) {
			return false;
		}

		$expected = $this->sign_teams_form_token( $started_at, $route_key );
		return hash_equals( $expected, $token );
	}

	/**
	 * Add teams form status query args to a redirect URL.
	 *
	 * @param  string $url    Base URL.
	 * @param  string $status success|error.
	 * @param  string $reason Optional reason key.
	 * @return string
	 */
	private function append_teams_form_status( $url, $status, $reason = '' ) {
		$args = array( 'ms365_teams_status' => sanitize_key( $status ) );
		if ( '' !== $reason ) {
			$args['ms365_teams_reason'] = sanitize_key( $reason );
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * Build the current frontend URL for form redirects.
	 *
	 * Uses home_url() as the authority — HTTP_HOST is client-controlled and
	 * must not be trusted for constructing redirect destinations. Only the
	 * path and query string are taken from REQUEST_URI.
	 *
	 * @return string
	 */
	private function get_current_request_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

		// Strip any scheme + host prefix that some server configs include in REQUEST_URI.
		$path = preg_replace( '#^https?://[^/]+#i', '', $request_uri );
		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . ltrim( (string) $path, '/' );
		}

		return esc_url_raw( home_url( $path ) );
	}

	// ------------------------------------------------------------------
	// Assets
	// ------------------------------------------------------------------

	/**
	 * Enqueue front-end stylesheet.
	 */
	public function enqueue_assets() {
		// Intentionally do not load a default stylesheet so shortcode output inherits
		// typography and spacing from the parent block/theme.
		wp_register_style( 'wp-ms365-graph', false, array(), WP_MS365_VERSION );
		wp_enqueue_style( 'wp-ms365-graph' );

		$settings   = WP_MS365_Auth::get_settings();
		$custom_css = isset( $settings['custom_css'] ) ? trim( (string) $settings['custom_css'] ) : '';
		$base_css   = '
			.msgraph_table {
				width: 100%;
				border-collapse: collapse;
				border: 0;
			}
			.msgraph_calendar__table th:first-child,
			.msgraph_calendar__table td:first-child,
			.msgraph_files__table th:nth-child(2),
			.msgraph_files__table th:nth-child(3),
			.msgraph_files__table td:nth-child(2),
			.msgraph_files__table td:nth-child(3) {
				white-space: nowrap;
			}
			.msgraph_table th,
			.msgraph_table td {
				border: 0;
				padding: 0.3rem 0.5rem 0.3rem 0;
				text-align: left;
				vertical-align: top;
			}
			.msgraph_files__table th:nth-child(2),
			.msgraph_files__table th:nth-child(3),
			.msgraph_files__table td:nth-child(2),
			.msgraph_files__table td:nth-child(3) {
				text-align: right;
				padding-right: 0;
				padding-left: 0.75rem;
			}
			.msgraph_table th {
				font-weight: 600;
			}
			.msgraph_teams_form-wrap {
				max-width: 680px;
			}
			.msgraph_teams_form {
				display: grid;
				gap: 0.6rem;
			}
			.msgraph_teams_form__label {
				font-weight: 600;
			}
			.msgraph_teams_form__input,
			.msgraph_teams_form__textarea {
				width: 100%;
				max-width: 100%;
				padding: 0.5rem;
			}
			.msgraph_teams_form__honeypot {
				position: absolute;
				left: -9999px;
				top: auto;
				width: 1px;
				height: 1px;
				overflow: hidden;
			}
			.msgraph_teams_form__submit {
				width: fit-content;
				padding: 0.55rem 1rem;
				cursor: pointer;
			}
			.msgraph_notice--success {
				color: #1f7a1f;
			}
			@media (max-width: 640px) {
				.msgraph_table,
				.msgraph_table tbody,
				.msgraph_table tr,
				.msgraph_table td {
					display: block;
					width: 100%;
				}
				.msgraph_table thead {
					position: absolute;
					width: 1px;
					height: 1px;
					padding: 0;
					margin: -1px;
					overflow: hidden;
					clip: rect(0, 0, 0, 0);
					white-space: nowrap;
					border: 0;
				}
				.msgraph_table tr {
					padding: 0.2rem 0;
				}
				.msgraph_table td {
					padding: 0.18rem 0;
					text-align: left;
				}
				.msgraph_table td::before {
					content: attr(data-label) ": ";
					font-weight: 600;
				}
				.msgraph_files__table th:nth-child(2),
				.msgraph_files__table th:nth-child(3),
				.msgraph_files__table td:nth-child(2),
				.msgraph_files__table td:nth-child(3) {
					text-align: left;
					padding-left: 0;
				}
			}
		';

		wp_add_inline_style( 'wp-ms365-graph', $base_css );

		if ( '' !== $custom_css ) {
			wp_add_inline_style( 'wp-ms365-graph', $custom_css );
		}
	}

	// ------------------------------------------------------------------
	// Utility
	// ------------------------------------------------------------------

	/**
	 * Apply optional custom template rendering for a shortcode output.
	 *
	 * @param  string $scope        Template scope key.
	 * @param  string $default_html Default rendered HTML.
	 * @param  array  $context      Placeholder context values.
	 * @return string
	 */
	private function maybe_render_custom_template( $scope, $default_html, array $context = array(), $named_key = '' ) {
		$scope        = sanitize_key( (string) $scope );
		$default_html = (string) $default_html;
		$output       = $default_html;

		$named_key = sanitize_key( (string) $named_key );
		if ( '' !== $named_key && '' !== $scope ) {
			try {
				$settings        = WP_MS365_Auth::get_settings();
				$nt_settings_key = 'shortcode_render_' . $scope . '_named_templates';
				$named_list      = isset( $settings[ $nt_settings_key ] ) && is_array( $settings[ $nt_settings_key ] )
					? $settings[ $nt_settings_key ]
					: array();

				$template = '';
				foreach ( $named_list as $entry ) {
					if ( is_array( $entry ) && isset( $entry['key'] ) && $entry['key'] === $named_key ) {
						$template = isset( $entry['template'] ) ? (string) $entry['template'] : '';
						break;
					}
				}

				if ( '' !== trim( $template ) ) {
					$template_context = array_merge(
						array(
							'shortcode' => $scope,
							'content'   => $default_html,
						),
						$context
					);
					$candidate = $this->render_snippet_template( $template, $template_context );
					if ( '' !== trim( $candidate ) ) {
						$output = $candidate;
					}
					// Template rendered empty → fall through to built-in output.
				}
				// Key not found or template blank → $output stays as $default_html.
			} catch ( Exception $e ) {
				// Any unexpected failure → use built-in output.
				$output = $default_html;
			}
		}
		// No template= attribute → always use built-in output.

		$filtered = apply_filters( 'wp_ms365_shortcode_custom_render', $output, $scope, $context, $default_html );
		return is_string( $filtered ) ? $filtered : $output;
	}

	/**
	 * Render a snippet template by replacing placeholder keys.
	 *
	 * Supported placeholder forms:
	 * - {{key}} for escaped output.
	 * - {{{key}}} for markup-safe output.
	 *
	 * @param  string $template Template source.
	 * @param  array  $context  Placeholder context map.
	 * @return string
	 */
	private function render_snippet_template( $template, array $context ) {
		$template = (string) $template;

		// First pre-pass: handle loop blocks {{#key}}...{{/key}}.
		// Each iteration renders the inner template with the item's own fields as context.
		$template = preg_replace_callback(
			'/\{\{#\s*([a-zA-Z0-9_\-]+)\s*\}\}(.*?)\{\{\/\s*\1\s*\}\}/s',
			function ( $matches ) use ( $context ) {
				$key   = isset( $matches[1] ) ? sanitize_key( (string) $matches[1] ) : '';
				$inner = isset( $matches[2] ) ? (string) $matches[2] : '';
				if ( '' === $key || ! isset( $context[ $key ] ) || ! is_array( $context[ $key ] ) ) {
					return '';
				}
				$output = '';
				foreach ( $context[ $key ] as $item ) {
					if ( is_array( $item ) ) {
						$output .= $this->render_snippet_template( $inner, $item );
					}
				}
				return $output;
			},
			$template
		);

		$template = preg_replace_callback(
			'/\{\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}\}/',
			function ( $matches ) use ( $context ) {
				$key = isset( $matches[1] ) ? sanitize_key( (string) $matches[1] ) : '';
				if ( '' === $key ) {
					return '';
				}

				$value = $this->get_snippet_context_value( $context, $key );
				return wp_kses_post( $value );
			},
			$template
		);

		$template = preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_\-]+)\s*\}\}/',
			function ( $matches ) use ( $context ) {
				$key = isset( $matches[1] ) ? sanitize_key( (string) $matches[1] ) : '';
				if ( '' === $key ) {
					return '';
				}

				$value = $this->get_snippet_context_value( $context, $key );
				return esc_html( $value );
			},
			$template
		);

		return $template;
	}

	/**
	 * Normalize context values used by snippet placeholders.
	 *
	 * @param  array  $context Placeholder context map.
	 * @param  string $key     Placeholder key.
	 * @return string
	 */
	private function get_snippet_context_value( array $context, $key ) {
		if ( ! array_key_exists( $key, $context ) ) {
			return '';
		}

		$value = $context[ $key ];

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_scalar( $value ) || null === $value ) {
			return (string) $value;
		}

		return (string) wp_json_encode( $value );
	}

	/**
	 * Return a "not connected" notice HTML string.
	 *
	 * @return string
	 */
	private function not_connected_notice() {
		return '<p class="msgraph_notice msgraph_notice--warning">'
			. esc_html__( 'Microsoft 365 is not connected. Please configure the plugin in the WordPress admin.', 'wp-ms365-graph' )
			. '</p>';
	}

	/**
	 * Parse a shortcode boolean-like attribute into a strict boolean.
	 *
	 * @param  mixed $value Raw attribute value.
	 * @param  bool  $default Default value if parsing fails.
	 * @return bool
	 */
	private function shortcode_att_to_bool( $value, $default = false ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$normalized = strtolower( trim( (string) $value ) );
		if ( '' === $normalized ) {
			return $default;
		}

		if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $normalized, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}

		return $default;
	}

	/**
	 * Return tracked shortcodes and default counts.
	 *
	 * @return array
	 */
	private static function get_tracked_shortcodes() {
		return array(
			'msgraph_calendar'           => 0,
			'msgraph_files'              => 0,
			'msgraph_sharepoint_library' => 0,
			'msgraph_teams_message_form' => 0,
			'msgraph_login_button'       => 0,
		);
	}

	/**
	 * Get total render counts for each tracked shortcode.
	 *
	 * @return array
	 */
	public static function get_render_counts() {
		$defaults = self::get_tracked_shortcodes();
		$stored   = get_option( self::RENDER_COUNTS_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$counts = array();
		foreach ( $defaults as $shortcode => $default_value ) {
			$counts[ $shortcode ] = isset( $stored[ $shortcode ] ) ? max( 0, (int) $stored[ $shortcode ] ) : $default_value;
		}

		return $counts;
	}

	/**
	 * Reset total render counts for all tracked shortcodes.
	 *
	 * @return void
	 */
	public static function reset_render_counts() {
		update_option( self::RENDER_COUNTS_OPTION, self::get_tracked_shortcodes(), false );
	}

	/**
	 * Increment total render counter for a shortcode.
	 *
	 * @param string $shortcode Shortcode tag.
	 * @return void
	 */
	private function track_shortcode_render( $shortcode ) {
		$shortcode = trim( (string) $shortcode );
		$defaults  = self::get_tracked_shortcodes();

		if ( '' === $shortcode || ! isset( $defaults[ $shortcode ] ) ) {
			return;
		}

		$counts               = self::get_render_counts();
		$counts[ $shortcode ] = isset( $counts[ $shortcode ] ) ? ( (int) $counts[ $shortcode ] + 1 ) : 1;

		update_option( self::RENDER_COUNTS_OPTION, $counts, false );
	}

	/**
	 * Build a stable cache key for shortcode payloads.
	 *
	 * @param  string $scope Cache scope.
	 * @param  array  $payload Key payload.
	 * @return string
	 */
	private function get_shortcode_cache_key( $scope, array $payload = array() ) {
		return 'wp_ms365_sc_' . md5( (string) $scope . '|' . wp_json_encode( $payload ) );
	}

	/**
	 * Whether the current request should bypass the shortcode cache.
	 * Only honoured for users with manage_options capability.
	 *
	 * @return bool
	 */
	private function is_cache_busted() {
		return isset( $_GET['ms365_cache_bust'] ) && current_user_can( 'manage_options' );
	}

	/**
	 * Get shortcode cache TTL in seconds.
	 *
	 * @return int
	 */
	private function get_shortcode_cache_ttl() {
		$ttl = (int) apply_filters( 'wp_ms365_shortcode_cache_ttl', 60 );
		return max( 10, $ttl );
	}

	/**
	 * Return localized shortcode wording with optional per-site overrides.
	 *
	 * @return array
	 */
	private function get_shortcode_wording() {
		$settings = WP_MS365_Auth::get_settings();
		$defaults = array(
			'calendar_empty_text'      => __( 'No upcoming events found.', 'wp-ms365-graph' ),
			'calendar_header_date'     => __( 'Date', 'wp-ms365-graph' ),
			'calendar_header_event'    => __( 'Event', 'wp-ms365-graph' ),
			'calendar_header_duration' => __( 'Duration', 'wp-ms365-graph' ),
			'calendar_all_day_text'    => __( 'All day', 'wp-ms365-graph' ),
			'calendar_header_location' => __( 'Location', 'wp-ms365-graph' ),
			'files_empty_text'         => __( 'No files found.', 'wp-ms365-graph' ),
			'files_header_file'        => __( 'File', 'wp-ms365-graph' ),
			'files_header_size'        => __( 'Size', 'wp-ms365-graph' ),
			'files_header_modified'    => __( 'Modified', 'wp-ms365-graph' ),
			'teams_form_placeholder'   => __( 'Type your message', 'wp-ms365-graph' ),
			'teams_form_button_text'   => __( 'Send Message', 'wp-ms365-graph' ),
			'teams_form_label_name'    => __( 'Your Name', 'wp-ms365-graph' ),
			'teams_form_label_email'   => __( 'Your Email', 'wp-ms365-graph' ),
			'teams_form_label_message' => __( 'Message', 'wp-ms365-graph' ),
			'teams_form_success'       => __( 'Your message has been sent.', 'wp-ms365-graph' ),
			'teams_form_error_invalid_nonce' => __( 'Security validation failed. Please refresh the page and try again.', 'wp-ms365-graph' ),
			'teams_form_error_missing_fields' => __( 'Please enter your name, email, and message before submitting.', 'wp-ms365-graph' ),
			'teams_form_error_invalid_email' => __( 'Please provide a valid email address.', 'wp-ms365-graph' ),
			'teams_form_error_invalid_form' => __( 'Invalid form submission. Please refresh and try again.', 'wp-ms365-graph' ),
			'teams_form_error_submitted_too_fast' => __( 'Submitted too quickly. Please try again.', 'wp-ms365-graph' ),
			'teams_form_error_rate_limited' => __( 'Too many requests. Please wait and try again later.', 'wp-ms365-graph' ),
			'teams_form_error_invalid_endpoint' => __( 'Message delivery is not configured. Please contact the site administrator.', 'wp-ms365-graph' ),
			'teams_form_error_post_fail' => __( 'Message could not be delivered to Teams. Please try again later.', 'wp-ms365-graph' ),
			'teams_form_error_unknown'  => __( 'Message could not be sent.', 'wp-ms365-graph' ),
		);

		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $settings[ $key ] ) && '' !== trim( (string) $settings[ $key ] ) ) {
				$defaults[ $key ] = (string) $settings[ $key ];
			}
		}

		return $defaults;
	}

	/**
	 * Merge default and override CSS classes.
	 *
	 * @param  string $defaults  Space-separated default classes.
	 * @param  string $overrides Space-separated override classes.
	 * @return string
	 */
	private function merge_css_classes( $defaults, $overrides = '' ) {
		$classes = array();
		$tokens  = preg_split( '/\s+/', trim( (string) $defaults . ' ' . (string) $overrides ) );

		if ( ! is_array( $tokens ) ) {
			return '';
		}

		foreach ( $tokens as $token ) {
			$token = trim( (string) $token );
			if ( '' === $token ) {
				continue;
			}

			$clean = sanitize_html_class( $token );
			if ( '' !== $clean && ! in_array( $clean, $classes, true ) ) {
				$classes[] = $clean;
			}
		}

		return implode( ' ', $classes );
	}

	/**
	 * Return an error notice HTML string.
	 *
	 * @param  string $message Error message.
	 * @return string
	 */
	private function error_notice( $message ) {
		return '<p class="msgraph_notice msgraph_notice--error">'
			. esc_html( $message )
			. '</p>';
	}

	/**
	 * Format bytes into a human-readable string.
	 *
	 * @param  int $bytes Number of bytes.
	 * @return string
	 */
	public static function format_bytes_public( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return number_format_i18n( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			return number_format_i18n( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			return number_format_i18n( $bytes / 1024, 2 ) . ' KB';
		}
		return (string) $bytes . ' B';
	}
}
