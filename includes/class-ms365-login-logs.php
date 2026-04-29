<?php
/**
 * Login attempt logging and admin viewer.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_Login_Logs {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ensure_table_exists();
		add_action( 'wp_login', array( $this, 'log_success' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'log_failed' ), 10, 2 );
		add_action( 'wp_ms365_prune_login_logs', array( 'WP_MS365_Login_Logs', 'prune_old_logs' ) );
	}

	/**
	 * Ensure the log table exists for already-installed plugin instances.
	 *
	 * @return void
	 */
	private function ensure_table_exists() {
		global $wpdb;
		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
		if ( $exists !== $table_name ) {
			self::create_table();
		}
	}

	/**
	 * Return login log table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ms365_login_logs';
	}

	/**
	 * Delete log entries older than the configured retention period.
	 *
	 * @return void
	 */
	public static function prune_old_logs() {
		global $wpdb;

		$settings = get_option( 'wp_ms365_settings', array() );
		$days     = isset( $settings['login_log_retention_days'] ) ? (int) $settings['login_log_retention_days'] : 30;
		$days     = min( 365, max( 1, $days ) );

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE attempted_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) );
	}

	/**
	 * Schedule the daily cleanup cron event (call on plugin activation).
	 *
	 * @return void
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'wp_ms365_prune_login_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_ms365_prune_login_logs' );
		}
	}

	/**
	 * Remove the scheduled cleanup cron event (call on plugin deactivation).
	 *
	 * @return void
	 */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( 'wp_ms365_prune_login_logs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wp_ms365_prune_login_logs' );
		}
	}

	/**
	 * Create/update the login log table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attempted_at datetime NOT NULL,
			username varchar(191) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NULL,
			email varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL,
			reason text NULL,
			auth_source varchar(60) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text NULL,
			PRIMARY KEY  (id),
			KEY attempted_at (attempted_at),
			KEY status (status),
			KEY username (username)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Log successful WordPress login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 * @return void
	 */
	public function log_success( $user_login, $user ) {
		$this->insert_log(
			array(
				'username'    => (string) $user_login,
				'user_id'     => (int) $user->ID,
				'email'       => (string) $user->user_email,
				'status'      => 'success',
				'reason'      => '',
				'auth_source' => $this->detect_auth_source(),
			)
		);
	}

	/**
	 * Log failed WordPress login.
	 *
	 * @param string         $username Username submitted.
	 * @param WP_Error|mixed $error    Optional error object.
	 * @return void
	 */
	public function log_failed( $username, $error = null ) {
		$reason = '';

		if ( $error instanceof WP_Error ) {
			$code    = $error->get_error_code();
			$message = $error->get_error_message( $code );
			$reason  = trim( $code . ': ' . $message );
		}

		$this->insert_log(
			array(
				'username'    => (string) $username,
				'user_id'     => null,
				'email'       => '',
				'status'      => 'failed',
				'reason'      => $reason,
				'auth_source' => $this->detect_auth_source(),
			)
		);
	}

	/**
	 * Insert one log record.
	 *
	 * @param array $data Log data.
	 * @return void
	 */
	private function insert_log( $data ) {
		global $wpdb;

		$ip_address = '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$ip_address = sanitize_text_field( (string) $_SERVER['REMOTE_ADDR'] );
		}

		$user_agent = '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$user_agent = sanitize_text_field( (string) $_SERVER['HTTP_USER_AGENT'] );
		}

		$wpdb->insert(
			self::table_name(),
			array(
				'attempted_at' => current_time( 'mysql' ),
				'username'     => sanitize_user( (string) ( $data['username'] ?? '' ), true ),
				'user_id'      => isset( $data['user_id'] ) && null !== $data['user_id'] ? (int) $data['user_id'] : null,
				'email'        => sanitize_email( (string) ( $data['email'] ?? '' ) ),
				'status'       => sanitize_key( (string) ( $data['status'] ?? 'failed' ) ),
				'reason'       => sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) ),
				'auth_source'  => sanitize_text_field( (string) ( $data['auth_source'] ?? '' ) ),
				'ip_address'   => $ip_address,
				'user_agent'   => $user_agent,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Detect source path of the login attempt.
	 *
	 * @return string
	 */
	private function detect_auth_source() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );

		if ( false !== strpos( $request_uri, 'wp_ms365_sso' ) || false !== strpos( $request_uri, 'admin-post.php' ) ) {
			return 'ms365-sso';
		}

		if ( false !== strpos( $request_uri, 'wp-login.php' ) ) {
			return 'wp-login';
		}

		return 'unknown';
	}

	/**
	 * Render the login logs admin page.
	 *
	 * @return void
	 */
	public static function render_page( $context = array() ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-ms365-graph' ) );
		}

		global $wpdb;

		$as_tab   = is_array( $context ) && ! empty( $context['as_tab'] );
		$page_key = $as_tab ? 'wp-ms365-graph' : 'wp-ms365-login-logs';

		$per_page = 50;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : '';

		$where = '1=1';
		$args  = array();
		if ( in_array( $status_filter, array( 'success', 'failed' ), true ) ) {
			$where  .= ' AND status = %s';
			$args[] = $status_filter;
		}

		$count_sql = "SELECT COUNT(*) FROM " . self::table_name() . " WHERE {$where}";
		if ( empty( $args ) ) {
			$total = (int) $wpdb->get_var( $count_sql );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );
		}

		$logs_sql = "SELECT * FROM " . self::table_name() . " WHERE {$where} ORDER BY attempted_at DESC, id DESC LIMIT %d OFFSET %d";
		$args[] = $per_page;
		$args[] = $offset;
		$logs = $wpdb->get_results( $wpdb->prepare( $logs_sql, $args ) );

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Login Logs', 'wp-ms365-graph' ) . '</h1>';
		echo '<p>' . esc_html__( 'Shows successful and failed WordPress login attempts.', 'wp-ms365-graph' ) . '</p>';

		echo '<form method="get" style="margin: 1em 0;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( $page_key ) . '" />';
		if ( $as_tab ) {
			echo '<input type="hidden" name="tab" value="login-access" />';
		}
		echo '<label for="ms365-status-filter" style="margin-right:8px;">' . esc_html__( 'Status', 'wp-ms365-graph' ) . '</label>';
		echo '<select id="ms365-status-filter" name="status">';
		echo '<option value="">' . esc_html__( 'All', 'wp-ms365-graph' ) . '</option>';
		echo '<option value="success" ' . selected( $status_filter, 'success', false ) . '>' . esc_html__( 'Success', 'wp-ms365-graph' ) . '</option>';
		echo '<option value="failed" ' . selected( $status_filter, 'failed', false ) . '>' . esc_html__( 'Failed', 'wp-ms365-graph' ) . '</option>';
		echo '</select>';
		echo '<button type="submit" class="button" style="margin-left:8px;">' . esc_html__( 'Filter', 'wp-ms365-graph' ) . '</button>';
		echo '</form>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'wp-ms365-graph' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wp-ms365-graph' ) . '</th>';
		echo '<th>' . esc_html__( 'Username', 'wp-ms365-graph' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'wp-ms365-graph' ) . '</th>';
		echo '<th>' . esc_html__( 'ID', 'wp-ms365-graph' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'wp-ms365-graph' ) . '</th>';
		echo '<th>' . esc_html__( 'IP', 'wp-ms365-graph' ) . '</th>';
		echo '<th>' . esc_html__( 'Reason', 'wp-ms365-graph' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $logs ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No login attempts found.', 'wp-ms365-graph' ) . '</td></tr>';
		} else {
			foreach ( $logs as $log ) {
				echo '<tr>';
				echo '<td>' . esc_html( (string) $log->attempted_at ) . '</td>';
				echo '<td><strong>' . esc_html( ucfirst( (string) $log->status ) ) . '</strong></td>';
				echo '<td>' . esc_html( (string) $log->username ) . '</td>';
				echo '<td>' . esc_html( (string) $log->email ) . '</td>';
				echo '<td>' . esc_html( (string) $log->user_id ) . '</td>';
				echo '<td>' . esc_html( (string) $log->auth_source ) . '</td>';
				echo '<td>' . esc_html( (string) $log->ip_address ) . '</td>';
				echo '<td>' . esc_html( (string) $log->reason ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		$base_url = add_query_arg(
			array(
				'page'   => $page_key,
				'tab'    => $as_tab ? 'login-access' : null,
				'status' => $status_filter,
			),
			admin_url( 'admin.php' )
		);

		echo '<div class="tablenav"><div class="tablenav-pages" style="margin-top:1em;">';
		$pagination = paginate_links(
			array(
				'base'      => $base_url . '%_%',
				'format'    => '&paged=%#%',
				'current'   => $paged,
				'total'     => $total_pages,
				'prev_text' => __( '&laquo;', 'wp-ms365-graph' ),
				'next_text' => __( '&raquo;', 'wp-ms365-graph' ),
			)
		);
		if ( is_string( $pagination ) ) {
			echo wp_kses_post( $pagination );
		}
		echo '</div></div>';

		echo '</div>';
	}
}
