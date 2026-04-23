<?php
/**
 * Shortcode documentation tab view.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap msgraph_documentation">
	<h1 class="msgraph_settings__heading">
		<img src="<?php echo esc_url( WP_MS365_Admin::get_icon_url() ); ?>" class="msgraph_page-icon" alt="" width="28" height="28" />
		<?php esc_html_e( 'MS Graph Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Documentation', 'wp-ms365-graph' ); ?>
	</h1>

	<p>
		<?php esc_html_e( 'Use the following shortcodes in posts, pages, or widgets. Parameters are optional unless marked as required.', 'wp-ms365-graph' ); ?>
	</p>

	<hr />
	<h2><code>[msgraph_calendar]</code></h2>
	<p><?php esc_html_e( 'Displays upcoming events from the configured Specific User calendar.', 'wp-ms365-graph' ); ?></p>
	<table class="widefat striped msgraph_shortcode-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Parameter', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Default', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Description', 'wp-ms365-graph' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>limit</code></td><td><code>5</code></td><td><?php esc_html_e( 'Maximum number of rows to render.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>timezone</code></td><td><code>Site timezone</code></td><td><?php esc_html_e( 'Target timezone for date/time display (for example Europe/London).', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>title</code></td><td><code>""</code></td><td><?php esc_html_e( 'Optional heading above the table.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>past_days</code></td><td><code>0</code></td><td><?php esc_html_e( 'Include events that ended in the last N days.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>columns</code></td><td><code>all</code></td><td><?php esc_html_e( 'Comma-separated list from: date,event,duration,location.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>duration_display</code></td><td><code>hours_minutes</code></td><td><?php esc_html_e( 'Duration mode: hours_minutes or start_end.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>group_by_date</code></td><td><code>false</code></td><td><?php esc_html_e( 'Group events by start date so multiple events are listed under one date. In grouped mode, event times are shown as HH:MM ranges.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>categories</code></td><td><code>""</code></td><td><?php esc_html_e( 'Comma-separated category filter. Empty means all categories.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>show_headers</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show or hide table header row.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_calendar]</code></p>
	<p><code>[msgraph_calendar limit="8" timezone="Europe/Berlin" past_days="2" title="Upcoming Events"]</code></p>
	<p><code>[msgraph_calendar columns="date,event,duration" duration_display="start_end" categories="Townhall,Leadership"]</code></p>
	<p><code>[msgraph_calendar group_by_date="true" columns="date,event,duration,location" title="Team Calendar"]</code></p>

	<hr />
	<h2><code>[msgraph_files]</code></h2>
	<p><?php esc_html_e( 'Displays file-only results from the configured Specific User OneDrive.', 'wp-ms365-graph' ); ?></p>
	<table class="widefat striped msgraph_shortcode-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Parameter', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Default', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Description', 'wp-ms365-graph' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>limit</code></td><td><code>50</code></td><td><?php esc_html_e( 'Maximum number of file rows to render.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>folder</code></td><td><code>""</code></td><td><?php esc_html_e( 'Optional relative folder path inside OneDrive root.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>title</code></td><td><code>""</code></td><td><?php esc_html_e( 'Optional heading above the table.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>show_headers</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show or hide table header row.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_files]</code></p>
	<p><code>[msgraph_files limit="20" folder="Documents/Policies" title="Policy Files"]</code></p>
	<p><code>[msgraph_files limit="15" show_headers="false"]</code></p>

	<hr />
	<h2><code>[msgraph_sharepoint_library]</code></h2>
	<p><?php esc_html_e( 'Displays file-only results from a SharePoint document library.', 'wp-ms365-graph' ); ?></p>
	<table class="widefat striped msgraph_shortcode-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Parameter', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Default', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Description', 'wp-ms365-graph' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>site_id</code></td><td><code>required</code></td><td><?php esc_html_e( 'SharePoint site identifier.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>drive_id</code></td><td><code>required</code></td><td><?php esc_html_e( 'Document library drive identifier.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>limit</code></td><td><code>50</code></td><td><?php esc_html_e( 'Maximum number of file rows to render.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>folder</code></td><td><code>""</code></td><td><?php esc_html_e( 'Optional relative folder path inside the library.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>title</code></td><td><code>""</code></td><td><?php esc_html_e( 'Optional heading above the table.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>show_headers</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show or hide table header row.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" folder="Shared Documents/HR" limit="30" title="HR Library"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" show_headers="false"]</code></p>

	<hr />
	<h2><?php esc_html_e( 'Tip', 'wp-ms365-graph' ); ?></h2>
	<p>
		<?php esc_html_e( 'Use the SP Explorer tab to discover SharePoint site_id and drive_id values and copy ready-to-use shortcode snippets.', 'wp-ms365-graph' ); ?>
	</p>
</div>
