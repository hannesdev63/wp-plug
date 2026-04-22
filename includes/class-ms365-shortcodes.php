<?php
/**
 * Front-end shortcodes for WP Microsoft 365 Graph.
 *
 * [ms365_calendar]   – renders upcoming calendar events.
 * [ms365_files]      – renders OneDrive file listing.
 * [ms365_profile]    – renders the selected user's display name / email.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Shortcodes {

	public function __construct() {
		add_shortcode( 'ms365_calendar', array( $this, 'render_calendar' ) );
		add_shortcode( 'ms365_files',    array( $this, 'render_files' ) );
		add_shortcode( 'ms365_profile',  array( $this, 'render_profile' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'maybe_handle_download' ) );
	}

	/**
	 * Handle public download requests for OneDrive files.
	 *
	 * @return void
	 */
	public function maybe_handle_download() {
		if ( isset( $_GET['ms365_download'] ) ) {
			$this->handle_drive_download();
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
	// Shortcode: [ms365_calendar]
	// ------------------------------------------------------------------

	/**
	 * Render upcoming Microsoft 365 calendar events.
	 *
	 * Attributes:
	 *   limit    – max number of events (default 5)
	 *   timezone – IANA timezone string (default WP site timezone)
	 *   title    – heading text (default "Upcoming Events")
	 *   calendar_link_mode – link behavior: "ics" or "none" (default "ics")
	 *   categories – comma-separated category names to include (default: all)
	 *   show_headers – whether to render table headers (default true)
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_calendar( $atts ) {
		$default_timezone = $this->get_default_calendar_timezone();
		$wording          = $this->get_shortcode_wording();

		$atts = shortcode_atts(
			array(
				'limit'              => 5,
				'timezone'           => $default_timezone,
				'title'              => '',
				'calendar_link_mode' => 'ics',
				'categories'         => '',
				'show_headers'       => 'true',
			),
			$atts,
			'ms365_calendar'
		);

		$show_headers = $this->shortcode_att_to_bool( $atts['show_headers'], true );

		$link_mode = strtolower( trim( (string) $atts['calendar_link_mode'] ) );
		if ( ! in_array( $link_mode, array( 'ics', 'none' ), true ) ) {
			$link_mode = 'ics';
		}

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

		$events = WP_MS365_Graph::get_calendar_events( (int) $atts['limit'], $atts['timezone'] );

		if ( is_wp_error( $events ) ) {
			return $this->error_notice( $events->get_error_message() );
		}

		$items = isset( $events['value'] ) ? $events['value'] : array();

		if ( ! empty( $category_filter ) ) {
			$items = array_values(
				array_filter(
					$items,
					function ( $event ) use ( $category_filter ) {
						if ( empty( $event['categories'] ) || ! is_array( $event['categories'] ) ) {
							return false;
						}

						$event_categories = array_map( 'strtolower', array_map( 'trim', $event['categories'] ) );
						return count( array_intersect( $category_filter, $event_categories ) ) > 0;
					}
				)
			);
		}

		ob_start();
		?>
		<div class="ms365-calendar">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="ms365-calendar__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<p class="ms365-calendar__empty"><?php echo esc_html( $wording['calendar_empty_text'] ); ?></p>
			<?php else : ?>
				<table class="ms365-table ms365-calendar__table">
					<?php if ( $show_headers ) : ?>
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html( $wording['calendar_header_date'] ); ?></th>
								<th scope="col"><?php echo esc_html( $wording['calendar_header_event'] ); ?></th>
								<th scope="col"><?php echo esc_html( $wording['calendar_header_location'] ); ?></th>
							</tr>
						</thead>
					<?php endif; ?>
					<tbody>
						<?php foreach ( $items as $event ) : ?>
							<?php
							$subject  = isset( $event['subject'] ) ? $event['subject'] : __( '(No subject)', 'wp-ms365-graph' );
							$start    = isset( $event['start']['dateTime'] ) ? $event['start']['dateTime'] : '';
							$location = isset( $event['location']['displayName'] ) ? $event['location']['displayName'] : '';
							$event_timezone = isset( $event['start']['timeZone'] ) ? (string) $event['start']['timeZone'] : (string) $atts['timezone'];
							$event_link = '';
							if ( 'ics' === $link_mode && isset( $event['id'] ) && '' !== (string) $event['id'] ) {
								$event_link = add_query_arg( 'ms365_calendar_ics', $this->encode_local_token_param( (string) $event['id'] ), home_url( '/' ) );
							}
							$all_day  = ! empty( $event['isAllDay'] );
							$start_display = $start
								? ( $all_day
									? $this->format_event_datetime_for_display( $start, $event_timezone, true )
									: $this->format_event_datetime_for_display( $start, $event_timezone, false ) )
								: '';
							?>
							<tr class="ms365-calendar__item">
								<td class="ms365-calendar__date" data-label="<?php echo esc_attr( $wording['calendar_header_date'] ); ?>"><?php echo esc_html( $start_display ); ?></td>
								<td class="ms365-calendar__subject" data-label="<?php echo esc_attr( $wording['calendar_header_event'] ); ?>">
									<?php if ( $event_link ) : ?>
										<a href="<?php echo esc_url( $event_link ); ?>" rel="nofollow">
											<?php echo esc_html( $subject ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $subject ); ?>
									<?php endif; ?>
								</td>
								<td class="ms365-calendar__location" data-label="<?php echo esc_attr( $wording['calendar_header_location'] ); ?>"><?php echo esc_html( $location ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
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

		try {
			if ( preg_match( '/(Z|[+\-]\d{2}:\d{2})$/', (string) $date_time ) ) {
				$dt = new DateTimeImmutable( (string) $date_time );
			} else {
				$dt = new DateTimeImmutable( (string) $date_time, $display_tz );
			}
		} catch ( Exception $e ) {
			$timestamp = strtotime( (string) $date_time );
			if ( false === $timestamp ) {
				return '';
			}
			return wp_date( $format, $timestamp, $display_tz );
		}

		return wp_date( $format, $dt->getTimestamp(), $display_tz );
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
	// Shortcode: [ms365_files]
	// ------------------------------------------------------------------

	/**
	 * Render a OneDrive file listing.
	 *
	 * Attributes:
	 *   limit  – max number of items (default 10)
	 *   folder – OneDrive folder path (default: root)
	 *   title  – heading text (default "My Files")
	 *   show_headers – whether to render table headers (default true)
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_files( $atts ) {
		$wording = $this->get_shortcode_wording();

		$atts = shortcode_atts(
			array(
				'limit'  => 10,
				'folder' => '',
				'title'  => '',
				'show_headers' => 'true',
			),
			$atts,
			'ms365_files'
		);

		$show_headers = $this->shortcode_att_to_bool( $atts['show_headers'], true );

		if ( ! WP_MS365_Auth::is_connected() ) {
			return $this->not_connected_notice();
		}

		$folder = trim( (string) $atts['folder'] );
		$result = WP_MS365_Graph::get_drive_items( $folder, (int) $atts['limit'], WP_MS365_Graph::get_configured_user() );

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

		ob_start();
		?>
		<div class="ms365-files">
			<?php if ( $atts['title'] ) : ?>
				<h3 class="ms365-files__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<p class="ms365-files__empty"><?php echo esc_html( $wording['files_empty_text'] ); ?></p>
			<?php else : ?>
				<table class="ms365-table ms365-files__table">
					<?php if ( $show_headers ) : ?>
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html( $wording['files_header_file'] ); ?></th>
								<th scope="col"><?php echo esc_html( $wording['files_header_size'] ); ?></th>
								<th scope="col"><?php echo esc_html( $wording['files_header_modified'] ); ?></th>
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
							<tr class="ms365-files__item">
								<td class="ms365-files__name" data-label="<?php echo esc_attr( $wording['files_header_file'] ); ?>">
									<?php if ( $download_link ) : ?>
										<a href="<?php echo esc_url( $download_link ); ?>" rel="nofollow">
											<?php echo esc_html( $name ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $name ); ?>
									<?php endif; ?>
								</td>
								<td class="ms365-files__meta ms365-files__meta--size" data-label="<?php echo esc_attr( $wording['files_header_size'] ); ?>"><?php echo esc_html( $size ); ?></td>
								<td class="ms365-files__meta ms365-files__meta--modified" data-label="<?php echo esc_attr( $wording['files_header_modified'] ); ?>"><?php echo esc_html( $modified ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Shortcode: [ms365_profile]
	// ------------------------------------------------------------------

	/**
	 * Render the selected user profile snippet.
	 *
	 * @param  array $atts Shortcode attributes (none used currently).
	 * @return string HTML.
	 */
	public function render_profile( $atts ) {
		if ( ! WP_MS365_Auth::is_connected() ) {
			return $this->not_connected_notice();
		}

		$me = WP_MS365_Graph::get_target_user_profile();
		if ( is_wp_error( $me ) ) {
			return $this->error_notice( $me->get_error_message() );
		}

		$display_name = isset( $me['displayName'] ) ? $me['displayName'] : '';
		$email        = isset( $me['mail'] ) ? $me['mail'] : ( isset( $me['userPrincipalName'] ) ? $me['userPrincipalName'] : '' );
		$job_title    = isset( $me['jobTitle'] ) ? $me['jobTitle'] : '';

		ob_start();
		?>
		<div class="ms365-profile">
			<?php if ( $display_name ) : ?>
				<span class="ms365-profile__name"><?php echo esc_html( $display_name ); ?></span>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<span class="ms365-profile__email"><?php echo esc_html( $email ); ?></span>
			<?php endif; ?>
			<?php if ( $job_title ) : ?>
				<span class="ms365-profile__job-title"><?php echo esc_html( $job_title ); ?></span>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
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
			.ms365-table {
				width: 100%;
				border-collapse: collapse;
				border: 0;
			}
			.ms365-calendar__table th:first-child,
			.ms365-calendar__table td:first-child,
			.ms365-files__table th:nth-child(2),
			.ms365-files__table th:nth-child(3),
			.ms365-files__table td:nth-child(2),
			.ms365-files__table td:nth-child(3) {
				white-space: nowrap;
			}
			.ms365-table th,
			.ms365-table td {
				border: 0;
				padding: 0.3rem 0.5rem 0.3rem 0;
				text-align: left;
				vertical-align: top;
			}
			.ms365-files__table th:nth-child(2),
			.ms365-files__table th:nth-child(3),
			.ms365-files__table td:nth-child(2),
			.ms365-files__table td:nth-child(3) {
				text-align: right;
				padding-right: 0;
				padding-left: 0.75rem;
			}
			.ms365-table th {
				font-weight: 600;
			}
			@media (max-width: 640px) {
				.ms365-table,
				.ms365-table tbody,
				.ms365-table tr,
				.ms365-table td {
					display: block;
					width: 100%;
				}
				.ms365-table thead {
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
				.ms365-table tr {
					padding: 0.2rem 0;
				}
				.ms365-table td {
					padding: 0.18rem 0;
					text-align: left;
				}
				.ms365-table td::before {
					content: attr(data-label) ": ";
					font-weight: 600;
				}
				.ms365-files__table th:nth-child(2),
				.ms365-files__table th:nth-child(3),
				.ms365-files__table td:nth-child(2),
				.ms365-files__table td:nth-child(3) {
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
	 * Return a "not connected" notice HTML string.
	 *
	 * @return string
	 */
	private function not_connected_notice() {
		return '<p class="ms365-notice ms365-notice--warning">'
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
			'calendar_header_location' => __( 'Location', 'wp-ms365-graph' ),
			'files_empty_text'         => __( 'No files found.', 'wp-ms365-graph' ),
			'files_header_file'        => __( 'File', 'wp-ms365-graph' ),
			'files_header_size'        => __( 'Size', 'wp-ms365-graph' ),
			'files_header_modified'    => __( 'Modified', 'wp-ms365-graph' ),
		);

		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $settings[ $key ] ) && '' !== trim( (string) $settings[ $key ] ) ) {
				$defaults[ $key ] = (string) $settings[ $key ];
			}
		}

		return $defaults;
	}

	/**
	 * Return an error notice HTML string.
	 *
	 * @param  string $message Error message.
	 * @return string
	 */
	private function error_notice( $message ) {
		return '<p class="ms365-notice ms365-notice--error">'
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
