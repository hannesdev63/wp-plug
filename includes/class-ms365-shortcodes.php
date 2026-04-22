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

		$response = wp_remote_get(
			$download_url,
			array(
				'timeout'     => 60,
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( esc_html( $response->get_error_message() ), 500 );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			wp_die( esc_html__( 'Unable to download this file right now.', 'wp-ms365-graph' ), 502 );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			wp_die( esc_html__( 'Downloaded file was empty.', 'wp-ms365-graph' ), 404 );
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$disposition  = wp_remote_retrieve_header( $response, 'content-disposition' );
		$content_len  = wp_remote_retrieve_header( $response, 'content-length' );
		$safe_name    = str_replace( '"', '', $filename );

		nocache_headers();
		header( 'Content-Type: ' . ( $content_type ? $content_type : ( $mime_type ? $mime_type : 'application/octet-stream' ) ) );
		if ( $disposition ) {
			header( 'Content-Disposition: ' . $disposition );
		} else {
			header( 'Content-Disposition: attachment; filename="' . $safe_name . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		}
		if ( $content_len ) {
			header( 'Content-Length: ' . $content_len );
		}
		echo $body;
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
	 * Encode a local query param payload safely for URLs.
	 *
	 * @param  string $value Raw identifier.
	 * @return string
	 */
	private function encode_local_token_param( $value ) {
		return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode a local query param payload previously encoded with base64url.
	 *
	 * @param  string $key Query parameter key.
	 * @return string
	 */
	private function decode_local_token_param( $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return '';
		}

		$encoded = trim( (string) wp_unslash( $_GET[ $key ] ) );
		if ( '' === $encoded ) {
			return '';
		}

		$decoded = base64_decode( strtr( $encoded, '-_', '+/' ), true );
		if ( false !== $decoded ) {
			return trim( $decoded );
		}

		return '';
	}

	// ------------------------------------------------------------------
	// Shortcode: [ms365_calendar]
	// ------------------------------------------------------------------

	/**
	 * Render upcoming Microsoft 365 calendar events.
	 *
	 * Attributes:
	 *   limit    – max number of events (default 5)
	 *   timezone – IANA timezone string (default UTC)
	 *   title    – heading text (default "Upcoming Events")
	 *   calendar_link_mode – link behavior: "ics" or "none" (default "ics")
	 *   categories – comma-separated category names to include (default: all)
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'              => 5,
				'timezone'           => 'UTC',
				'title'              => '',
				'calendar_link_mode' => 'ics',
				'categories'         => '',
			),
			$atts,
			'ms365_calendar'
		);

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
				<p class="ms365-calendar__empty"><?php esc_html_e( 'No upcoming events found.', 'wp-ms365-graph' ); ?></p>
			<?php else : ?>
				<ul class="ms365-calendar__list">
					<?php foreach ( $items as $event ) : ?>
						<?php
						$subject  = isset( $event['subject'] ) ? $event['subject'] : __( '(No subject)', 'wp-ms365-graph' );
						$start    = isset( $event['start']['dateTime'] ) ? $event['start']['dateTime'] : '';
						$end      = isset( $event['end']['dateTime'] )   ? $event['end']['dateTime']   : '';
						$location = isset( $event['location']['displayName'] ) ? $event['location']['displayName'] : '';
						$event_link = '';
						if ( 'ics' === $link_mode && isset( $event['id'] ) && '' !== (string) $event['id'] ) {
							$event_link = add_query_arg( 'ms365_calendar_ics', $this->encode_local_token_param( (string) $event['id'] ), home_url( '/' ) );
						}
						$all_day  = ! empty( $event['isAllDay'] );

						$start_display = $start
							? ( $all_day
								? date_i18n( get_option( 'date_format' ), strtotime( $start ) )
								: date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start ) ) )
							: '';
						?>
						<li class="ms365-calendar__item">
							<span class="ms365-calendar__date"><?php echo esc_html( $start_display ); ?></span>
							<?php if ( $event_link ) : ?>
								<a class="ms365-calendar__subject" href="<?php echo esc_url( $event_link ); ?>" rel="nofollow">
									<?php echo esc_html( $subject ); ?>
								</a>
							<?php else : ?>
								<span class="ms365-calendar__subject"><?php echo esc_html( $subject ); ?></span>
							<?php endif; ?>
							<?php if ( $location ) : ?>
								<span class="ms365-calendar__location"><?php echo esc_html( $location ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
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
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_files( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'  => 10,
				'folder' => '',
				'title'  => '',
			),
			$atts,
			'ms365_files'
		);

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
				<p class="ms365-files__empty"><?php esc_html_e( 'No files found.', 'wp-ms365-graph' ); ?></p>
			<?php else : ?>
				<ul class="ms365-files__list">
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
						<li class="ms365-files__item">
							<span class="ms365-files__icon ms365-files__icon--file"></span>
							<?php if ( $download_link ) : ?>
								<a class="ms365-files__name" href="<?php echo esc_url( $download_link ); ?>" rel="nofollow">
									<?php echo esc_html( $name ); ?>
								</a>
							<?php else : ?>
								<span class="ms365-files__name"><?php echo esc_html( $name ); ?></span>
							<?php endif; ?>
							<?php if ( $size ) : ?>
								<span class="ms365-files__meta"><?php echo esc_html( $size ); ?></span>
							<?php endif; ?>
							<?php if ( $modified ) : ?>
								<span class="ms365-files__meta"><?php echo esc_html( $modified ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
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
