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
		add_action( 'login_init', array( $this, 'maybe_force_entra_login' ), 1 );
		add_action( 'wp_logout', array( $this, 'clear_login_related_cookies' ), 20 );

		// Login page additions.
		add_action( 'login_message',          array( $this, 'render_local_login_notice' ) );
		add_action( 'login_form',             array( $this, 'render_login_button' ) );
		add_action( 'login_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
		add_filter( 'wp_authenticate_user',   array( $this, 'block_local_password_for_entra_users' ), 20, 2 );
		add_filter( 'pre_get_avatar_data',    array( $this, 'maybe_override_avatar' ), 20, 2 );
	}

	/**
	 * Clear all relevant WordPress login/auth cookies on logout.
	 *
	 * WordPress clears core auth cookies already, but in some environments stale
	 * cookies can survive due to differing path/domain combinations. Expire the
	 * common cookie names on both COOKIEPATH/SITECOOKIEPATH and "/".
	 *
	 * @return void
	 */
	public function clear_login_related_cookies() {
		if ( headers_sent() ) {
			return;
		}

		$cookie_names = array();

		if ( defined( 'AUTH_COOKIE' ) ) {
			$cookie_names[] = AUTH_COOKIE;
		}
		if ( defined( 'SECURE_AUTH_COOKIE' ) ) {
			$cookie_names[] = SECURE_AUTH_COOKIE;
		}
		if ( defined( 'LOGGED_IN_COOKIE' ) ) {
			$cookie_names[] = LOGGED_IN_COOKIE;
		}
		if ( defined( 'TEST_COOKIE' ) ) {
			$cookie_names[] = TEST_COOKIE;
		}

		// Legacy aliases seen in some setups.
		$cookie_names[] = 'wordpress_logged_in_' . COOKIEHASH;
		$cookie_names[] = 'wordpress_sec_' . COOKIEHASH;
		$cookie_names[] = 'wordpress_' . COOKIEHASH;

		$cookie_names = array_unique( array_filter( $cookie_names ) );

		$paths = array( '/', ( defined( 'COOKIEPATH' ) ? COOKIEPATH : '/' ), ( defined( 'SITECOOKIEPATH' ) ? SITECOOKIEPATH : '/' ) );
		$paths = array_unique( array_filter( $paths ) );

		$domains = array( '', ( defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '' ) );
		$domains = array_unique( $domains );

		foreach ( $cookie_names as $name ) {
			foreach ( $paths as $path ) {
				foreach ( $domains as $domain ) {
					setcookie( $name, ' ', time() - YEAR_IN_SECONDS, $path, $domain, is_ssl(), true );
				}
			}
		}
	}

	/**
	 * Render a notice when local login bypass is used while forced redirect is enabled.
	 *
	 * @param string $message Existing login message HTML.
	 * @return string
	 */
	public function render_local_login_notice( $message ) {
		$settings = WP_MS365_Auth::get_settings();
		if ( empty( $settings['sso_enabled'] ) || empty( $settings['sso_force_redirect'] ) ) {
			return $message;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$local_login_mode = isset( $_GET['ms365_local_login'] );
		if ( ! $local_login_mode ) {
			return $message;
		}

		$notice = '<p class="message">'
			. esc_html__( 'Local login bypass is active. Remove ?ms365_local_login=1 from the URL to return to automatic Microsoft Entra sign-in.', 'wp-ms365-graph' )
			. '</p>';

		return $notice . $message;
	}

	/**
	 * Optionally redirect default wp-login.php directly to Microsoft Entra sign-in.
	 *
	 * Bypass URL: wp-login.php?ms365_local_login=1
	 *
	 * @return void
	 */
	public function maybe_force_entra_login() {
		$settings = WP_MS365_Auth::get_settings();
		if ( empty( $settings['sso_enabled'] ) || empty( $settings['sso_force_redirect'] ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			return;
		}

		// Allow emergency local login when explicitly requested.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['ms365_local_login'] ) ) {
			return;
		}

		// Do not auto-redirect right after WordPress logout, otherwise users can
		// be signed back in immediately and see auth cookies reappear.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['loggedout'] ) || isset( $_GET['reauth'] ) || isset( $_GET['checkemail'] ) ) {
			return;
		}

		// Do not redirect non-login actions (logout, reset password, register, etc.).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		$allowed_actions = array( '', 'login' );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return;
		}

		// Preserve the originally requested redirect target when present.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$after = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		$login_url = WP_MS365_Auth::get_sso_login_url( $after );

		if ( ! $login_url ) {
			return;
		}

		// Entra authorize URL is external; wp_safe_redirect() may reject it and
		// cause a local redirect loop back to wp-login.php.
		wp_redirect( esc_url_raw( $login_url ) );
		exit;
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
	 * Override the WordPress avatar with the user's Microsoft profile picture.
	 *
	 * Fetches the photo on first request, saves it to the uploads directory, and
	 * caches the URL in a transient for 12 hours. Stores 'none' when the user
	 * has no Entra profile photo so we don't fetch repeatedly.
	 *
	 * @param  array             $args        Avatar data arguments.
	 * @param  int|string|WP_User $id_or_email User identifier.
	 * @return array
	 */
	public function maybe_override_avatar( $args, $id_or_email ) {
		$settings = WP_MS365_Auth::get_settings();
		if ( empty( $settings['sso_use_ms_avatar'] ) ) {
			return $args;
		}

		// Resolve the identifier to a WP_User object.
		$user = false;
		if ( $id_or_email instanceof WP_User ) {
			$user = $id_or_email;
		} elseif ( is_numeric( $id_or_email ) && (int) $id_or_email > 0 ) {
			$user = get_user_by( 'id', (int) $id_or_email );
		} elseif ( is_string( $id_or_email ) && false !== strpos( $id_or_email, '@' ) ) {
			$user = get_user_by( 'email', $id_or_email );
		} elseif ( $id_or_email instanceof WP_Comment ) {
			if ( ! empty( $id_or_email->user_id ) ) {
				$user = get_user_by( 'id', (int) $id_or_email->user_id );
			} elseif ( ! empty( $id_or_email->comment_author_email ) ) {
				$user = get_user_by( 'email', $id_or_email->comment_author_email );
			}
		}

		if ( ! ( $user instanceof WP_User ) ) {
			return $args;
		}

		// Only override for Entra-linked accounts.
		if ( ! get_user_meta( $user->ID, WP_MS365_Auth::USER_META_SSO_LINKED, true ) ) {
			return $args;
		}

		$transient_key = 'wp_ms365_avatar_' . $user->ID;
		$cached        = get_transient( $transient_key );

		if ( false === $cached ) {
			$cached = $this->fetch_and_cache_ms_avatar( $user );
		}

		if ( 'none' !== $cached && '' !== $cached ) {
			$args['url']          = $cached;
			$args['found_avatar'] = true;
		}

		return $args;
	}

	/**
	 * Fetch the Microsoft profile photo via Graph, save it to uploads, and
	 * store the resulting URL (or the sentinel 'none') in a transient.
	 *
	 * @param  WP_User $user
	 * @return string  Local file URL, or 'none' when no photo is available.
	 */
	private function fetch_and_cache_ms_avatar( WP_User $user ) {
		$transient_key = 'wp_ms365_avatar_' . $user->ID;
		$data          = WP_MS365_Graph::get_user_photo_data( $user->user_email );

		if ( is_wp_error( $data ) ) {
			WP_MS365_Logger::log(
				'debug',
				'MS avatar fetch failed for user ' . $user->ID . ': ' . $data->get_error_message()
			);
			set_transient( $transient_key, 'none', 12 * HOUR_IN_SECONDS );
			return 'none';
		}

		$upload    = wp_upload_dir();
		$dir_path  = trailingslashit( $upload['basedir'] ) . 'ms365-avatars';
		$dir_url   = trailingslashit( $upload['baseurl'] ) . 'ms365-avatars';

		if ( ! file_exists( $dir_path ) ) {
			wp_mkdir_p( $dir_path );
		}

		$file_path = $dir_path . '/' . $user->ID . '.jpg';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $file_path, $data ) ) {
			WP_MS365_Logger::log( 'error', 'MS avatar: could not write photo to disk for user ' . $user->ID );
			set_transient( $transient_key, 'none', 12 * HOUR_IN_SECONDS );
			return 'none';
		}

		$photo_url = $dir_url . '/' . $user->ID . '.jpg';
		set_transient( $transient_key, $photo_url, 12 * HOUR_IN_SECONDS );

		return $photo_url;
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
