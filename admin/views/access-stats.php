<?php
/**
 * WordPress-origin access statistics page.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$source = isset( $_GET['ms365_source'] ) ? sanitize_key( wp_unslash( $_GET['ms365_source'] ) ) : 'all';
if ( ! in_array( $source, array( 'all', 'wordpress', 'shortcodes' ), true ) ) {
	$source = 'all';
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$window = isset( $_GET['ms365_window'] ) ? (int) $_GET['ms365_window'] : 7;
if ( ! in_array( $window, array( 7, 30, 90, 180 ), true ) ) {
	$window = 7;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$wp_group = isset( $_GET['ms365_wp_group'] ) ? sanitize_key( wp_unslash( $_GET['ms365_wp_group'] ) ) : 'all';
if ( ! in_array( $wp_group, array( 'all', 'page', 'blog', 'document' ), true ) ) {
	$wp_group = 'all';
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$shortcode_target = isset( $_GET['ms365_shortcode_target'] ) ? sanitize_key( wp_unslash( $_GET['ms365_shortcode_target'] ) ) : 'all';
if ( ! in_array( $shortcode_target, array( 'all', 'sharepoint', 'onedrive', 'outlook' ), true ) ) {
	$shortcode_target = 'all';
}

$show_wp         = in_array( $source, array( 'all', 'wordpress' ), true );
$show_shortcodes = in_array( $source, array( 'all', 'shortcodes' ), true );

$wp_totals = array( 'page' => 0, 'blog' => 0, 'document' => 0 );
$wp_top    = array();
$wp_trend  = array();
if ( $show_wp ) {
	$wp_totals = WP_MS365_WP_Access_Stats::get_totals_by_group( $window );
	$wp_top    = WP_MS365_WP_Access_Stats::get_top_items( $window, 10, $wp_group );
	$wp_trend  = WP_MS365_WP_Access_Stats::get_daily_trend( $window, $wp_group );
}

$external_totals = array( 'sharepoint' => 0, 'onedrive' => 0, 'outlook' => 0 );
$external_top    = array();
$external_trend  = array();
if ( $show_shortcodes ) {
	$external_totals = WP_MS365_WP_Access_Stats::get_external_totals_by_source( $window );
	$external_top    = WP_MS365_WP_Access_Stats::get_top_items( $window, 250, 'external' );
	$external_trend  = WP_MS365_WP_Access_Stats::get_external_daily_trend( $window, $shortcode_target );
}

if ( $show_shortcodes && 'all' !== $shortcode_target ) {
	$external_top = array_values(
		array_filter(
			$external_top,
			function ( $row ) use ( $shortcode_target ) {
				return isset( $row['post_type'] ) && $shortcode_target === sanitize_key( (string) $row['post_type'] );
			}
		)
	);
}

$top_rows = array();

$shortcode_item_keys = array();
if ( $show_shortcodes ) {
	foreach ( $external_top as $row ) {
		$external_url = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
		if ( '' !== $external_url ) {
			$shortcode_item_keys[ 'url:' . strtolower( untrailingslashit( $external_url ) ) ] = true;
		}

		$external_label = isset( $row['post_title'] ) ? trim( (string) $row['post_title'] ) : '';
		if ( '' !== $external_label ) {
			$shortcode_item_keys[ 'label:' . strtolower( $external_label ) ] = true;
		}
	}
}

if ( $show_wp ) {
	foreach ( $wp_top as $row ) {
		$wp_label = isset( $row['post_title'] ) ? trim( (string) $row['post_title'] ) : '';
		$wp_url   = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';

		if ( ! empty( $shortcode_item_keys ) ) {
			$matched_by_url   = '' !== $wp_url && isset( $shortcode_item_keys[ 'url:' . strtolower( untrailingslashit( $wp_url ) ) ] );
			$matched_by_label = '' !== $wp_label && isset( $shortcode_item_keys[ 'label:' . strtolower( $wp_label ) ] );

			if ( $matched_by_url || $matched_by_label ) {
				continue;
			}
		}

		$top_rows[] = array(
			'label'  => '' !== $wp_label ? $wp_label : __( '(Untitled)', 'esc-connect' ),
			'value'  => isset( $row['total_hits'] ) ? (int) $row['total_hits'] : 0,
			'source' => __( 'WordPress Content', 'esc-connect' ),
			'url'    => $wp_url,
		);
	}
}

if ( $show_shortcodes ) {
	foreach ( $external_top as $row ) {
		$type = isset( $row['post_type'] ) ? sanitize_key( (string) $row['post_type'] ) : '';
		$source_label = __( 'WP Shortcode -> Microsoft 365', 'esc-connect' );
		if ( 'sharepoint' === $type ) {
			$source_label = __( 'WP Shortcode -> SharePoint', 'esc-connect' );
		} elseif ( 'onedrive' === $type ) {
			$source_label = __( 'WP Shortcode -> OneDrive', 'esc-connect' );
		} elseif ( 'outlook' === $type ) {
			$source_label = __( 'WP Shortcode -> Outlook', 'esc-connect' );
		}

		$top_rows[] = array(
			'label'  => isset( $row['post_title'] ) ? (string) $row['post_title'] : __( '(Untitled)', 'esc-connect' ),
			'value'  => isset( $row['total_hits'] ) ? (int) $row['total_hits'] : 0,
			'source' => $source_label,
			'url'    => isset( $row['url'] ) ? (string) $row['url'] : '',
		);
	}
}

usort(
	$top_rows,
	function ( $a, $b ) {
		return (int) $b['value'] <=> (int) $a['value'];
	}
);
$top_rows = array_slice( $top_rows, 0, 10 );

$trend_map = array();
if ( $show_wp ) {
	foreach ( $wp_trend as $row ) {
		$date = isset( $row['stat_date'] ) ? (string) $row['stat_date'] : '';
		if ( '' === $date ) {
			continue;
		}
		if ( ! isset( $trend_map[ $date ] ) ) {
			$trend_map[ $date ] = 0;
		}
		$trend_map[ $date ] += isset( $row['total_hits'] ) ? (int) $row['total_hits'] : 0;
	}
}

if ( $show_shortcodes ) {
	foreach ( $external_trend as $row ) {
		$date = isset( $row['stat_date'] ) ? (string) $row['stat_date'] : '';
		if ( '' === $date ) {
			continue;
		}
		if ( ! isset( $trend_map[ $date ] ) ) {
			$trend_map[ $date ] = 0;
		}
		$trend_map[ $date ] += isset( $row['total_hits'] ) ? (int) $row['total_hits'] : 0;
	}
}
ksort( $trend_map );
?>

<div class="wrap msgraph_access-stats">
	<h1 class="msgraph_dashboard__heading">
		<img src="<?php echo esc_url( WP_MS365_Admin::get_icon_url() ); ?>" class="msgraph_page-icon" alt="" width="28" height="28" />
		<?php esc_html_e( 'ESC Connect', 'esc-connect' ); ?> &mdash; <?php esc_html_e( 'Access Statistics', 'esc-connect' ); ?>
	</h1>

	<p class="description">
		<?php esc_html_e( 'WordPress-origin access only: frontend page/blog/document views and shortcode-driven access to Microsoft 365 items.', 'esc-connect' ); ?>
	</p>

	<form method="get" action="" style="margin:16px 0;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
		<input type="hidden" name="page" value="esc-connect" />
		<input type="hidden" name="tab" value="access-stats" />
		<div>
			<label for="ms365_source"><strong><?php esc_html_e( 'Source', 'esc-connect' ); ?></strong></label><br />
			<select id="ms365_source" name="ms365_source">
				<option value="all" <?php selected( $source, 'all' ); ?>><?php esc_html_e( 'All WordPress-origin', 'esc-connect' ); ?></option>
				<option value="wordpress" <?php selected( $source, 'wordpress' ); ?>><?php esc_html_e( 'WordPress content', 'esc-connect' ); ?></option>
				<option value="shortcodes" <?php selected( $source, 'shortcodes' ); ?>><?php esc_html_e( 'WordPress shortcode to M365', 'esc-connect' ); ?></option>
			</select>
		</div>
		<div>
			<label for="ms365_window"><strong><?php esc_html_e( 'Window', 'esc-connect' ); ?></strong></label><br />
			<select id="ms365_window" name="ms365_window">
				<option value="7" <?php selected( $window, 7 ); ?>><?php esc_html_e( 'Last 7 days', 'esc-connect' ); ?></option>
				<option value="30" <?php selected( $window, 30 ); ?>><?php esc_html_e( 'Last 30 days', 'esc-connect' ); ?></option>
				<option value="90" <?php selected( $window, 90 ); ?>><?php esc_html_e( 'Last 90 days', 'esc-connect' ); ?></option>
				<option value="180" <?php selected( $window, 180 ); ?>><?php esc_html_e( 'Last 180 days', 'esc-connect' ); ?></option>
			</select>
		</div>
		<div>
			<label for="ms365_wp_group"><strong><?php esc_html_e( 'WordPress type', 'esc-connect' ); ?></strong></label><br />
			<select id="ms365_wp_group" name="ms365_wp_group">
				<option value="all" <?php selected( $wp_group, 'all' ); ?>><?php esc_html_e( 'All types', 'esc-connect' ); ?></option>
				<option value="page" <?php selected( $wp_group, 'page' ); ?>><?php esc_html_e( 'Pages', 'esc-connect' ); ?></option>
				<option value="blog" <?php selected( $wp_group, 'blog' ); ?>><?php esc_html_e( 'Blogs', 'esc-connect' ); ?></option>
				<option value="document" <?php selected( $wp_group, 'document' ); ?>><?php esc_html_e( 'Documents', 'esc-connect' ); ?></option>
			</select>
		</div>
		<div>
			<label for="ms365_shortcode_target"><strong><?php esc_html_e( 'Shortcode target', 'esc-connect' ); ?></strong></label><br />
			<select id="ms365_shortcode_target" name="ms365_shortcode_target">
				<option value="all" <?php selected( $shortcode_target, 'all' ); ?>><?php esc_html_e( 'All Microsoft 365 targets', 'esc-connect' ); ?></option>
				<option value="sharepoint" <?php selected( $shortcode_target, 'sharepoint' ); ?>><?php esc_html_e( 'SharePoint', 'esc-connect' ); ?></option>
				<option value="onedrive" <?php selected( $shortcode_target, 'onedrive' ); ?>><?php esc_html_e( 'OneDrive', 'esc-connect' ); ?></option>
				<option value="outlook" <?php selected( $shortcode_target, 'outlook' ); ?>><?php esc_html_e( 'Outlook', 'esc-connect' ); ?></option>
			</select>
		</div>
		<div>
			<?php submit_button( __( 'Apply', 'esc-connect' ), 'secondary', 'submit', false ); ?>
		</div>
	</form>

	<div class="msgraph_card">
		<h2 class="msgraph_card__title"><?php esc_html_e( 'Summary', 'esc-connect' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Metric', 'esc-connect' ); ?></th>
					<th><?php esc_html_e( 'Value', 'esc-connect' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $show_wp ) : ?>
				<tr><td><?php esc_html_e( 'WordPress page views', 'esc-connect' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $wp_totals['page'] ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'WordPress blog views', 'esc-connect' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $wp_totals['blog'] ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'WordPress document views', 'esc-connect' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $wp_totals['document'] ) ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $show_shortcodes ) : ?>
				<tr><td><?php esc_html_e( 'Shortcode to SharePoint item accesses', 'esc-connect' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $external_totals['sharepoint'] ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Shortcode to OneDrive item accesses', 'esc-connect' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $external_totals['onedrive'] ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Shortcode to Outlook item accesses', 'esc-connect' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $external_totals['outlook'] ) ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div class="msgraph_card">
		<h2 class="msgraph_card__title"><?php esc_html_e( 'Top Accessed Items', 'esc-connect' ); ?></h2>
		<?php if ( empty( $top_rows ) ) : ?>
			<p><?php esc_html_e( 'No access data available for the current filters yet.', 'esc-connect' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'esc-connect' ); ?></th>
						<th><?php esc_html_e( 'Source', 'esc-connect' ); ?></th>
						<th><?php esc_html_e( 'Access count', 'esc-connect' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_rows as $row ) : ?>
						<tr>
							<td>
								<?php if ( ! empty( $row['url'] ) ) : ?>
									<a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['label'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $row['label'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['source'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['value'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="msgraph_card">
		<h2 class="msgraph_card__title"><?php esc_html_e( 'Daily Trend', 'esc-connect' ); ?></h2>
		<?php if ( empty( $trend_map ) ) : ?>
			<p><?php esc_html_e( 'No trend data available for the selected period yet.', 'esc-connect' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'esc-connect' ); ?></th>
						<th><?php esc_html_e( 'Total accesses', 'esc-connect' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $trend_map as $date => $value ) : ?>
						<tr>
							<td><?php echo esc_html( $date ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $value ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
