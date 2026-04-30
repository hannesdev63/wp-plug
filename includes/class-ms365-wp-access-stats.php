<?php
/**
 * WordPress content access statistics tracking.
 *
 * Tracks public frontend views for pages, blog posts, and document-like content.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MS365_WP_Access_Stats {

	/** Cron hook for cleanup. */
	const CRON_HOOK = 'wp_ms365_prune_wp_access_stats';

	/** Maximum days to retain records. */
	const RETENTION_DAYS = 365;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ensure_table_exists();
		add_action( 'template_redirect', array( $this, 'track_request' ), 20 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'prune_old_logs' ) );
	}

	/**
	 * Ensure the access stats table exists.
	 *
	 * @return void
	 */
	private function ensure_table_exists() {
		// dbDelta handles both create and schema updates.
		self::create_table();
	}

	/**
	 * Return table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ms365_wp_access_stats';
	}

	/**
	 * Create or update the access stats table.
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
			stat_date date NOT NULL,
			content_group varchar(20) NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(60) NOT NULL,
			post_title varchar(191) NOT NULL DEFAULT '',
			item_url varchar(255) NOT NULL DEFAULT '',
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_day_post_group (stat_date, post_id, content_group),
			KEY stat_date (stat_date),
			KEY content_group (content_group),
			KEY post_type (post_type),
			KEY hits (hits)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Schedule cleanup cron.
	 *
	 * @return void
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule cleanup cron.
	 *
	 * @return void
	 */
	public static function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Delete stale rows based on retention.
	 *
	 * @return void
	 */
	public static function prune_old_logs() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE stat_date < DATE_SUB(CURDATE(), INTERVAL %d DAY)", self::RETENTION_DAYS ) );
	}

	/**
	 * Track one frontend request if it targets supported content.
	 *
	 * @return void
	 */
	public function track_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( is_preview() || is_feed() || is_search() || is_trackback() || is_robots() || is_favicon() ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		if ( 'publish' !== get_post_status( $post ) ) {
			return;
		}

		if ( $this->is_likely_bot_request() ) {
			return;
		}

		$content_group = $this->resolve_content_group( $post );
		if ( '' === $content_group ) {
			return;
		}

		$this->increment_hit( $post, $content_group, '' );
	}

	/**
	 * Track a shortcode-origin access to an external Microsoft 365 item.
	 *
	 * @param string $source    Source key (onedrive|sharepoint|outlook).
	 * @param string $item_id   Stable item identifier.
	 * @param string $item_name Human-readable item label.
	 * @param string $item_url  Optional item URL.
	 * @return void
	 */
	public static function track_external_access( $source, $item_id, $item_name, $item_url = '' ) {
		$source = sanitize_key( (string) $source );
		if ( ! in_array( $source, array( 'onedrive', 'sharepoint', 'outlook' ), true ) ) {
			return;
		}

		$item_id   = trim( (string) $item_id );
		$item_name = trim( sanitize_text_field( (string) $item_name ) );
		$item_url  = esc_url_raw( trim( (string) $item_url ) );

		if ( '' === $item_id || '' === $item_name ) {
			return;
		}

		$post_id = abs( (int) crc32( $source . '|' . $item_id ) );
		if ( 0 === $post_id ) {
			$post_id = 1;
		}

		self::insert_hit_row( 'external', $post_id, $source, $item_name, $item_url );
	}

	/**
	 * Resolve the tracked content group for the given post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function resolve_content_group( WP_Post $post ) {
		if ( 'page' === $post->post_type ) {
			return 'page';
		}

		if ( 'post' === $post->post_type ) {
			return 'blog';
		}

		if ( 'attachment' === $post->post_type || $this->is_document_like_post_type( $post->post_type ) ) {
			return 'document';
		}

		return '';
	}

	/**
	 * Determine whether a post type should be treated as document-like.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function is_document_like_post_type( $post_type ) {
		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return false;
		}

		if ( in_array( $post_type, array( 'post', 'page', 'attachment' ), true ) ) {
			return false;
		}

		$allowlist = apply_filters( 'wp_ms365_document_like_post_types', array() );
		if ( is_array( $allowlist ) && in_array( $post_type, $allowlist, true ) ) {
			return true;
		}

		return (bool) preg_match( '/(doc|file|asset|resource|download|library|knowledge)/i', $post_type );
	}

	/**
	 * Increment daily hit counter for a post/content-group pair.
	 *
	 * @param WP_Post $post          Post object.
	 * @param string  $content_group Content group key.
	 * @return void
	 */
	private function increment_hit( WP_Post $post, $content_group, $item_url = '', $forced_title = '' ) {
		$title = '';
		if ( '' !== trim( (string) $forced_title ) ) {
			$title = trim( sanitize_text_field( (string) $forced_title ) );
		} else {
			$title = sanitize_text_field( html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		}

		$item_url = esc_url_raw( trim( (string) $item_url ) );

		self::insert_hit_row( $content_group, (int) $post->ID, (string) $post->post_type, $title, $item_url );
	}

	/**
	 * Insert/upsert one hit row.
	 *
	 * @param string $content_group Group key.
	 * @param int    $post_id       Item id.
	 * @param string $post_type     Item type/source.
	 * @param string $title         Item title.
	 * @param string $item_url      Optional item URL.
	 * @return void
	 */
	private static function insert_hit_row( $content_group, $post_id, $post_type, $title, $item_url = '' ) {
		global $wpdb;

		$table = self::table_name();
		$date  = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (stat_date, content_group, post_id, post_type, post_title, item_url, hits, updated_at)
				 VALUES (%s, %s, %d, %s, %s, %s, 1, %s)
				 ON DUPLICATE KEY UPDATE
					hits = hits + 1,
					post_type = VALUES(post_type),
					post_title = VALUES(post_title),
					item_url = VALUES(item_url),
					updated_at = VALUES(updated_at)",
				$date,
				(string) $content_group,
				(int) $post_id,
				(string) $post_type,
				(string) $title,
				(string) $item_url,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Return summarized hits by content group in the requested period.
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	public static function get_totals_by_group( $days = 7 ) {
		global $wpdb;

		$days  = max( 1, (int) $days );
		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT content_group, SUM(hits) AS total_hits
				 FROM {$table}
				 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				 GROUP BY content_group",
				$days
			),
			ARRAY_A
		);

		$totals = array(
			'page'     => 0,
			'blog'     => 0,
			'document' => 0,
		);

		foreach ( $rows as $row ) {
			$key = isset( $row['content_group'] ) ? (string) $row['content_group'] : '';
			if ( isset( $totals[ $key ] ) ) {
				$totals[ $key ] = (int) $row['total_hits'];
			}
		}

		return $totals;
	}

	/**
	 * Return top content items for the requested period.
	 *
	 * @param int    $days          Number of days.
	 * @param int    $limit         Max number of rows.
	 * @param string $content_group Optional filter (page|blog|document|all).
	 * @return array
	 */
	public static function get_top_items( $days = 7, $limit = 10, $content_group = 'all' ) {
		global $wpdb;

		$days          = max( 1, (int) $days );
		$limit         = max( 1, min( 100, (int) $limit ) );
		$content_group = sanitize_key( (string) $content_group );
		$table         = self::table_name();

		if ( in_array( $content_group, array( 'page', 'blog', 'document', 'external' ), true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT post_id, post_type, post_title, item_url, content_group, SUM(hits) AS total_hits
				 FROM {$table}
				 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
					AND content_group = %s
				 GROUP BY post_id, post_type, post_title, item_url, content_group
				 ORDER BY total_hits DESC, post_title ASC
				 LIMIT %d",
				$days,
				$content_group,
				$limit
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT post_id, post_type, post_title, item_url, content_group, SUM(hits) AS total_hits
				 FROM {$table}
				 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				 GROUP BY post_id, post_type, post_title, item_url, content_group
				 ORDER BY total_hits DESC, post_title ASC
				 LIMIT %d",
				$days,
				$limit
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( ! empty( $row['item_url'] ) ) {
				$row['url'] = esc_url_raw( (string) $row['item_url'] );
			} elseif ( $post_id > 0 ) {
				$permalink = get_permalink( $post_id );
				$row['url'] = is_string( $permalink ) ? $permalink : '';
			} else {
				$row['url'] = '';
			}
			$row['total_hits'] = isset( $row['total_hits'] ) ? (int) $row['total_hits'] : 0;
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Return daily totals for trend chart/table.
	 *
	 * @param int    $days          Number of days.
	 * @param string $content_group Optional filter (page|blog|document|all).
	 * @return array
	 */
	public static function get_daily_trend( $days = 7, $content_group = 'all' ) {
		global $wpdb;

		$days          = max( 1, (int) $days );
		$content_group = sanitize_key( (string) $content_group );
		$table         = self::table_name();

		if ( in_array( $content_group, array( 'page', 'blog', 'document', 'external' ), true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT stat_date, SUM(hits) AS total_hits
				 FROM {$table}
				 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
					AND content_group = %s
				 GROUP BY stat_date
				 ORDER BY stat_date DESC",
				$days,
				$content_group
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT stat_date, SUM(hits) AS total_hits
				 FROM {$table}
				 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				 GROUP BY stat_date
				 ORDER BY stat_date DESC",
				$days
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['total_hits'] = isset( $row['total_hits'] ) ? (int) $row['total_hits'] : 0;
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Return summarized external shortcode-origin interactions by source.
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	public static function get_external_totals_by_source( $days = 7 ) {
		global $wpdb;

		$days  = max( 1, (int) $days );
		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_type, SUM(hits) AS total_hits
				 FROM {$table}
				 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
					AND content_group = 'external'
				 GROUP BY post_type",
				$days
			),
			ARRAY_A
		);

		$totals = array(
			'sharepoint' => 0,
			'onedrive'   => 0,
			'outlook'    => 0,
		);

		foreach ( $rows as $row ) {
			$key = isset( $row['post_type'] ) ? sanitize_key( (string) $row['post_type'] ) : '';
			if ( isset( $totals[ $key ] ) ) {
				$totals[ $key ] = (int) $row['total_hits'];
			}
		}

		return $totals;
	}

	/**
	 * Return daily totals for external shortcode-origin interactions.
	 *
	 * @param int    $days   Number of days.
	 * @param string $source Source key (all|sharepoint|onedrive|outlook).
	 * @return array
	 */
	public static function get_external_daily_trend( $days = 7, $source = 'all' ) {
		global $wpdb;

		$days   = max( 1, (int) $days );
		$source = sanitize_key( (string) $source );
		$table  = self::table_name();

		if ( in_array( $source, array( 'sharepoint', 'onedrive', 'outlook' ), true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT stat_date, SUM(hits) AS total_hits
				 FROM {$table}
				 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
					AND content_group = 'external'
					AND post_type = %s
				 GROUP BY stat_date
				 ORDER BY stat_date ASC",
				$days,
				$source
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT stat_date, SUM(hits) AS total_hits
				 FROM {$table}
				 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
					AND content_group = 'external'
				 GROUP BY stat_date
				 ORDER BY stat_date ASC",
				$days
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['total_hits'] = isset( $row['total_hits'] ) ? (int) $row['total_hits'] : 0;
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Best-effort crawler detection using user-agent signatures.
	 *
	 * @return bool
	 */
	private function is_likely_bot_request() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( (string) $_SERVER['HTTP_USER_AGENT'] ) : '';
		if ( '' === $ua ) {
			return true;
		}

		$patterns = array(
			'bot',
			'spider',
			'crawler',
			'slurp',
			'headless',
			'facebookexternalhit',
			'whatsapp',
			'skypeuripreview',
			'twitterbot',
			'linkedinbot',
			'pingdom',
			'uptimerobot',
		);

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $ua, $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}
