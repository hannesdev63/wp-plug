<?php
/**
 * Admin settings page view.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected    = WP_MS365_Auth::is_connected();
$settings        = WP_MS365_Auth::get_settings();
$connected_user  = get_option( 'wp_ms365_connected_user', '' );
$token_requested = isset( $_GET['token_requested'] ) && '1' === $_GET['token_requested']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap ms365-settings">
	<h1 class="ms365-settings__heading">
		<img src="<?php echo esc_url( WP_MS365_Admin::get_icon_url() ); ?>" class="ms365-page-icon" alt="" width="28" height="28" />
		<?php esc_html_e( 'Entra ID Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Settings', 'wp-ms365-graph' ); ?>
	</h1>

	<?php if ( $token_requested ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Requested a new token. A fresh app-only token will be retrieved automatically on the next Graph request.', 'wp-ms365-graph' ); ?></p>
		</div>
	<?php endif; ?>

	<?php settings_errors( 'wp_ms365_settings' ); ?>

	<!-- Connection status banner -->
	<div class="ms365-status <?php echo $is_connected ? 'ms365-status--connected' : 'ms365-status--disconnected'; ?>">
		<?php if ( $is_connected ) : ?>
			<span class="ms365-status__dot"></span>
			<strong><?php esc_html_e( 'Connected (app-only)', 'wp-ms365-graph' ); ?></strong>
			<?php if ( $connected_user ) : ?>
				&nbsp;<?php /* translators: %s: connection mode label */ ?>
				<span><?php printf( esc_html__( 'mode: %s', 'wp-ms365-graph' ), esc_html( $connected_user ) ); ?></span>
			<?php endif; ?>
			&nbsp;&mdash;&nbsp;
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<input type="hidden" name="action" value="wp_ms365_request_new_token" />
				<?php wp_nonce_field( 'wp_ms365_request_new_token' ); ?>
				<button type="submit" class="button button-small ms365-status__disconnect">
					<?php esc_html_e( 'Request New Token', 'wp-ms365-graph' ); ?>
				</button>
			</form>
		<?php else : ?>
			<span class="ms365-status__dot"></span>
			<strong><?php esc_html_e( 'Not connected', 'wp-ms365-graph' ); ?></strong>
			<span><?php esc_html_e( 'Save valid credentials to enable automatic app-only token retrieval.', 'wp-ms365-graph' ); ?></span>
		<?php endif; ?>
	</div>

	<!-- Settings form -->
	<form method="post" action="options.php" class="ms365-settings__form">
		<?php settings_fields( 'wp_ms365_settings_group' ); ?>
		<?php do_settings_sections( 'wp-ms365-graph' ); ?>

		<?php submit_button(); ?>
	</form>

	<!-- Important note about permissions -->
	<?php if ( ! empty( $settings['specific_user'] ) ) : ?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Note: App-only access requires Microsoft Graph application permissions.', 'wp-ms365-graph' ); ?></strong><br />
			<?php esc_html_e( 'Make sure your Azure app registration includes the following permissions:', 'wp-ms365-graph' ); ?>
			<code>User.Read.All</code>, <code>Calendars.Read</code>, <code>Files.Read.All</code>.<br />
			<?php esc_html_e( 'After adding these application permissions, click "Grant admin consent" in Azure and save credentials again.', 'wp-ms365-graph' ); ?>
		</p>
	</div>
	<?php endif; ?>
	<!-- Diagnostics note -->
	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Having issues? Check the ', 'wp-ms365-graph' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ms365-diagnostics' ) ); ?>">
				<?php esc_html_e( 'Diagnostics page', 'wp-ms365-graph' ); ?>
			</a>
			<?php esc_html_e( ' for troubleshooting and debug logs.', 'wp-ms365-graph' ); ?>
		</p>
	</div>

	<hr />
	<h2><?php esc_html_e( 'Connection Mode', 'wp-ms365-graph' ); ?></h2>
	<p>
		<?php esc_html_e( 'This plugin uses app-only authentication (client credentials). No interactive Microsoft sign-in is required.', 'wp-ms365-graph' ); ?>
	</p>

	<!-- Usage guide -->
	<hr />
	<h2><?php esc_html_e( 'Shortcodes', 'wp-ms365-graph' ); ?></h2>
	<p><?php esc_html_e( 'Use these shortcodes in any post, page, or widget:', 'wp-ms365-graph' ); ?></p>
	<table class="widefat striped ms365-shortcode-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Shortcode', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Description', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Example', 'wp-ms365-graph' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><code>[ms365_calendar]</code></td>
				<td><?php esc_html_e( 'Displays upcoming calendar events from the configured specific user.', 'wp-ms365-graph' ); ?></td>
				<td><code>[ms365_calendar limit="5" timezone="Europe/London" title="My Calendar"]</code></td>
			</tr>
			<tr>
				<td><code>[ms365_files]</code></td>
				<td><?php esc_html_e( 'Displays a file listing from the configured specific user OneDrive.', 'wp-ms365-graph' ); ?></td>
				<td><code>[ms365_files limit="10" folder="Documents" title="My Files"]</code></td>
			</tr>
			<tr>
				<td><code>[ms365_profile]</code></td>
				<td><?php esc_html_e( 'Displays the configured specific Microsoft 365 user profile.', 'wp-ms365-graph' ); ?></td>
				<td><code>[ms365_profile]</code></td>
			</tr>
		</tbody>
	</table>
</div>
