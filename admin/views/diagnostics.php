<?php
/**
 * Diagnostics page view for troubleshooting.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected       = WP_MS365_Auth::is_connected();
$settings           = WP_MS365_Auth::get_settings();
$logs               = WP_MS365_Logger::get_logs();
$debug_enabled      = defined( 'WP_DEBUG' ) && WP_DEBUG;
$configured_user    = WP_MS365_Graph::get_configured_user();
$mail_test_status   = isset( $_GET['mail_test'] ) ? sanitize_key( wp_unslash( $_GET['mail_test'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$mail_test_reason   = isset( $_GET['reason'] ) ? sanitize_text_field( wp_unslash( $_GET['reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$admin_email        = sanitize_email( (string) get_option( 'admin_email', '' ) );
$allowed_logs_per_page = array( 25, 50, 100 );
$logs_per_page         = isset( $_GET['logs_per_page'] ) ? absint( wp_unslash( $_GET['logs_per_page'] ) ) : 25; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $logs_per_page, $allowed_logs_per_page, true ) ) {
	$logs_per_page = 25;
}
$logs_total         = count( $logs );
$log_page           = isset( $_GET['log_page'] ) ? max( 1, absint( wp_unslash( $_GET['log_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$logs_total_pages   = max( 1, (int) ceil( $logs_total / $logs_per_page ) );

if ( $log_page > $logs_total_pages ) {
	$log_page = $logs_total_pages;
}

$logs_sorted = array_reverse( $logs );
$log_offset  = ( $log_page - 1 ) * $logs_per_page;
$logs_page_items = array_slice( $logs_sorted, $log_offset, $logs_per_page );

// Live Graph checks – only run when connected and a specific user is configured.
$diag_user_result     = null;
$diag_calendar_result = null;
$diag_drive_result    = null;
if ( $is_connected && '' !== $configured_user ) {
	$diag_user_result     = WP_MS365_Graph::get_target_user_profile();
	$diag_calendar_result = WP_MS365_Graph::get_user_calendar_info();
	$diag_drive_result    = WP_MS365_Graph::get( WP_MS365_Graph::get_user_endpoint_prefix() . '/drive?$select=id,driveType,quota' );
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$clear_logs = isset( $_GET['clear_logs'] ) && '1' === $_GET['clear_logs'];
if ( $clear_logs && check_admin_referer( 'wp_ms365_clear_logs' ) ) {
	WP_MS365_Logger::clear_logs();
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared.', 'wp-ms365-graph' ) . '</p></div>';
}
// phpcs:enable
?>

<div class="wrap msgraph_diagnostics">
	<h1 class="msgraph_diagnostics__heading">
		<img src="<?php echo esc_url( WP_MS365_Admin::get_icon_url() ); ?>" class="msgraph_page-icon" alt="" width="28" height="28" />
		<?php esc_html_e( 'ESC Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Diagnostics', 'wp-ms365-graph' ); ?>
	</h1>

	<?php if ( 'success' === $mail_test_status ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php printf( esc_html__( 'Test email sent successfully to %s.', 'wp-ms365-graph' ), esc_html( $admin_email ) ); ?></p>
		</div>
	<?php elseif ( 'error' === $mail_test_status ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				echo esc_html__( 'Test email failed.', 'wp-ms365-graph' );
				if ( 'invalid_recipient' === $mail_test_reason ) {
					echo ' ' . esc_html__( 'Admin email address is not configured or invalid.', 'wp-ms365-graph' );
				} elseif ( '' !== $mail_test_reason ) {
					echo ' ' . esc_html( $mail_test_reason );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="msgraph_card">
		<h2><?php esc_html_e( 'Email Transport Test', 'wp-ms365-graph' ); ?></h2>
		<p>
			<?php esc_html_e( 'Send a test email using WordPress mail flow (wp_mail). If Graph mail transport is enabled, this test will go through Microsoft Graph.', 'wp-ms365-graph' ); ?>
		</p>
		<p>
			<?php echo esc_html__( 'Recipient:', 'wp-ms365-graph' ); ?> <code><?php echo esc_html( $admin_email ); ?></code>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wp_ms365_send_test_email" />
			<?php wp_nonce_field( 'wp_ms365_send_test_email' ); ?>
			<?php submit_button( __( 'Send Test Email', 'wp-ms365-graph' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>

	<!-- System Information -->
	<div class="msgraph_card">
		<h2><?php esc_html_e( 'System Information', 'wp-ms365-graph' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Plugin Version', 'wp-ms365-graph' ); ?></th>
				<td><code><?php echo esc_html( WP_MS365_VERSION ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'WordPress Version', 'wp-ms365-graph' ); ?></th>
				<td><code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'PHP Version', 'wp-ms365-graph' ); ?></th>
				<td><code><?php echo esc_html( phpversion() ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'WP_DEBUG Enabled', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( $debug_enabled ) : ?>
						<span style="color: green;">✓ <?php esc_html_e( 'Yes', 'wp-ms365-graph' ); ?></span>
					<?php else : ?>
						<span style="color: red;">✗ <?php esc_html_e( 'No', 'wp-ms365-graph' ); ?></span>
						<p class="description"><?php esc_html_e( 'Enable WP_DEBUG in wp-config.php to see debug logs.', 'wp-ms365-graph' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<!-- Authentication Configuration -->
	<div class="msgraph_card">
		<h2><?php esc_html_e( 'Authentication Configuration', 'wp-ms365-graph' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Connected', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( $is_connected ) : ?>
						<span style="color: green;">✓ <?php esc_html_e( 'Yes', 'wp-ms365-graph' ); ?></span>
					<?php else : ?>
						<span style="color: red;">✗ <?php esc_html_e( 'No', 'wp-ms365-graph' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tenant ID Configured', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( ! empty( $settings['tenant_id'] ) ) : ?>
						<span style="color: green;">✓ <?php esc_html_e( 'Yes', 'wp-ms365-graph' ); ?></span>
						<br /><code><?php echo esc_html( $settings['tenant_id'] ); ?></code>
					<?php else : ?>
						<span style="color: red;">✗ <?php esc_html_e( 'No', 'wp-ms365-graph' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Client ID Configured', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( ! empty( $settings['client_id'] ) ) : ?>
						<span style="color: green;">✓ <?php esc_html_e( 'Yes', 'wp-ms365-graph' ); ?></span>
						<br /><code><?php echo esc_html( $settings['client_id'] ); ?></code>
					<?php else : ?>
						<span style="color: red;">✗ <?php esc_html_e( 'No', 'wp-ms365-graph' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Client Secret Configured', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( ! empty( $settings['client_secret'] ) ) : ?>
						<span style="color: green;">✓ <?php esc_html_e( 'Yes', 'wp-ms365-graph' ); ?></span>
						<p class="description"><?php esc_html_e( '(value hidden)', 'wp-ms365-graph' ); ?></p>
					<?php else : ?>
						<span style="color: red;">✗ <?php esc_html_e( 'No', 'wp-ms365-graph' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Specific User Configured', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( ! empty( $configured_user ) ) : ?>
						<span style="color: green;">✓ <?php esc_html_e( 'Yes', 'wp-ms365-graph' ); ?></span>
						<br /><code><?php echo esc_html( $configured_user ); ?></code>
					<?php else : ?>
						<span style="color: red;">✗ <?php esc_html_e( 'Not set (required for app-only mode)', 'wp-ms365-graph' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<!-- Live Graph Checks -->
	<?php if ( '' !== $configured_user ) : ?>
	<div class="msgraph_card">
		<h2><?php esc_html_e( 'Live Graph Checks', 'wp-ms365-graph' ); ?></h2>
		<?php if ( ! $is_connected ) : ?>
			<p class="description"><?php esc_html_e( 'Connect the plugin first to run live checks.', 'wp-ms365-graph' ); ?></p>
		<?php else : ?>
		<table class="form-table">

			<!-- Check 1: user exists -->
			<tr>
				<th scope="row"><?php esc_html_e( 'User exists in Entra ID', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( is_wp_error( $diag_user_result ) ) : ?>
						<span style="color:red;">✗ <?php esc_html_e( 'Failed', 'wp-ms365-graph' ); ?></span>
						<p class="description"><?php echo esc_html( $diag_user_result->get_error_message() ); ?></p>
					<?php else : ?>
						<span style="color:green;">✓ <?php esc_html_e( 'Found', 'wp-ms365-graph' ); ?></span>
						<?php
						$display_name = isset( $diag_user_result['displayName'] ) ? $diag_user_result['displayName'] : '';
						$upn          = isset( $diag_user_result['userPrincipalName'] ) ? $diag_user_result['userPrincipalName'] : '';
						$object_id    = isset( $diag_user_result['id'] ) ? $diag_user_result['id'] : '';
						?>
						<p class="description">
							<?php if ( $display_name ) echo esc_html( $display_name ) . ' &mdash; '; ?>
							<?php if ( $upn ) echo esc_html( $upn ); ?>
							<?php if ( $object_id ) : ?>
								<br /><code><?php echo esc_html( $object_id ); ?></code>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Check 2: calendar provisioned -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Exchange Online / Calendar provisioned', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( is_wp_error( $diag_calendar_result ) ) :
						$cal_msg    = $diag_calendar_result->get_error_message();
						$cal_data   = $diag_calendar_result->get_error_data();
						$cal_status = is_array( $cal_data ) && isset( $cal_data['status'] ) ? (int) $cal_data['status'] : 0;
					?>
						<span style="color:red;">✗ <?php esc_html_e( 'Not available', 'wp-ms365-graph' ); ?></span>
						<p class="description"><?php echo esc_html( $cal_msg ); ?></p>
						<?php if ( 403 === $cal_status ) : ?>
							<p class="description" style="color:red;">
								<?php esc_html_e( '→ Missing permission. Add Microsoft Graph application permission Calendars.Read to your Azure app and grant admin consent.', 'wp-ms365-graph' ); ?>
							</p>
						<?php elseif ( 404 === $cal_status || 0 === $cal_status ) : ?>
							<p class="description" style="color:orange;">
								<?php esc_html_e( '→ License is assigned but Exchange has not initialized the mailbox yet. Have the user open Outlook on the web (outlook.office.com) once — that triggers mailbox creation. Allow up to 24 h after license assignment.', 'wp-ms365-graph' ); ?>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<span style="color:green;">✓ <?php esc_html_e( 'Provisioned', 'wp-ms365-graph' ); ?></span>
						<?php if ( ! empty( $diag_calendar_result['name'] ) ) : ?>
							<p class="description"><?php echo esc_html( $diag_calendar_result['name'] ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Check 3: OneDrive provisioned -->
			<tr>
				<th scope="row"><?php esc_html_e( 'OneDrive provisioned', 'wp-ms365-graph' ); ?></th>
				<td>
					<?php if ( is_wp_error( $diag_drive_result ) ) :
						$drv_msg    = $diag_drive_result->get_error_message();
						$drv_data   = $diag_drive_result->get_error_data();
						$drv_status = is_array( $drv_data ) && isset( $drv_data['status'] ) ? (int) $drv_data['status'] : 0;
					?>
						<span style="color:red;">✗ <?php esc_html_e( 'Not available', 'wp-ms365-graph' ); ?></span>
						<p class="description"><?php echo esc_html( $drv_msg ); ?></p>
						<?php if ( 403 === $drv_status ) : ?>
							<p class="description" style="color:red;">
								<?php esc_html_e( '→ Missing permission. Ensure Microsoft Graph application permission Files.Read.All is added to your Azure app with admin consent.', 'wp-ms365-graph' ); ?>
							</p>
						<?php else : ?>
							<p class="description" style="color:orange;">
								<?php esc_html_e( '→ OneDrive not initialized. Have the user open OneDrive (onedrive.live.com or SharePoint) once to trigger drive creation. Allow up to 24 h after license assignment.', 'wp-ms365-graph' ); ?>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<span style="color:green;">✓ <?php esc_html_e( 'Provisioned', 'wp-ms365-graph' ); ?></span>
						<?php
						$used  = isset( $diag_drive_result['quota']['used'] ) ? (int) $diag_drive_result['quota']['used'] : null;
						$total = isset( $diag_drive_result['quota']['total'] ) ? (int) $diag_drive_result['quota']['total'] : null;
						?>
						<?php if ( null !== $used && null !== $total ) : ?>
							<p class="description">
								<?php echo esc_html( WP_MS365_Shortcodes::format_bytes_public( $used ) . ' / ' . WP_MS365_Shortcodes::format_bytes_public( $total ) ); ?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</td>
			</tr>

		</table>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Application Scope -->
	<div class="msgraph_card">
		<h2><?php esc_html_e( 'Token Scope', 'wp-ms365-graph' ); ?></h2>
		<p><?php esc_html_e( 'The plugin requests an app-only token using this scope:', 'wp-ms365-graph' ); ?></p>
		<code><?php echo esc_html( WP_MS365_Auth::SCOPES ); ?></code>
		<p class="description">
			<?php esc_html_e( 'Your Azure app registration must include these Microsoft Graph application permissions:', 'wp-ms365-graph' ); ?>
			<br />
			<code>User.Read.All</code>, <code>Calendars.Read</code>, <code>Files.Read.All</code>, <code>Sites.Read.All</code>
		</p>
	</div>

	<!-- Debug Logs -->
	<div class="msgraph_card">
		<h2><?php esc_html_e( 'Debug Logs', 'wp-ms365-graph' ); ?></h2>

		<?php if ( ! $debug_enabled ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'WP_DEBUG is not enabled. To view logs, add the following to wp-config.php:', 'wp-ms365-graph' ); ?>
					<br /><code>define( 'WP_DEBUG', true );</code>
				</p>
			</div>
		<?php endif; ?>

		<p>
			<?php if ( ! empty( $logs ) ) : ?>
				<strong><?php printf( esc_html__( 'Total log entries: %d', 'wp-ms365-graph' ), count( $logs ) ); ?></strong>
			<?php else : ?>
				<strong><?php esc_html_e( 'No logs available.', 'wp-ms365-graph' ); ?></strong>
			<?php endif; ?>
		</p>

		<?php if ( ! empty( $logs ) ) : ?>
			<p>
				<?php
				$clear_logs_url = wp_nonce_url(
					add_query_arg(
						array(
							'clear_logs'     => '1',
							'log_page'       => $log_page,
							'logs_per_page'  => $logs_per_page,
						)
					),
					'wp_ms365_clear_logs'
				);
				?>
				<a href="<?php echo esc_url( $clear_logs_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Clear Logs', 'wp-ms365-graph' ); ?>
				</a>
			</p>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin:0 0 12px 0; display:flex; gap:8px; align-items:center;">
				<input type="hidden" name="page" value="wp-ms365-graph" />
				<input type="hidden" name="tab" value="diagnostics" />
				<input type="hidden" name="log_page" value="1" />
				<label for="wp_ms365_logs_per_page"><strong><?php esc_html_e( 'Rows per page', 'wp-ms365-graph' ); ?></strong></label>
				<select name="logs_per_page" id="wp_ms365_logs_per_page">
					<?php foreach ( $allowed_logs_per_page as $page_size ) : ?>
						<option value="<?php echo esc_attr( (string) $page_size ); ?>"<?php selected( $logs_per_page, $page_size ); ?>><?php echo esc_html( (string) $page_size ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Apply', 'wp-ms365-graph' ); ?></button>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'wp-ms365-graph' ); ?></th>
						<th><?php esc_html_e( 'Level', 'wp-ms365-graph' ); ?></th>
						<th><?php esc_html_e( 'Message', 'wp-ms365-graph' ); ?></th>
						<th><?php esc_html_e( 'Context', 'wp-ms365-graph' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs_page_items as $entry ) : ?>
						<?php
						$level_class = isset( $entry['level'] ) ? strtolower( (string) $entry['level'] ) : 'info';
						$context_str = ! empty( $entry['context'] ) ? wp_json_encode( $entry['context'] ) : '—';
						?>
						<tr class="log-level-<?php echo esc_attr( $level_class ); ?>">
							<td><?php echo esc_html( isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '' ); ?></td>
							<td><code><?php echo esc_html( isset( $entry['level'] ) ? (string) $entry['level'] : '' ); ?></code></td>
							<td><?php echo esc_html( isset( $entry['message'] ) ? (string) $entry['message'] : '' ); ?></td>
							<td><code><?php echo esc_html( $context_str ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $logs_total_pages > 1 ) : ?>
				<?php
				$prev_page_url = add_query_arg(
					array(
						'log_page'      => max( 1, $log_page - 1 ),
						'logs_per_page' => $logs_per_page,
					)
				);
				$next_page_url = add_query_arg(
					array(
						'log_page'      => min( $logs_total_pages, $log_page + 1 ),
						'logs_per_page' => $logs_per_page,
					)
				);
				?>
				<p style="margin-top:12px; display:flex; gap:8px; align-items:center;">
					<?php if ( $log_page > 1 ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $prev_page_url ); ?>">
							<?php esc_html_e( 'Previous', 'wp-ms365-graph' ); ?>
						</a>
					<?php else : ?>
						<button class="button button-secondary" type="button" disabled><?php esc_html_e( 'Previous', 'wp-ms365-graph' ); ?></button>
					<?php endif; ?>

					<span>
						<?php
						printf(
							esc_html__( 'Page %1$d of %2$d', 'wp-ms365-graph' ),
							(int) $log_page,
							(int) $logs_total_pages
						);
						?>
					</span>

					<?php if ( $log_page < $logs_total_pages ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $next_page_url ); ?>">
							<?php esc_html_e( 'Next', 'wp-ms365-graph' ); ?>
						</a>
					<?php else : ?>
						<button class="button button-secondary" type="button" disabled><?php esc_html_e( 'Next', 'wp-ms365-graph' ); ?></button>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<!-- Troubleshooting Tips -->
	<div class="msgraph_card">
		<h2><?php esc_html_e( 'Troubleshooting Tips', 'wp-ms365-graph' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'After changing app permissions in Azure, grant admin consent and clear plugin connection once so a new app-only token is fetched.', 'wp-ms365-graph' ); ?></li>
			<li><?php esc_html_e( 'Calendar and OneDrive require the target user to sign in to Outlook/OneDrive at least once after license assignment.', 'wp-ms365-graph' ); ?></li>
			<li><?php esc_html_e( 'Use Microsoft Graph application permissions (not delegated) for no-user-interaction access.', 'wp-ms365-graph' ); ?></li>
			<li><?php esc_html_e( 'If the wrong user\'s data shows up, verify the Specific User field contains the correct UPN (user@domain.com).', 'wp-ms365-graph' ); ?></li>
		</ul>
	</div>

</div>
