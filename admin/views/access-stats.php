<?php
/**
 * Unified access statistics page.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected = WP_MS365_Auth::is_connected();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$source = isset( $_GET['ms365_source'] ) ? sanitize_key( wp_unslash( $_GET['ms365_source'] ) ) : 'all';
if ( ! in_array( $source, array( 'all', 'wordpress', 'sharepoint', 'onedrive' ), true ) ) {
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

$show_wp = in_array( $source, array( 'all', 'wordpress' ), true );
$show_sp = in_array( $source, array( 'all', 'sharepoint' ), true );
$show_od = in_array( $source, array( 'all', 'onedrive' ), true );

$wp_totals = array( 'page' => 0, 'blog' => 0, 'document' => 0 );
$wp_top    = array();
$wp_trend  = array();
if ( $show_wp ) {
	$wp_totals = WP_MS365_WP_Access_Stats::get_totals_by_group( $window );
	$wp_top    = WP_MS365_WP_Access_Stats::get_top_items( $window, 10, $wp_group );
	$wp_trend  = WP_MS365_WP_Access_Stats::get_daily_trend( $window, $wp_group );
}

$period = WP_MS365_Graph::get_report_period( $window );

$sp_site_rows      = array();
$sp_file_rows      = array();
$od_account_rows   = array();
$sp_site_error     = null;
$sp_file_error     = null;
$od_error          = null;

if ( $is_connected && $show_sp ) {
	$sp_site_result = WP_MS365_Graph::get_sharepoint_site_usage_detail( $period );
	if ( is_wp_error( $sp_site_result ) ) {
		$sp_site_error = $sp_site_result;
	} else {
		$sp_site_rows = WP_MS365_Graph::normalize_sharepoint_site_usage_rows( $sp_site_result );
	}

	$sp_file_result = WP_MS365_Graph::get_sharepoint_file_usage_detail( $period );
	if ( is_wp_error( $sp_file_result ) ) {
		$sp_file_error = $sp_file_result;
	} else {
		$sp_file_rows = WP_MS365_Graph::normalize_sharepoint_file_usage_rows( $sp_file_result );
	}
}

if ( $is_connected && $show_od ) {
	$od_result = WP_MS365_Graph::get_onedrive_usage_account_detail( $period );
	if ( is_wp_error( $od_result ) ) {
		$od_error = $od_result;
	} else {
		$od_account_rows = WP_MS365_Graph::normalize_onedrive_usage_rows( $od_result );
	}
}

$sp_page_views_total = 0;
$sp_document_views_total = 0;
$od_document_views_total = 0;

foreach ( $sp_site_rows as $row ) {
	$sp_page_views_total += isset( $row['page_views'] ) ? (int) $row['page_views'] : 0;
}
foreach ( $sp_file_rows as $row ) {
	$sp_document_views_total += isset( $row['files_viewed'] ) ? (int) $row['files_viewed'] : 0;
}
foreach ( $od_account_rows as $row ) {
	$od_document_views_total += isset( $row['files_viewed'] ) ? (int) $row['files_viewed'] : 0;
}

$top_rows = array();
if ( $show_wp ) {
	foreach ( $wp_top as $row ) {
		$top_rows[] = array(
			'label'  => isset( $row['post_title'] ) ? (string) $row['post_title'] : __( '(Untitled)', 'wp-ms365-graph' ),
			'value'  => isset( $row['total_hits'] ) ? (int) $row['total_hits'] : 0,
			'source' => __( 'WordPress', 'wp-ms365-graph' ),
			'url'    => isset( $row['url'] ) ? (string) $row['url'] : '',
		);
	}
}
if ( $show_sp ) {
	$sp_top_pages = WP_MS365_Graph::aggregate_report_top_items( $sp_site_rows, 'label', 'page_views', 10 );
	foreach ( $sp_top_pages as $row ) {
		$top_rows[] = array(
			'label'  => $row['label'],
			'value'  => $row['value'],
			'source' => __( 'SharePoint Pages/Blogs', 'wp-ms365-graph' ),
			'url'    => isset( $row['url'] ) ? $row['url'] : '',
		);
	}

	$sp_top_docs = WP_MS365_Graph::aggregate_report_top_items( $sp_site_rows, 'label', 'active_files', 10 );
	foreach ( $sp_top_docs as $row ) {
		$top_rows[] = array(
			'label'  => $row['label'],
			'value'  => $row['value'],
			'source' => __( 'SharePoint Documents', 'wp-ms365-graph' ),
			'url'    => isset( $row['url'] ) ? $row['url'] : '',
		);
	}
}
if ( $show_od ) {
	$od_top = WP_MS365_Graph::aggregate_report_top_items( $od_account_rows, 'label', 'files_viewed', 10 );
	foreach ( $od_top as $row ) {
		$top_rows[] = array(
			'label'     => $row['label'],
			'sub_label' => isset( $row['sub_label'] ) ? $row['sub_label'] : '',
			'value'     => $row['value'],
			'source'    => __( 'OneDrive Documents', 'wp-ms365-graph' ),
			'url'       => isset( $row['url'] ) ? $row['url'] : '',
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
if ( $show_sp ) {
	$sp_daily_pages = WP_MS365_Graph::aggregate_report_daily_metric( $sp_site_rows, 'date', 'page_views' );
	foreach ( $sp_daily_pages as $row ) {
		$date = (string) $row['date'];
		if ( ! isset( $trend_map[ $date ] ) ) {
			$trend_map[ $date ] = 0;
		}
		$trend_map[ $date ] += (int) $row['value'];
	}

	$sp_daily_docs = WP_MS365_Graph::aggregate_report_daily_metric( $sp_file_rows, 'date', 'files_viewed' );
	foreach ( $sp_daily_docs as $row ) {
		$date = (string) $row['date'];
		if ( ! isset( $trend_map[ $date ] ) ) {
			$trend_map[ $date ] = 0;
		}
		$trend_map[ $date ] += (int) $row['value'];
	}
}
if ( $show_od ) {
	$od_daily = WP_MS365_Graph::aggregate_report_daily_metric( $od_account_rows, 'date', 'files_viewed' );
	foreach ( $od_daily as $row ) {
		$date = (string) $row['date'];
		if ( ! isset( $trend_map[ $date ] ) ) {
			$trend_map[ $date ] = 0;
		}
		$trend_map[ $date ] += (int) $row['value'];
	}
}
ksort( $trend_map );
?>

<div class="wrap msgraph_access-stats">
	<h1 class="msgraph_dashboard__heading">
		<img src="<?php echo esc_url( WP_MS365_Admin::get_icon_url() ); ?>" class="msgraph_page-icon" alt="" width="28" height="28" />
		<?php esc_html_e( 'MS Graph Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Access Statistics', 'wp-ms365-graph' ); ?>
	</h1>

	<p class="description">
		<?php esc_html_e( 'Unified view for WordPress pages/blogs/documents and SharePoint/OneDrive access metrics.', 'wp-ms365-graph' ); ?>
	</p>

	<form method="get" action="" style="margin:16px 0;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
		<input type="hidden" name="page" value="wp-ms365-graph" />
		<input type="hidden" name="tab" value="access-stats" />
		<div>
			<label for="ms365_source"><strong><?php esc_html_e( 'Source', 'wp-ms365-graph' ); ?></strong></label><br />
			<select id="ms365_source" name="ms365_source">
				<option value="all" <?php selected( $source, 'all' ); ?>><?php esc_html_e( 'All', 'wp-ms365-graph' ); ?></option>
				<option value="wordpress" <?php selected( $source, 'wordpress' ); ?>><?php esc_html_e( 'WordPress', 'wp-ms365-graph' ); ?></option>
				<option value="sharepoint" <?php selected( $source, 'sharepoint' ); ?>><?php esc_html_e( 'SharePoint', 'wp-ms365-graph' ); ?></option>
				<option value="onedrive" <?php selected( $source, 'onedrive' ); ?>><?php esc_html_e( 'OneDrive', 'wp-ms365-graph' ); ?></option>
			</select>
		</div>
		<div>
			<label for="ms365_window"><strong><?php esc_html_e( 'Window', 'wp-ms365-graph' ); ?></strong></label><br />
			<select id="ms365_window" name="ms365_window">
				<option value="7" <?php selected( $window, 7 ); ?>><?php esc_html_e( 'Last 7 days', 'wp-ms365-graph' ); ?></option>
				<option value="30" <?php selected( $window, 30 ); ?>><?php esc_html_e( 'Last 30 days', 'wp-ms365-graph' ); ?></option>
				<option value="90" <?php selected( $window, 90 ); ?>><?php esc_html_e( 'Last 90 days', 'wp-ms365-graph' ); ?></option>
				<option value="180" <?php selected( $window, 180 ); ?>><?php esc_html_e( 'Last 180 days', 'wp-ms365-graph' ); ?></option>
			</select>
		</div>
		<div>
			<label for="ms365_wp_group"><strong><?php esc_html_e( 'WordPress type', 'wp-ms365-graph' ); ?></strong></label><br />
			<select id="ms365_wp_group" name="ms365_wp_group">
				<option value="all" <?php selected( $wp_group, 'all' ); ?>><?php esc_html_e( 'All types', 'wp-ms365-graph' ); ?></option>
				<option value="page" <?php selected( $wp_group, 'page' ); ?>><?php esc_html_e( 'Pages', 'wp-ms365-graph' ); ?></option>
				<option value="blog" <?php selected( $wp_group, 'blog' ); ?>><?php esc_html_e( 'Blogs', 'wp-ms365-graph' ); ?></option>
				<option value="document" <?php selected( $wp_group, 'document' ); ?>><?php esc_html_e( 'Documents', 'wp-ms365-graph' ); ?></option>
			</select>
		</div>
		<div>
			<?php submit_button( __( 'Apply', 'wp-ms365-graph' ), 'secondary', 'submit', false ); ?>
		</div>
	</form>

	<?php if ( ( $show_sp || $show_od ) && ! $is_connected ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Microsoft 365 is not connected. SharePoint/OneDrive statistics require a connected app and Reports.Read.All application permission.', 'wp-ms365-graph' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="msgraph_card">
		<h2 class="msgraph_card__title"><?php esc_html_e( 'Summary', 'wp-ms365-graph' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Metric', 'wp-ms365-graph' ); ?></th>
					<th><?php esc_html_e( 'Value', 'wp-ms365-graph' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $show_wp ) : ?>
				<tr><td><?php esc_html_e( 'WordPress page views', 'wp-ms365-graph' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $wp_totals['page'] ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'WordPress blog views', 'wp-ms365-graph' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $wp_totals['blog'] ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'WordPress document views', 'wp-ms365-graph' ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $wp_totals['document'] ) ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $show_sp ) : ?>
				<tr><td><?php esc_html_e( 'SharePoint page/blog views', 'wp-ms365-graph' ); ?></td><td><?php echo esc_html( number_format_i18n( $sp_page_views_total ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'SharePoint document interactions', 'wp-ms365-graph' ); ?></td><td><?php echo esc_html( number_format_i18n( $sp_document_views_total ) ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $show_od ) : ?>
				<tr><td><?php esc_html_e( 'OneDrive document interactions', 'wp-ms365-graph' ); ?></td><td><?php echo esc_html( number_format_i18n( $od_document_views_total ) ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $show_sp && is_wp_error( $sp_site_error ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $sp_site_error->get_error_message() ); ?></p></div>
	<?php endif; ?>
	<?php if ( $show_sp && is_wp_error( $sp_file_error ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $sp_file_error->get_error_message() ); ?></p></div>
	<?php endif; ?>
	<?php if ( $show_od && is_wp_error( $od_error ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $od_error->get_error_message() ); ?></p></div>
	<?php endif; ?>

	<div class="msgraph_card">
		<h2 class="msgraph_card__title"><?php esc_html_e( 'Top Accessed Items', 'wp-ms365-graph' ); ?></h2>
		<?php if ( $show_sp || $show_od ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: URL to Microsoft privacy docs */
					wp_kses(
						__( '<strong>Note:</strong> Microsoft 365 report data may show obfuscated user or site names depending on your tenant\'s privacy settings. An admin can reveal real names under <a href="%s" target="_blank" rel="noopener noreferrer">Reports &rarr; Privacy settings</a> in the Microsoft 365 admin center.', 'wp-ms365-graph' ),
						array(
							'strong' => array(),
							'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
						)
					),
					'https://admin.microsoft.com/#/Settings/Services/:/Settings/L1/Reports'
				);
				?>
			</p>
		<?php endif; ?>
		<?php if ( empty( $top_rows ) ) : ?>
			<p><?php esc_html_e( 'No access data available for the current filters yet.', 'wp-ms365-graph' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'wp-ms365-graph' ); ?></th>
						<th><?php esc_html_e( 'Source', 'wp-ms365-graph' ); ?></th>
						<th><?php esc_html_e( 'Access count', 'wp-ms365-graph' ); ?></th>
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
								<?php if ( ! empty( $row['sub_label'] ) && $row['sub_label'] !== $row['label'] ) : ?>
									<br><small style="color:#888;"><?php echo esc_html( $row['sub_label'] ); ?></small>
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
		<h2 class="msgraph_card__title"><?php esc_html_e( 'Daily Trend', 'wp-ms365-graph' ); ?></h2>
		<?php if ( empty( $trend_map ) ) : ?>
			<p><?php esc_html_e( 'No trend data available for the selected period yet.', 'wp-ms365-graph' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'wp-ms365-graph' ); ?></th>
						<th><?php esc_html_e( 'Total accesses', 'wp-ms365-graph' ); ?></th>
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
