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

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$clear_logs = isset( $_GET['clear_logs'] ) && '1' === $_GET['clear_logs'];
if ( $clear_logs && check_admin_referer( 'wp_ms365_clear_logs' ) ) {
	WP_MS365_Logger::clear_logs();
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Logs cleared.', 'wp-ms365-graph' ) . '</p></div>';
}
// phpcs:enable
?>

<div class="wrap ms365-diagnostics">
	<h1 class="ms365-diagnostics__heading">
		<span class="dashicons dashicons-tools"></span>
		<?php esc_html_e( 'Microsoft 365 Graph', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Diagnostics', 'wp-ms365-graph' ); ?>
	</h1>

	<!-- System Information -->
	<div class="ms365-card">
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

	<!-- OAuth Configuration -->
	<div class="ms365-card">
		<h2><?php esc_html_e( 'OAuth Configuration', 'wp-ms365-graph' ); ?></h2>
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
						<span style="color: orange;">⚠ <?php esc_html_e( 'Not set (using signed-in user)', 'wp-ms365-graph' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<!-- OAuth Scopes -->
	<div class="ms365-card">
		<h2><?php esc_html_e( 'OAuth Scopes Requested', 'wp-ms365-graph' ); ?></h2>
		<p><?php esc_html_e( 'These scopes are requested during the OAuth flow:', 'wp-ms365-graph' ); ?></p>
		<code><?php echo esc_html( WP_MS365_Auth::SCOPES ); ?></code>
		<p class="description">
			<?php esc_html_e( 'If you are accessing another user\'s profile/calendar/OneDrive, make sure the following scopes are present in your Azure app registration:', 'wp-ms365-graph' ); ?>
			<br />
			<code>User.ReadBasic.All</code>, <code>Calendars.Read.Shared</code>, <code>Files.Read</code>
		</p>
	</div>

	<!-- Debug Logs -->
	<div class="ms365-card">
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

			<?php if ( ! empty( $logs ) ) : ?>
				&nbsp;&nbsp;
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'clear_logs', '1' ), 'wp_ms365_clear_logs' ) ); ?>" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Clear all logs?', 'wp-ms365-graph' ); ?>');">
					<?php esc_html_e( 'Clear Logs', 'wp-ms365-graph' ); ?>
				</a>
			<?php endif; ?>
		</p>

		<?php echo WP_MS365_Logger::render_logs_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>

	<!-- Troubleshooting Tips -->
	<div class="ms365-card">
		<h2><?php esc_html_e( 'Troubleshooting Tips', 'wp-ms365-graph' ); ?></h2>
		<ul style="list-style: disc; margin-left: 20px;">
			<li>
				<strong><?php esc_html_e( 'Connection Failed:', 'wp-ms365-graph' ); ?></strong>
				<br />
				<?php esc_html_e( 'Check the logs above. Common issues: invalid Tenant ID, Client ID, or Client Secret. Verify these match your Azure app registration.', 'wp-ms365-graph' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Access Denied (403) when accessing another user:', 'wp-ms365-graph' ); ?></strong>
				<br />
				<?php esc_html_e( 'Ensure your Azure app has the required permissions and admin consent has been granted. See the OAuth Scopes section above.', 'wp-ms365-graph' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Token Refresh Failures:', 'wp-ms365-graph' ); ?></strong>
				<br />
				<?php esc_html_e( 'The refresh token may be invalid. Try disconnecting and reconnecting in the Settings page.', 'wp-ms365-graph' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Logs are empty:', 'wp-ms365-graph' ); ?></strong>
				<br />
				<?php esc_html_e( 'Enable WP_DEBUG in wp-config.php to start recording debug logs.', 'wp-ms365-graph' ); ?>
			</li>
		</ul>
	</div>
</div>
