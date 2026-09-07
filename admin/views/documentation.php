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
		<?php esc_html_e( 'ESC Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Documentation', 'wp-ms365-graph' ); ?>
	</h1>

	<p>
		<?php esc_html_e( 'Use the following shortcodes in posts, pages, or widgets. Parameters are optional unless marked as required.', 'wp-ms365-graph' ); ?>
	</p>

	<hr />
	<h2><?php esc_html_e( 'Required Microsoft Graph API Rights', 'wp-ms365-graph' ); ?></h2>
	<p>
		<?php esc_html_e( 'This plugin uses app-only authentication (client credentials), so configure Microsoft Graph Application permissions in Azure and grant admin consent.', 'wp-ms365-graph' ); ?>
	</p>
	<table class="widefat striped msgraph_shortcode-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Feature', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Microsoft Graph Application Permission', 'wp-ms365-graph' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><?php esc_html_e( 'Read configured user profile', 'wp-ms365-graph' ); ?></td><td><code>User.Read.All</code></td></tr>
			<tr><td><?php esc_html_e( 'Microsoft Entra profile picture (avatar)', 'wp-ms365-graph' ); ?></td><td><code>User.Read.All</code></td></tr>
			<tr><td><code>[msgraph_calendar]</code></td><td><code>Calendars.Read</code></td></tr>
			<tr><td><code>[msgraph_files]</code> (OneDrive)</td><td><code>Files.Read.All</code></td></tr>
			<tr><td><code>[msgraph_sharepoint_library]</code> (SharePoint)</td><td><code>Sites.Read.All</code></td></tr>
			<tr><td><code>[msgraph_sharepoint_team]</code> (SharePoint XLSX)</td><td><code>Sites.Read.All</code></td></tr>
			<tr><td><code>[msgraph_teams_message_form]</code></td><td><?php esc_html_e( 'No Graph permission required (uses Teams Workflow endpoint URL).', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p>
		<?php esc_html_e( 'After adding these permissions, click "Grant admin consent" in Azure, then request/save a fresh token in plugin settings.', 'wp-ms365-graph' ); ?>
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
			<tr><td><code>calendar</code></td><td><code>default calendar</code></td><td><?php esc_html_e( 'Optional calendar ID. Use the Calendar Explorer tab to copy the shortcode for a specific calendar you can access.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>past_days</code></td><td><code>0</code></td><td><?php esc_html_e( 'Include events that ended in the last N days.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>columns</code></td><td><code>all</code></td><td><?php esc_html_e( 'Comma-separated list from: date,event,duration,location.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>duration_display</code></td><td><code>hours_minutes</code></td><td><?php esc_html_e( 'Duration mode: hours_minutes, hours, minutes, compact or start_end. Examples: 1 hour 30 minutes, 1h 30m, 90 minutes, 09:00 - 10:30.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>group_by_date</code></td><td><code>false</code></td><td><?php esc_html_e( 'Group events by start date so multiple events are listed under one date. In grouped mode, event times are shown as HH:MM ranges.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>categories</code></td><td><code>""</code></td><td><?php esc_html_e( 'Comma-separated category filter. Empty means all categories.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>show_headers</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show or hide table header row.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>class</code></td><td><code>msgraph_calendar</code></td><td><?php esc_html_e( 'Additional wrapper classes merged with default calendar wrapper class.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>table_class</code></td><td><code>msgraph_table msgraph_calendar__table</code></td><td><?php esc_html_e( 'Additional classes merged with default calendar table classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>item_class</code></td><td><code>msgraph_calendar__item</code></td><td><?php esc_html_e( 'Additional classes merged with each calendar row item.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_calendar]</code></p>
	<p><code>[msgraph_calendar calendar="AAMkAGI2..."]</code></p>
	<p><code>[msgraph_calendar limit="8" timezone="Europe/Berlin" past_days="2" title="Upcoming Events"]</code></p>
	<p><code>[msgraph_calendar columns="date,event,duration" duration_display="start_end" categories="Townhall,Leadership"]</code></p>
	<p><code>[msgraph_calendar columns="date,event,duration" duration_display="minutes" title="Meeting Lengths"]</code></p>
	<p><code>[msgraph_calendar columns="date,event,duration" duration_display="compact" title="Compact Schedule"]</code></p>
	<p><code>[msgraph_calendar group_by_date="true" columns="date,event,duration,location" title="Team Calendar"]</code></p>
	<p><code>[msgraph_calendar class="my-calendar" table_class="my-calendar-table" item_class="my-calendar-row"]</code></p>
	<p class="description">
		<?php esc_html_e( 'Note: Calendar Explorer only lists calendars the configured user can access. To give another user edit access to a specific calendar, share that calendar in Outlook or Exchange and grant Can edit; after sharing, the calendar can be selected in Calendar Explorer and copied into the shortcode.', 'wp-ms365-graph' ); ?>
	</p>

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
			<tr><td><code>columns</code></td><td><code>all</code></td><td><?php esc_html_e( 'Comma-separated list of columns to display: file,size,modified. Unknown values are ignored.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>column_order</code></td><td><code>file,size,modified</code></td><td><?php esc_html_e( 'Optional default column order used when columns is not supplied. This overrides the built-in order but still respects hide_columns.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>hide_columns</code></td><td><code>none</code></td><td><?php esc_html_e( 'Comma-separated list of columns to hide after the include list is applied. If a column appears in both columns and hide_columns, hide_columns wins.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>download_columns</code></td><td><code>file</code></td><td><?php esc_html_e( 'Comma-separated list of columns that render as download links. Use none to disable download links. Currently only the file column is downloadable.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>show_headers</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show or hide table header row.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>class</code></td><td><code>msgraph_files</code></td><td><?php esc_html_e( 'Additional wrapper classes merged with default files wrapper class.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>table_class</code></td><td><code>msgraph_table msgraph_files__table</code></td><td><?php esc_html_e( 'Additional classes merged with default files table classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>item_class</code></td><td><code>msgraph_files__item</code></td><td><?php esc_html_e( 'Additional classes merged with each file row item.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_files]</code></p>
	<p><code>[msgraph_files limit="20" folder="Documents/Policies" title="Policy Files"]</code></p>
	<p><code>[msgraph_files columns="file,size" hide_columns="size" download_columns="file" show_headers="true"]</code></p>
	<p><code>[msgraph_files column_order="modified,file" download_columns="file" show_headers="true"]</code></p>
	<p><code>[msgraph_files limit="15" download_columns="none" show_headers="false"]</code></p>
	<p><code>[msgraph_files class="my-files" table_class="my-files-table" item_class="my-files-row"]</code></p>

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
			<tr><td><code>columns</code></td><td><code>all</code></td><td><?php esc_html_e( 'Comma-separated list of columns to display: file,size,modified. Unknown values are ignored.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>column_order</code></td><td><code>file,size,modified</code></td><td><?php esc_html_e( 'Optional default column order used when columns is not supplied. This overrides the built-in order but still respects hide_columns.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>hide_columns</code></td><td><code>none</code></td><td><?php esc_html_e( 'Comma-separated list of columns to hide after the include list is applied. If a column appears in both columns and hide_columns, hide_columns wins.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>download_columns</code></td><td><code>file</code></td><td><?php esc_html_e( 'Comma-separated list of columns that render as download links. Use none to disable download links. Currently only the file column is downloadable.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>image_columns</code></td><td><code>none</code></td><td><?php esc_html_e( 'Comma-separated list of columns whose values are treated as image file names and rendered as images.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>image_basepath</code></td><td><code>settings value (fallback: WordPress uploads URL)</code></td><td><?php esc_html_e( 'Base URL prepended to image file names for image_columns. If omitted, the plugin setting SharePoint Image Base Path is used; if that is empty, the WordPress uploads URL is used.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>show_headers</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show or hide table header row.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>class</code></td><td><code>msgraph_files msgraph_files--sharepoint</code></td><td><?php esc_html_e( 'Additional wrapper classes merged with default SharePoint wrapper classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>table_class</code></td><td><code>msgraph_table msgraph_files__table</code></td><td><?php esc_html_e( 'Additional classes merged with default SharePoint table classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>item_class</code></td><td><code>msgraph_files__item</code></td><td><?php esc_html_e( 'Additional classes merged with each SharePoint file row item.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" folder="Shared Documents/HR" limit="30" title="HR Library"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" columns="file,size" hide_columns="size" download_columns="file" show_headers="true"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" column_order="modified,file" download_columns="file" show_headers="true"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" download_columns="none" show_headers="false"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" columns="file" image_columns="file"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" columns="file" image_columns="file" image_basepath="https://example.com/wp-content/uploads/team"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" class="my-sp-files" table_class="my-sp-table" item_class="my-sp-row"]</code></p>
	<p><?php esc_html_e( 'Default image base path can be configured in Microsoft 365 -> Settings -> SharePoint Image Base Path.', 'wp-ms365-graph' ); ?></p>

	<hr />
	<h2><code>[msgraph_sharepoint_team]</code></h2>
	<p><?php esc_html_e( 'Reads an XLSX file from a SharePoint document library and renders rows using the columns available in the sheet. Rows where column Aktiv equals Aktiv are hidden (case-insensitive).', 'wp-ms365-graph' ); ?></p>
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
			<tr><td><code>folder</code></td><td><code>""</code></td><td><?php esc_html_e( 'Optional relative folder path inside the library.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>name</code></td><td><code>team.xlsx</code></td><td><?php esc_html_e( 'XLSX filename in the selected SharePoint folder.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>title</code></td><td><code>""</code></td><td><?php esc_html_e( 'Optional heading above the table.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>show_headers</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show or hide the table header row.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>sort_columns</code></td><td><code>Position, Order, Name</code></td><td><?php esc_html_e( 'Comma-separated list of column names used to sort rows. Missing columns are ignored.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>display_columns</code></td><td><code>all columns</code></td><td><?php esc_html_e( 'Comma-separated list of columns to display. By default, all available columns are shown.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>hide_columns</code></td><td><code>none</code></td><td><?php esc_html_e( 'Comma-separated list of columns to hide. If a column is in both display_columns and hide_columns, hide_columns wins.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>formatter</code></td><td><code>none</code></td><td><?php esc_html_e( 'Per-column numeric formatter rules separated by semicolons. Syntax: Column:decimals[:decimal_point[:thousands_sep]] or Column:locale[:decimals]. Supported locales: de-DE, en-US, en-GB, fr-FR, it-IT, es-ES (also short forms like de, en, fr).', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>class</code></td><td><code>msgraph_team msgraph_team--sharepoint</code></td><td><?php esc_html_e( 'Additional wrapper classes merged with default team wrapper classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>table_class</code></td><td><code>msgraph_table msgraph_team__table</code></td><td><?php esc_html_e( 'Additional classes merged with default team table classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>item_class</code></td><td><code>msgraph_team__item</code></td><td><?php esc_html_e( 'Additional classes merged with each team row item.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_sharepoint_team site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123"]</code></p>
	<p><code>[msgraph_sharepoint_team site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" folder="Shared Documents/Teams" name="TeamTemplate2.xlsx" title="Team"]</code></p>
	<p><code>[msgraph_sharepoint_team site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" sort_columns="Position,Order,Name" display_columns="Name,Position,Number" hide_columns="Number"]</code></p>
	<p><code>[msgraph_sharepoint_team site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" formatter="Order:0; Salary:2:,:."]</code></p>
	<p><code>[msgraph_sharepoint_team site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" formatter="Salary:de-DE:2; MarketValue:en-US:0"]</code></p>
	<p><code>[msgraph_sharepoint_team site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" show_headers="false" class="my-team" table_class="my-team-table" item_class="my-team-row"]</code></p>

	<hr />
	<h2><code>[msgraph_login_button]</code></h2>
	<p><?php esc_html_e( 'Displays a Microsoft Entra sign-in button for custom login pages.', 'wp-ms365-graph' ); ?></p>
	<table class="widefat striped msgraph_shortcode-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Parameter', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Default', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Description', 'wp-ms365-graph' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>label</code></td><td><?php esc_html_e( 'Sign-In wording setting value (fallback: "Sign in with Microsoft")', 'wp-ms365-graph' ); ?></td><td><?php esc_html_e( 'Button label text. If omitted, uses the Entra Sign-In Button Text from Wording settings.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>redirect_to</code></td><td><code>current page</code></td><td><?php esc_html_e( 'Internal URL to redirect to after successful sign-in.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>class</code></td><td><code>""</code></td><td><?php esc_html_e( 'Extra CSS class(es) added to the button wrapper.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_login_button]</code></p>
	<p><code>[msgraph_login_button label="Log in with Company Account" redirect_to="/dashboard"]</code></p>
	<p><code>[msgraph_login_button class="my-login-button"]</code></p>
	<p>
		<?php esc_html_e( 'Wording source: Microsoft 365 -> Wording -> Sign-In Wording -> Entra Sign-In Button Text.', 'wp-ms365-graph' ); ?>
	</p>
	<p><strong><?php esc_html_e( 'Settings persistence check', 'wp-ms365-graph' ); ?>:</strong></p>
	<ol>
		<li><?php esc_html_e( 'Open Microsoft 365 -> Settings -> WordPress Sign-In (Microsoft Tenant), enable both "Enable Tenant Sign-In" and "Auto-Create Users", then save.', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'Open Microsoft 365 -> Wording, change any wording value, and save.', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'Return to Microsoft 365 -> Settings and verify both sign-in options are still enabled.', 'wp-ms365-graph' ); ?></li>
	</ol>
	<p><strong><?php esc_html_e( 'Custom sign-in button image', 'wp-ms365-graph' ); ?>:</strong></p>
	<p>
		<?php esc_html_e( 'Set under Microsoft 365 -> Wording -> Sign-In Wording -> Entra Sign-In Button Image.', 'wp-ms365-graph' ); ?>
		<?php esc_html_e( 'The image replaces the Microsoft SVG logo and is rendered as a 20×20 px icon inside the sign-in button.', 'wp-ms365-graph' ); ?>
		<?php esc_html_e( 'Use a square image no larger than 200×200 px (PNG or SVG with transparency works best).', 'wp-ms365-graph' ); ?>
		<?php esc_html_e( 'The media library picker warns you if the selected image exceeds 200×200 px.', 'wp-ms365-graph' ); ?>
		<?php esc_html_e( 'Leave blank to revert to the default Microsoft logo.', 'wp-ms365-graph' ); ?>
	</p>

	<hr />
	<h2><?php esc_html_e( 'Microsoft Entra Profile Picture (Avatar)', 'wp-ms365-graph' ); ?></h2>
	<p>
		<?php esc_html_e( 'When enabled, the plugin fetches each Entra-linked user\'s Microsoft profile photo via the Graph API and uses it as their WordPress avatar, replacing the default Gravatar.', 'wp-ms365-graph' ); ?>
	</p>
	<p><strong><?php esc_html_e( 'How to enable', 'wp-ms365-graph' ); ?>:</strong></p>
	<ol>
		<li><?php esc_html_e( 'Open Microsoft 365 -> Settings -> WordPress Sign-In (Microsoft Tenant).', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'Enable "Use Microsoft Profile Picture" and save.', 'wp-ms365-graph' ); ?></li>
	</ol>
	<p><strong><?php esc_html_e( 'Behaviour', 'wp-ms365-graph' ); ?>:</strong></p>
	<ul>
		<li><?php esc_html_e( 'Only applies to users who have previously signed in via Microsoft Entra SSO (linked accounts).', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'The photo is fetched on first avatar render, saved to the wp-content/uploads/ms365-avatars/ directory, and cached for 12 hours via a WP transient.', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'If the user has no Entra profile photo the avatar falls back to the default WordPress/Gravatar avatar silently.', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'To force a refresh, delete the transient wp_ms365_avatar_{user_id} from the database (e.g. via WP-CLI or a caching plugin flush).', 'wp-ms365-graph' ); ?></li>
	</ul>
	<p><strong><?php esc_html_e( 'Required Graph permission', 'wp-ms365-graph' ); ?>:</strong> <code>User.Read.All</code> <?php esc_html_e( '(application permission, already required for profile reads).', 'wp-ms365-graph' ); ?></p>

	<hr />
	<h2><code>[msgraph_teams_message_form]</code></h2>
	<p><?php esc_html_e( 'Displays a public message form and posts submissions to Microsoft Teams using a Workflow endpoint URL. Sender name and email are required.', 'wp-ms365-graph' ); ?></p>
	<table class="widefat striped msgraph_shortcode-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Parameter', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Default', 'wp-ms365-graph' ); ?></th>
				<th><?php esc_html_e( 'Description', 'wp-ms365-graph' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td><code>endpoint_url</code></td><td><code>settings value</code></td><td><?php esc_html_e( 'Teams Workflow endpoint URL used to deliver form submissions. Required if no default is configured in plugin settings.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>webhook_url</code></td><td><code>""</code></td><td><?php esc_html_e( 'Legacy alias for endpoint_url (kept for backward compatibility).', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>use_adaptive_card</code></td><td><code>auto</code></td><td><?php esc_html_e( 'Delivery mode selector: auto, true, or false. Default auto detects workflow URLs.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>team_id</code></td><td><code>settings value</code></td><td><?php esc_html_e( 'Optional Team ID metadata included only for compatibility/labeling.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>channel_id</code></td><td><code>settings value</code></td><td><?php esc_html_e( 'Optional Channel ID metadata included only for compatibility/labeling.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>title</code></td><td><code>""</code></td><td><?php esc_html_e( 'Optional heading above the form.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>placeholder</code></td><td><code>"Type your message"</code></td><td><?php esc_html_e( 'Textarea placeholder text.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>button_text</code></td><td><code>"Send Message"</code></td><td><?php esc_html_e( 'Submit button label.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>max_length</code></td><td><code>1000</code></td><td><?php esc_html_e( 'Maximum message length (20-4000).', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>class</code></td><td><code>msgraph_teams_form-wrap</code></td><td><?php esc_html_e( 'Additional wrapper classes merged with default Teams form wrapper class.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>form_class</code></td><td><code>msgraph_teams_form</code></td><td><?php esc_html_e( 'Additional classes merged with the form element class.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>input_class</code></td><td><code>msgraph_teams_form__input</code></td><td><?php esc_html_e( 'Additional classes merged with name and email input classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>textarea_class</code></td><td><code>msgraph_teams_form__textarea</code></td><td><?php esc_html_e( 'Additional classes merged with message textarea class.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>submit_class</code></td><td><code>msgraph_teams_form__submit</code></td><td><?php esc_html_e( 'Additional classes merged with submit button class.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_teams_message_form]</code></p>
	<p><code>[msgraph_teams_message_form endpoint_url="https://prod-00.westeurope.logic.azure.com:443/workflows/..." title="Send us a message"]</code></p>
	<p><code>[msgraph_teams_message_form button_text="Submit" placeholder="How can we help?" max_length="1500"]</code></p>
	<p><code>[msgraph_teams_message_form endpoint_url="https://..." use_adaptive_card="true"]</code></p>
	<p><code>[msgraph_teams_message_form endpoint_url="https://..." use_adaptive_card="false"]</code></p>
	<p><code>[msgraph_teams_message_form class="my-teams-wrap" form_class="my-teams-form" input_class="my-teams-input" textarea_class="my-teams-textarea" submit_class="my-teams-submit"]</code></p>
	<p><strong><?php esc_html_e( 'Create the Teams workflow endpoint', 'wp-ms365-graph' ); ?>:</strong></p>
	<ol>
		<li><?php esc_html_e( 'Open Microsoft Teams and select the target team channel.', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'Open Workflows and choose the trigger "When a Teams webhook request is received".', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'Default mode posts plain text (webhook style). For adaptive cards, use action "Post card in a chat or channel" and set card payload to trigger body field "adaptive_card".', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'Save the workflow, copy the generated HTTP POST URL, and paste it into plugin setting "Teams Workflow Endpoint URL".', 'wp-ms365-graph' ); ?></li>
		<li><?php esc_html_e( 'Save settings and submit a test message from the form shortcode.', 'wp-ms365-graph' ); ?></li>
	</ol>
	<p>
		<?php esc_html_e( 'Auto mode enables adaptive cards for workflow-style URLs (for example logic.azure.com). You can force mode with shortcode param use_adaptive_card="true" or "false".', 'wp-ms365-graph' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Note: in adaptive-card mode, team_id and channel_id are hidden in Settings and omitted from the payload.', 'wp-ms365-graph' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Rate limiting for this form is enforced server-side using the plugin settings fields "Teams Form Rate Limit: Max Requests" and "Teams Form Rate Limit: Window (seconds)". Additional anti-bot checks include a hidden honeypot field and a configurable minimum submit time ("Teams Form: Minimum Submit Time (seconds)").', 'wp-ms365-graph' ); ?>
	</p>

	<hr />
	<h2><?php esc_html_e( 'Tip', 'wp-ms365-graph' ); ?></h2>
	<p>
		<?php esc_html_e( 'Use the SP Explorer tab to discover SharePoint site_id and drive_id values and copy ready-to-use shortcode snippets.', 'wp-ms365-graph' ); ?>
	</p>
</div>
