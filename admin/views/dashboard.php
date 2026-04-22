<?php
/**
 * Admin dashboard page view – live data preview.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected = WP_MS365_Auth::is_connected();
?>
<div class="wrap ms365-dashboard">
	<h1 class="ms365-dashboard__heading">
		<span class="dashicons dashicons-microsoft"></span>
		<?php esc_html_e( 'Microsoft 365 Graph', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Dashboard', 'wp-ms365-graph' ); ?>
	</h1>

	<?php if ( ! $is_connected ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: settings page link */
					esc_html__( 'Microsoft 365 is not connected. Please %s first.', 'wp-ms365-graph' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wp-ms365-graph' ) ) . '">'
						. esc_html__( 'configure the plugin', 'wp-ms365-graph' )
					. '</a>'
				);
				?>
			</p>
		</div>
	<?php else : ?>

		<!-- User profile card -->
		<?php $me = WP_MS365_Graph::get_me(); ?>
		<?php if ( ! is_wp_error( $me ) ) : ?>
		<div class="ms365-card ms365-card--profile">
			<h2 class="ms365-card__title"><?php esc_html_e( 'Signed-in User', 'wp-ms365-graph' ); ?></h2>
			<p>
				<strong><?php echo esc_html( isset( $me['displayName'] ) ? $me['displayName'] : '' ); ?></strong><br />
				<?php echo esc_html( isset( $me['mail'] ) ? $me['mail'] : ( isset( $me['userPrincipalName'] ) ? $me['userPrincipalName'] : '' ) ); ?><br />
				<?php if ( ! empty( $me['jobTitle'] ) ) : ?>
					<em><?php echo esc_html( $me['jobTitle'] ); ?></em>
				<?php endif; ?>
			</p>
		</div>
		<?php endif; ?>

		<!-- Calendar events -->
		<div class="ms365-card ms365-card--calendar">
			<h2 class="ms365-card__title"><?php esc_html_e( 'Upcoming Calendar Events (next 30 days)', 'wp-ms365-graph' ); ?></h2>
			<?php
			$events = WP_MS365_Graph::get_calendar_events( 5 );
			if ( is_wp_error( $events ) ) :
			?>
				<p class="ms365-notice ms365-notice--error"><?php echo esc_html( $events->get_error_message() ); ?></p>
			<?php elseif ( empty( $events['value'] ) ) : ?>
				<p><?php esc_html_e( 'No upcoming events.', 'wp-ms365-graph' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'wp-ms365-graph' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'wp-ms365-graph' ); ?></th>
							<th><?php esc_html_e( 'Location', 'wp-ms365-graph' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $events['value'] as $event ) : ?>
						<tr>
							<td>
								<?php
								$start = isset( $event['start']['dateTime'] ) ? $event['start']['dateTime'] : '';
								echo esc_html( $start ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start ) ) : '' );
								?>
							</td>
							<td>
								<?php
								$subject  = isset( $event['subject'] ) ? $event['subject'] : __( '(No subject)', 'wp-ms365-graph' );
								$web_link = isset( $event['webLink'] ) ? $event['webLink'] : '';
								if ( $web_link ) :
								?>
									<a href="<?php echo esc_url( $web_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $subject ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $subject ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( isset( $event['location']['displayName'] ) ? $event['location']['displayName'] : '' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- OneDrive files -->
		<div class="ms365-card ms365-card--files">
			<h2 class="ms365-card__title"><?php esc_html_e( 'OneDrive Root Files', 'wp-ms365-graph' ); ?></h2>
			<?php
			$drive = WP_MS365_Graph::get_drive_items( '', 10 );
			if ( is_wp_error( $drive ) ) :
			?>
				<p class="ms365-notice ms365-notice--error"><?php echo esc_html( $drive->get_error_message() ); ?></p>
			<?php elseif ( empty( $drive['value'] ) ) : ?>
				<p><?php esc_html_e( 'No files found.', 'wp-ms365-graph' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'wp-ms365-graph' ); ?></th>
							<th><?php esc_html_e( 'Size', 'wp-ms365-graph' ); ?></th>
							<th><?php esc_html_e( 'Modified', 'wp-ms365-graph' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $drive['value'] as $item ) : ?>
						<tr>
							<td>
								<?php
								$name    = isset( $item['name'] ) ? $item['name'] : '';
								$web_url = isset( $item['webUrl'] ) ? $item['webUrl'] : '';
								$is_dir  = isset( $item['folder'] );
								echo $is_dir ? '<span class="dashicons dashicons-portfolio"></span> ' : '<span class="dashicons dashicons-media-default"></span> ';
								if ( $web_url ) :
								?>
									<a href="<?php echo esc_url( $web_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $name ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $name ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php
								if ( ! $is_dir && isset( $item['size'] ) ) {
									echo esc_html( WP_MS365_Shortcodes::format_bytes_public( $item['size'] ) );
								} else {
									esc_html_e( '—', 'wp-ms365-graph' );
								}
								?>
							</td>
							<td>
								<?php
								$mod = isset( $item['lastModifiedDateTime'] ) ? $item['lastModifiedDateTime'] : '';
								echo esc_html( $mod ? date_i18n( get_option( 'date_format' ), strtotime( $mod ) ) : '' );
								?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	<?php endif; ?>
</div>
