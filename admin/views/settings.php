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
$operation       = isset( $_GET['op'] ) ? sanitize_key( wp_unslash( $_GET['op'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$op_status       = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$op_reason       = isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

	<?php if ( 'success' === $op_status && 'import' === $operation ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings import completed successfully.', 'wp-ms365-graph' ); ?></p>
		</div>
	<?php elseif ( 'error' === $op_status && 'import' === $operation ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				switch ( $op_reason ) {
					case 'missing_password':
						esc_html_e( 'Import failed: please provide the decryption password.', 'wp-ms365-graph' );
						break;
					case 'missing_file':
						esc_html_e( 'Import failed: please choose an encrypted settings file.', 'wp-ms365-graph' );
						break;
					case 'upload_failed':
						esc_html_e( 'Import failed: file upload error.', 'wp-ms365-graph' );
						break;
					case 'invalid_size':
						esc_html_e( 'Import failed: file size is invalid.', 'wp-ms365-graph' );
						break;
					case 'decrypt_failed':
						esc_html_e( 'Import failed: invalid password or corrupted settings file.', 'wp-ms365-graph' );
						break;
					default:
						esc_html_e( 'Import failed. Please verify your file and password.', 'wp-ms365-graph' );
						break;
				}
				?>
			</p>
		</div>
	<?php elseif ( 'error' === $op_status && 'export' === $operation ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				if ( 'missing_password' === $op_reason ) {
					esc_html_e( 'Export failed: please provide an encryption password.', 'wp-ms365-graph' );
				} elseif ( 'password_mismatch' === $op_reason ) {
					esc_html_e( 'Export failed: the password and confirmation do not match.', 'wp-ms365-graph' );
				} else {
					esc_html_e( 'Export failed. Encryption may not be available on this server.', 'wp-ms365-graph' );
				}
				?>
			</p>
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
			<code>User.Read.All</code>, <code>Calendars.Read</code>, <code>Files.Read.All</code>, <code>Sites.Read.All</code>.<br />
			<?php esc_html_e( 'After adding these application permissions, click "Grant admin consent" in Azure and save credentials again.', 'wp-ms365-graph' ); ?>
		</p>
	</div>
	<?php endif; ?>
	<!-- Diagnostics note -->
	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Having issues? Check the ', 'wp-ms365-graph' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ms365-graph&tab=diagnostics' ) ); ?>">
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

	<hr />
	<h2><?php esc_html_e( 'Import / Export', 'wp-ms365-graph' ); ?></h2>
	<p>
		<?php esc_html_e( 'Export creates an encrypted file containing plugin settings and shortcode wording. Import decrypts and restores the same data.', 'wp-ms365-graph' ); ?>
	</p>

	<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:16px; align-items:start;">
		<div class="ms365-card" style="margin:0;">
			<h3><?php esc_html_e( 'Export Encrypted Settings', 'wp-ms365-graph' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wp_ms365_export_settings" />
				<?php wp_nonce_field( 'wp_ms365_export_settings' ); ?>
				<p>
					<label for="wp_ms365_export_password"><strong><?php esc_html_e( 'Encryption password', 'wp-ms365-graph' ); ?></strong></label><br />
					<input type="password" id="wp_ms365_export_password" name="export_password" class="regular-text ms365-password-input" autocomplete="off" required data-toggle-label-show="<?php echo esc_attr__( 'Show', 'wp-ms365-graph' ); ?>" data-toggle-label-hide="<?php echo esc_attr__( 'Hide', 'wp-ms365-graph' ); ?>" />
				</p>
				<p>
					<label for="wp_ms365_export_password_confirm"><strong><?php esc_html_e( 'Confirm password', 'wp-ms365-graph' ); ?></strong></label><br />
					<input type="password" id="wp_ms365_export_password_confirm" name="export_password_confirm" class="regular-text ms365-password-input" autocomplete="off" required data-toggle-label-show="<?php echo esc_attr__( 'Show', 'wp-ms365-graph' ); ?>" data-toggle-label-hide="<?php echo esc_attr__( 'Hide', 'wp-ms365-graph' ); ?>" />
				</p>
				<p class="description">
					<?php esc_html_e( 'Keep this password safe. It is required to decrypt the export during import.', 'wp-ms365-graph' ); ?>
				</p>
				<?php submit_button( __( 'Export Encrypted File', 'wp-ms365-graph' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<div class="ms365-card" style="margin:0;">
			<h3><?php esc_html_e( 'Import Encrypted Settings', 'wp-ms365-graph' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="wp_ms365_import_settings" />
				<?php wp_nonce_field( 'wp_ms365_import_settings' ); ?>
				<p>
					<label for="wp_ms365_import_file"><strong><?php esc_html_e( 'Encrypted settings file', 'wp-ms365-graph' ); ?></strong></label><br />
					<label for="wp_ms365_import_file" class="ms365-dropzone" data-dropzone>
						<span class="ms365-dropzone__title"><?php esc_html_e( 'Drag and drop your encrypted settings file here', 'wp-ms365-graph' ); ?></span>
						<span class="ms365-dropzone__meta"><?php esc_html_e( 'or click to choose a file', 'wp-ms365-graph' ); ?></span>
						<span class="ms365-dropzone__filename" data-dropzone-filename><?php esc_html_e( 'No file selected', 'wp-ms365-graph' ); ?></span>
					</label>
					<input type="file" id="wp_ms365_import_file" name="import_file" class="ms365-dropzone__input" accept=".json,.enc" required data-dropzone-input data-filename-target="[data-dropzone-filename]" />
				</p>
				<p>
					<label for="wp_ms365_import_password"><strong><?php esc_html_e( 'Decryption password', 'wp-ms365-graph' ); ?></strong></label><br />
					<input type="password" id="wp_ms365_import_password" name="import_password" class="regular-text ms365-password-input" autocomplete="off" required data-toggle-label-show="<?php echo esc_attr__( 'Show', 'wp-ms365-graph' ); ?>" data-toggle-label-hide="<?php echo esc_attr__( 'Hide', 'wp-ms365-graph' ); ?>" />
				</p>
				<p class="description">
					<?php esc_html_e( 'Import replaces current plugin settings and wording values.', 'wp-ms365-graph' ); ?>
				</p>
				<?php submit_button( __( 'Import Encrypted File', 'wp-ms365-graph' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var root = document.querySelector('.ms365-settings');
			if (!root) {
				return;
			}

			root.querySelectorAll('input[type="password"]').forEach(function (input) {
				if (input.dataset.toggleReady === '1') {
					return;
				}

				var wrapper = document.createElement('span');
				wrapper.className = 'ms365-password-field';
				input.parentNode.insertBefore(wrapper, input);
				wrapper.appendChild(input);

				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'button button-secondary ms365-password-toggle';
				button.textContent = input.dataset.toggleLabelShow || 'Show';
				button.setAttribute('aria-label', button.textContent);
				button.addEventListener('click', function () {
					var isHidden = input.type === 'password';
					input.type = isHidden ? 'text' : 'password';
					button.textContent = isHidden ? (input.dataset.toggleLabelHide || 'Hide') : (input.dataset.toggleLabelShow || 'Show');
					button.setAttribute('aria-label', button.textContent);
				});
				wrapper.appendChild(button);
				input.dataset.toggleReady = '1';
			});

			root.querySelectorAll('[data-dropzone]').forEach(function (dropzone) {
				var fileInput = document.querySelector(dropzone.getAttribute('for') ? '#' + dropzone.getAttribute('for') : '');
				var filenameNode = dropzone.querySelector('[data-dropzone-filename]');
				if (!fileInput) {
					return;
				}

				var updateFilename = function () {
					var fileName = fileInput.files && fileInput.files[0] ? fileInput.files[0].name : '<?php echo esc_js( __( 'No file selected', 'wp-ms365-graph' ) ); ?>';
					if (filenameNode) {
						filenameNode.textContent = fileName;
					}
				};

				['dragenter', 'dragover'].forEach(function (eventName) {
					dropzone.addEventListener(eventName, function (event) {
						event.preventDefault();
						dropzone.classList.add('is-dragover');
					});
				});

				['dragleave', 'dragend', 'drop'].forEach(function (eventName) {
					dropzone.addEventListener(eventName, function (event) {
						event.preventDefault();
						dropzone.classList.remove('is-dragover');
					});
				});

				dropzone.addEventListener('drop', function (event) {
					var files = event.dataTransfer && event.dataTransfer.files;
					if (!files || !files.length) {
						return;
					}

					fileInput.files = files;
					updateFilename();
				});

				fileInput.addEventListener('change', updateFilename);
				updateFilename();
			});
		});
	</script>
</div>
