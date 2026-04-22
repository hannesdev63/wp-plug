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
$connected_user  = get_option( 'wp_ms365_connected_user', '' );
$disconnected    = isset( $_GET['disconnected'] ) && '1' === $_GET['disconnected']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$redirect_uri    = WP_MS365_Auth::get_redirect_uri();
$auth_url        = WP_MS365_Auth::get_authorization_url();
?>
<div class="wrap ms365-settings">
	<h1 class="ms365-settings__heading">
		<span class="dashicons dashicons-microsoft"></span>
		<?php esc_html_e( 'Microsoft 365 Graph', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Settings', 'wp-ms365-graph' ); ?>
	</h1>

	<?php if ( $disconnected ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Successfully disconnected from Microsoft 365.', 'wp-ms365-graph' ); ?></p>
		</div>
	<?php endif; ?>

	<?php settings_errors( 'wp_ms365_settings' ); ?>

	<!-- Connection status banner -->
	<div class="ms365-status <?php echo $is_connected ? 'ms365-status--connected' : 'ms365-status--disconnected'; ?>">
		<?php if ( $is_connected ) : ?>
			<span class="ms365-status__dot"></span>
			<strong><?php esc_html_e( 'Connected', 'wp-ms365-graph' ); ?></strong>
			<?php if ( $connected_user ) : ?>
				&nbsp;<?php /* translators: %s: Microsoft account display name */ ?>
				<span><?php printf( esc_html__( 'as %s', 'wp-ms365-graph' ), esc_html( $connected_user ) ); ?></span>
			<?php endif; ?>
			&nbsp;&mdash;&nbsp;
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<input type="hidden" name="action" value="wp_ms365_disconnect" />
				<?php wp_nonce_field( 'wp_ms365_disconnect' ); ?>
				<button type="submit" class="button button-small ms365-status__disconnect">
					<?php esc_html_e( 'Disconnect', 'wp-ms365-graph' ); ?>
				</button>
			</form>
		<?php else : ?>
			<span class="ms365-status__dot"></span>
			<strong><?php esc_html_e( 'Not connected', 'wp-ms365-graph' ); ?></strong>
		<?php endif; ?>
	</div>

	<!-- Settings form -->
	<form method="post" action="options.php" class="ms365-settings__form">
		<?php settings_fields( 'wp_ms365_settings_group' ); ?>
		<?php do_settings_sections( 'wp-ms365-graph' ); ?>

		<!-- Redirect URI (read-only, for reference) -->
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Redirect URI', 'wp-ms365-graph' ); ?></th>
				<td>
					<input type="text" value="<?php echo esc_attr( $redirect_uri ); ?>" class="regular-text" readonly />
					<p class="description">
						<?php esc_html_e( 'Add this URL as a Redirect URI in your Azure app registration (under Authentication &rarr; Web).', 'wp-ms365-graph' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<!-- Connect button (only shown when credentials are saved) -->
	<?php
	$settings = WP_MS365_Auth::get_settings();
	if ( ! empty( $settings['tenant_id'] ) && ! empty( $settings['client_id'] ) && ! $is_connected ) :
	?>
	<hr />
	<h2><?php esc_html_e( 'Authorize Access', 'wp-ms365-graph' ); ?></h2>
	<p>
		<?php esc_html_e( 'Once you have saved your credentials above, click the button below to authorize WordPress to access your Microsoft 365 account.', 'wp-ms365-graph' ); ?>
	</p>
	<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary button-hero">
		<?php esc_html_e( 'Connect to Microsoft 365', 'wp-ms365-graph' ); ?>
	</a>
	<?php endif; ?>

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
				<td><?php esc_html_e( 'Displays upcoming calendar events from your Outlook Calendar.', 'wp-ms365-graph' ); ?></td>
				<td><code>[ms365_calendar limit="5" timezone="Europe/London" title="My Calendar"]</code></td>
			</tr>
			<tr>
				<td><code>[ms365_files]</code></td>
				<td><?php esc_html_e( 'Displays a file listing from your OneDrive.', 'wp-ms365-graph' ); ?></td>
				<td><code>[ms365_files limit="10" folder="Documents" title="My Files"]</code></td>
			</tr>
			<tr>
				<td><code>[ms365_profile]</code></td>
				<td><?php esc_html_e( 'Displays the signed-in Microsoft 365 user profile.', 'wp-ms365-graph' ); ?></td>
				<td><code>[ms365_profile]</code></td>
			</tr>
		</tbody>
	</table>
</div>
