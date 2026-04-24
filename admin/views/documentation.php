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
			<tr><td><code>[msgraph_calendar]</code></td><td><code>Calendars.Read</code></td></tr>
			<tr><td><code>[msgraph_files]</code> (OneDrive)</td><td><code>Files.Read.All</code></td></tr>
			<tr><td><code>[msgraph_sharepoint_library]</code> (SharePoint)</td><td><code>Sites.Read.All</code></td></tr>
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
			<tr><td><code>past_days</code></td><td><code>0</code></td><td><?php esc_html_e( 'Include events that ended in the last N days.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>columns</code></td><td><code>all</code></td><td><?php esc_html_e( 'Comma-separated list from: date,event,duration,location.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>duration_display</code></td><td><code>hours_minutes</code></td><td><?php esc_html_e( 'Duration mode: hours_minutes or start_end.', 'wp-ms365-graph' ); ?></td></tr>
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
	<p><code>[msgraph_calendar limit="8" timezone="Europe/Berlin" past_days="2" title="Upcoming Events"]</code></p>
	<p><code>[msgraph_calendar columns="date,event,duration" duration_display="start_end" categories="Townhall,Leadership"]</code></p>
	<p><code>[msgraph_calendar group_by_date="true" columns="date,event,duration,location" title="Team Calendar"]</code></p>
	<p><code>[msgraph_calendar class="my-calendar" table_class="my-calendar-table" item_class="my-calendar-row"]</code></p>

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
			<tr><td><code>class</code></td><td><code>msgraph_files</code></td><td><?php esc_html_e( 'Additional wrapper classes merged with default files wrapper class.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>table_class</code></td><td><code>msgraph_table msgraph_files__table</code></td><td><?php esc_html_e( 'Additional classes merged with default files table classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>item_class</code></td><td><code>msgraph_files__item</code></td><td><?php esc_html_e( 'Additional classes merged with each file row item.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_files]</code></p>
	<p><code>[msgraph_files limit="20" folder="Documents/Policies" title="Policy Files"]</code></p>
	<p><code>[msgraph_files limit="15" show_headers="false"]</code></p>
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
			<tr><td><code>show_headers</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show or hide table header row.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>class</code></td><td><code>msgraph_files msgraph_files--sharepoint</code></td><td><?php esc_html_e( 'Additional wrapper classes merged with default SharePoint wrapper classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>table_class</code></td><td><code>msgraph_table msgraph_files__table</code></td><td><?php esc_html_e( 'Additional classes merged with default SharePoint table classes.', 'wp-ms365-graph' ); ?></td></tr>
			<tr><td><code>item_class</code></td><td><code>msgraph_files__item</code></td><td><?php esc_html_e( 'Additional classes merged with each SharePoint file row item.', 'wp-ms365-graph' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Samples', 'wp-ms365-graph' ); ?>:</strong></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" folder="Shared Documents/HR" limit="30" title="HR Library"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" show_headers="false"]</code></p>
	<p><code>[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" class="my-sp-files" table_class="my-sp-table" item_class="my-sp-row"]</code></p>

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
