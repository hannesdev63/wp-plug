<?php
/**
 * Calendar Explorer admin page.
 *
 * Lists all calendars the configured user can access and provides ready-made
 * shortcode snippets for each calendar ID.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected = WP_MS365_Auth::is_connected();
$user_id      = WP_MS365_Graph::get_configured_user();
$calendars    = null;

if ( $is_connected ) {
	$calendars = WP_MS365_Graph::get_user_calendars( $user_id );
}
?>
<div class="wrap msgraph_calendar-explorer">
	<h1 class="msgraph_calendar-explorer__heading">
		<img src="<?php echo esc_url( WP_MS365_Admin::get_icon_url() ); ?>" class="msgraph_page-icon" alt="" width="28" height="28" />
		<?php esc_html_e( 'ESC Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Calendar Explorer', 'wp-ms365-graph' ); ?>
	</h1>

	<p><?php esc_html_e( 'Use this page to copy an [msgraph_calendar] shortcode for any calendar the configured user can access. The app needs Microsoft Graph application permission Calendars.Read with admin consent to list calendars.', 'wp-ms365-graph' ); ?></p>

	<?php if ( ! $is_connected ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Not connected to Microsoft 365. Configure credentials in Settings before using this page.', 'wp-ms365-graph' ); ?></p>
		</div>
	<?php else : ?>

		<?php if ( is_wp_error( $calendars ) ) : ?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Could not retrieve calendars:', 'wp-ms365-graph' ); ?></strong>
					<?php echo esc_html( $calendars->get_error_message() ); ?>
				</p>
			</div>
		<?php elseif ( empty( $calendars['value'] ) ) : ?>
			<p class="description"><?php esc_html_e( 'No calendars found. Ensure the app has Microsoft Graph application permission Calendars.Read and that admin consent has been granted.', 'wp-ms365-graph' ); ?></p>
		<?php else : ?>

			<?php $calendar_count = count( $calendars['value'] ); ?>
			<p class="description" id="ms365-calendar-results-meta" data-total-calendars="<?php echo esc_attr( $calendar_count ); ?>">
				<?php
				printf(
					/* translators: %d: number of calendars found */
					esc_html( _n( 'Found %d calendar.', 'Found %d calendars.', $calendar_count, 'wp-ms365-graph' ) ),
					$calendar_count
				);
				?>
			</p>

			<?php foreach ( $calendars['value'] as $calendar ) : ?>
				<?php
				$calendar_id      = isset( $calendar['id'] ) ? (string) $calendar['id'] : '';
				$calendar_name    = isset( $calendar['name'] ) ? (string) $calendar['name'] : __( 'Untitled calendar', 'wp-ms365-graph' );
				$calendar_default = ! empty( $calendar['isDefaultCalendar'] );
				$owner_label      = '';

				if ( isset( $calendar['owner']['emailAddress']['name'] ) && '' !== trim( (string) $calendar['owner']['emailAddress']['name'] ) ) {
					$owner_label = (string) $calendar['owner']['emailAddress']['name'];
				} elseif ( isset( $calendar['owner']['emailAddress']['address'] ) && '' !== trim( (string) $calendar['owner']['emailAddress']['address'] ) ) {
					$owner_label = (string) $calendar['owner']['emailAddress']['address'];
				}

				if ( '' === $calendar_id ) {
					continue;
				}

				$snippet = '[msgraph_calendar calendar="' . $calendar_id . '"]';
				?>
				<div class="msgraph_card msgraph_calendar-explorer__calendar" data-calendar-id="<?php echo esc_attr( $calendar_id ); ?>" style="margin-bottom:1.5em;">
					<h3 style="margin-top:0;">
						<?php echo esc_html( $calendar_name ); ?>
						<?php if ( $calendar_default ) : ?>
							<span class="description" style="font-weight:normal;margin-left:.5em;">
								<?php esc_html_e( 'Default calendar', 'wp-ms365-graph' ); ?>
							</span>
						<?php endif; ?>
					</h3>

					<table class="form-table msgraph_calendar-explorer__meta" style="margin-top:0;">
						<?php if ( $owner_label ) : ?>
							<tr>
								<th scope="row" style="width:120px;"><?php esc_html_e( 'Owner', 'wp-ms365-graph' ); ?></th>
								<td><?php echo esc_html( $owner_label ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<th scope="row" style="width:120px;"><?php esc_html_e( 'calendar_id', 'wp-ms365-graph' ); ?></th>
							<td>
								<code class="msgraph_calendar-explorer__copyable"><?php echo esc_html( $calendar_id ); ?></code>
								<button
									type="button"
									class="button button-small msgraph_copy-btn"
									data-copy="<?php echo esc_attr( $snippet ); ?>"
									title="<?php esc_attr_e( 'Copy shortcode', 'wp-ms365-graph' ); ?>"
								><?php esc_html_e( 'Copy shortcode', 'wp-ms365-graph' ); ?></button>
							</td>
						</tr>
						<tr>
							<th scope="row" style="width:120px;"><?php esc_html_e( 'Shortcode', 'wp-ms365-graph' ); ?></th>
							<td>
								<code style="word-break:break-all;"><?php echo esc_html( $snippet ); ?></code>
							</td>
						</tr>
					</table>
				</div>
			<?php endforeach; ?>

		<?php endif; ?>

	<?php endif; ?>
</div>

<script>
( function () {
	function setCopyFeedback( btn ) {
		var orig = btn.textContent;
		btn.textContent = '✓';
		setTimeout( function () { btn.textContent = orig; }, 1200 );
	}

	function copyText( text, btn ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( function () {
				setCopyFeedback( btn );
			} );
			return;
		}

		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity  = '0';
		document.body.appendChild( ta );
		ta.select();
		document.execCommand( 'copy' );
		document.body.removeChild( ta );
		setCopyFeedback( btn );
	}

	document.addEventListener( 'click', function ( evt ) {
		var btn = evt.target.closest( '.msgraph_copy-btn' );
		if ( ! btn ) {
			return;
		}

		var text = btn.getAttribute( 'data-copy' );
		if ( text ) {
			copyText( text, btn );
		}
	} );
}() );
</script>