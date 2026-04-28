<?php
/**
 * WordPress login integration for tenant-based Microsoft sign-in.
 *
 * Adds a "Sign in with Microsoft" button to wp-login.php and registers the
 * init-time callback handler that processes the OAuth response from Entra ID.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Login {

	public function __construct() {
		// Callback handler must run early on every request.
		add_action( 'init', array( 'WP_MS365_Auth', 'maybe_handle_sso_callback' ), 5 );

		// Login page additions.
		add_action( 'login_form',             array( $this, 'render_login_button' ) );
		add_action( 'login_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
		add_filter( 'wp_authenticate_user',   array( $this, 'block_local_password_for_entra_users' ), 20, 2 );
	}

	/**
	 * Block local password login for users already linked to Entra SSO.
	 *
	 * @param WP_User|WP_Error $user     Authenticated user object or error.
	 * @param string            $password Submitted password.
	 * @return WP_User|WP_Error
	 */
	public function block_local_password_for_entra_users( $user, $password ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $user;
		}

		$settings = WP_MS365_Auth::get_settings();
		if ( empty( $settings['sso_enabled'] ) ) {
			return $user;
		}

		$linked_flag = get_user_meta( $user->ID, WP_MS365_Auth::USER_META_SSO_LINKED, true );
		$provider    = get_user_meta( $user->ID, WP_MS365_Auth::USER_META_SSO_PROVIDER, true );

		if ( empty( $linked_flag ) && 'entra' !== $provider ) {
			return $user;
		}

		return new WP_Error(
			'ms365_entra_local_login_blocked',
			__( 'This account is managed via Microsoft Entra sign-in. Please use the Microsoft sign-in button.', 'wp-ms365-graph' )
		);
	}

	/**
	 * Render the "Sign in with Microsoft" button on wp-login.php.
	 *
	 * @return void
	 */
	public function render_login_button() {
		$settings = WP_MS365_Auth::get_settings();
		if ( empty( $settings['sso_enabled'] ) ) {
			return;
		}

		// Pass the requested_redirect_to through to the callback so users land
		// where they originally intended after signing in.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$after = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		$login_url = WP_MS365_Auth::get_sso_login_url( $after );
		$button_label = ! empty( $settings['sso_signin_button_text'] ) ? (string) $settings['sso_signin_button_text'] : __( 'Sign in with Microsoft', 'wp-ms365-graph' );

		if ( ! $login_url ) {
			return;
		}

		?>
		<div class="ms365-login-separator">
			<span><?php esc_html_e( 'or', 'wp-ms365-graph' ); ?></span>
		</div>
		<div class="ms365-login-button-wrap">
			<a href="<?php echo esc_url( $login_url ); ?>" class="ms365-login-button">
				<?php if ( ! empty( $settings['sso_signin_button_image'] ) ) : ?>
				<img src="<?php echo esc_url( $settings['sso_signin_button_image'] ); ?>" alt="" width="20" height="20" aria-hidden="true" class="ms365-login-button__icon" />
				<?php else : ?>
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23" width="20" height="20" aria-hidden="true" focusable="false">
					<rect x="1"  y="1"  width="10" height="10" fill="#F25022"/>
					<rect x="12" y="1"  width="10" height="10" fill="#7FBA00"/>
					<rect x="1"  y="12" width="10" height="10" fill="#00A4EF"/>
					<rect x="12" y="12" width="10" height="10" fill="#FFB900"/>
				</svg>
				<?php endif; ?>
				<span><?php echo esc_html( $button_label ); ?></span>
			</a>
		</div>
		<?php
	}

	/**
	 * Enqueue login-page stylesheet.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$settings = WP_MS365_Auth::get_settings();
		if ( empty( $settings['sso_enabled'] ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-ms365-login',
			WP_MS365_PLUGIN_URL . 'assets/css/login.css',
			array(),
			WP_MS365_VERSION
		);
	}
}
