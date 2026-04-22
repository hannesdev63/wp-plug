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
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_calendar( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => 5,
				'timezone' => 'UTC',
				'title'    => __( 'Upcoming Events', 'wp-ms365-graph' ),
			),
			$atts,
			'ms365_calendar'
		);

		if ( ! WP_MS365_Auth::is_connected() ) {
			return $this->not_connected_notice();
		}

		$events = WP_MS365_Graph::get_calendar_events( (int) $atts['limit'], $atts['timezone'] );

		if ( is_wp_error( $events ) ) {
			return $this->error_notice( $events->get_error_message() );
		}

		$items = isset( $events['value'] ) ? $events['value'] : array();

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
						$web_link = isset( $event['webLink'] ) ? $event['webLink'] : '';
						$all_day  = ! empty( $event['isAllDay'] );

						$start_display = $start
							? ( $all_day
								? date_i18n( get_option( 'date_format' ), strtotime( $start ) )
								: date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start ) ) )
							: '';
						?>
						<li class="ms365-calendar__item">
							<span class="ms365-calendar__date"><?php echo esc_html( $start_display ); ?></span>
							<?php if ( $web_link ) : ?>
								<a class="ms365-calendar__subject" href="<?php echo esc_url( $web_link ); ?>" target="_blank" rel="noopener noreferrer">
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
				'title'  => __( 'My Files', 'wp-ms365-graph' ),
			),
			$atts,
			'ms365_files'
		);

		if ( ! WP_MS365_Auth::is_connected() ) {
			return $this->not_connected_notice();
		}

		$result = WP_MS365_Graph::get_drive_items( $atts['folder'], (int) $atts['limit'], WP_MS365_Graph::get_configured_user() );

		if ( is_wp_error( $result ) ) {
			return $this->error_notice( $result->get_error_message() );
		}

		$items = isset( $result['value'] ) ? $result['value'] : array();

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
						$web_url  = isset( $item['webUrl'] ) ? $item['webUrl'] : '';
						$is_folder = isset( $item['folder'] );
						$size     = ! $is_folder && isset( $item['size'] ) ? self::format_bytes( $item['size'] ) : '';
						$modified = isset( $item['lastModifiedDateTime'] )
							? date_i18n( get_option( 'date_format' ), strtotime( $item['lastModifiedDateTime'] ) )
							: '';
						$icon_class = $is_folder ? 'ms365-files__icon--folder' : 'ms365-files__icon--file';
						?>
						<li class="ms365-files__item">
							<span class="ms365-files__icon <?php echo esc_attr( $icon_class ); ?>"></span>
							<?php if ( $web_url ) : ?>
								<a class="ms365-files__name" href="<?php echo esc_url( $web_url ); ?>" target="_blank" rel="noopener noreferrer">
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
		wp_enqueue_style(
			'wp-ms365-graph',
			WP_MS365_PLUGIN_URL . 'assets/css/ms365.css',
			array(),
			WP_MS365_VERSION
		);
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
