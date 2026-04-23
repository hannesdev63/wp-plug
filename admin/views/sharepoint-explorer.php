<?php
/**
 * SharePoint Explorer admin page.
 *
 * Lists all SharePoint sites the app can access, and for each site
 * enumerates the available document library drives with their IDs
 * so that site_id and drive_id values can be copied into shortcodes.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected = WP_MS365_Auth::is_connected();

// Optional keyword search from the URL, validated server-side.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search_query = isset( $_GET['ms365_sp_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ms365_sp_search'] ) ) : '*';
if ( '' === trim( $search_query ) ) {
	$search_query = '*';
}

$sites_result = null;

if ( $is_connected ) {
	$sites_result = WP_MS365_Graph::get_sharepoint_sites( $search_query );
}
?>
<div class="wrap msgraph_sp-explorer">
	<h1 class="msgraph_sp-explorer__heading">
		<img src="<?php echo esc_url( WP_MS365_Admin::get_icon_url() ); ?>" class="msgraph_page-icon" alt="" width="28" height="28" />
		<?php esc_html_e( 'MS Graph Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'SharePoint Explorer', 'wp-ms365-graph' ); ?>
	</h1>

	<p><?php esc_html_e( 'Use this page to look up the site_id and drive_id values for the [msgraph_sharepoint_library] shortcode.', 'wp-ms365-graph' ); ?></p>

	<?php if ( ! $is_connected ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Not connected to Microsoft 365. Configure credentials in Settings before using this page.', 'wp-ms365-graph' ); ?></p>
		</div>
	<?php else : ?>

		<!-- Search form -->
		<form method="get" action="" class="msgraph_sp-explorer__search">
			<input type="hidden" name="page" value="wp-ms365-graph" />
			<input type="hidden" name="tab" value="sp-explorer" />
			<label for="ms365_sp_search"><strong><?php esc_html_e( 'Filter sites by name:', 'wp-ms365-graph' ); ?></strong></label>
			<input
				type="text"
				id="ms365_sp_search"
				name="ms365_sp_search"
				value="<?php echo esc_attr( '*' === $search_query ? '' : $search_query ); ?>"
				placeholder="<?php esc_attr_e( '* = all', 'wp-ms365-graph' ); ?>"
				class="regular-text"
			/>
			<?php submit_button( __( 'Search', 'wp-ms365-graph' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php if ( is_wp_error( $sites_result ) ) : ?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Could not retrieve sites:', 'wp-ms365-graph' ); ?></strong>
					<?php echo esc_html( $sites_result->get_error_message() ); ?>
				</p>
			</div>
		<?php elseif ( empty( $sites_result['value'] ) ) : ?>
			<p class="description"><?php esc_html_e( 'No SharePoint sites found. The app may need the Sites.Read.All application permission (with admin consent) to list sites.', 'wp-ms365-graph' ); ?></p>
		<?php else : ?>

			<p class="description" id="ms365-sp-results-meta" data-total-sites="<?php echo esc_attr( count( $sites_result['value'] ) ); ?>">
				<?php
				printf(
					/* translators: %d: number of sites found */
					esc_html( _n( 'Found %d site.', 'Found %d sites.', count( $sites_result['value'] ), 'wp-ms365-graph' ) ),
					count( $sites_result['value'] )
				);
				?>
			</p>

			<?php foreach ( $sites_result['value'] as $site ) : ?>
				<?php
				$sid         = isset( $site['id'] ) ? (string) $site['id'] : '';
				$s_name      = isset( $site['displayName'] ) ? (string) $site['displayName'] : ( isset( $site['name'] ) ? (string) $site['name'] : '—' );
				$s_url       = isset( $site['webUrl'] ) ? (string) $site['webUrl'] : '';
				if ( '' === $sid ) {
					continue;
				}
				?>
				<div class="msgraph_card msgraph_sp-explorer__site" data-site-id="<?php echo esc_attr( $sid ); ?>" style="margin-bottom:1.5em;">
					<h3 style="margin-top:0;">
						<?php echo esc_html( $s_name ); ?>
						<?php if ( $s_url ) : ?>
							<a href="<?php echo esc_url( $s_url ); ?>" target="_blank" rel="noopener noreferrer" style="font-size:.85em;font-weight:normal;margin-left:.5em;">
								<?php esc_html_e( 'Open site ↗', 'wp-ms365-graph' ); ?>
							</a>
						<?php endif; ?>
					</h3>

					<table class="form-table msgraph_sp-explorer__meta" style="margin-top:0;">
						<tr>
							<th scope="row" style="width:120px;"><?php esc_html_e( 'site_id', 'wp-ms365-graph' ); ?></th>
							<td>
								<code class="msgraph_sp-explorer__copyable"><?php echo esc_html( $sid ); ?></code>
								<button
									type="button"
									class="button button-small msgraph_copy-btn"
									data-copy="<?php echo esc_attr( $sid ); ?>"
									title="<?php esc_attr_e( 'Copy site_id', 'wp-ms365-graph' ); ?>"
								><?php esc_html_e( 'Copy', 'wp-ms365-graph' ); ?></button>
							</td>
						</tr>
					</table>

					<h4><?php esc_html_e( 'Document Libraries (drives)', 'wp-ms365-graph' ); ?></h4>
					<div class="msgraph_sp-explorer__drives-container" data-state="loading">
						<div class="msgraph_sp-skeleton" aria-hidden="true">
							<div class="msgraph_sp-skeleton__line msgraph_sp-skeleton__line--lg"></div>
							<div class="msgraph_sp-skeleton__line"></div>
							<div class="msgraph_sp-skeleton__line"></div>
						</div>
						<p class="description msgraph_sp-explorer__loading-text"><?php esc_html_e( 'Loading drives...', 'wp-ms365-graph' ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>

		<?php endif; ?>

	<?php endif; ?>
</div>

<script>
( function () {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_ms365_sp_explorer' ) ); ?>;
	var cards = Array.prototype.slice.call( document.querySelectorAll( '.msgraph_sp-explorer__site[data-site-id]' ) );
	var hiddenDueToPermissions = 0;

	function escapeHtml( value ) {
		return String( value || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function setCopyFeedback( btn ) {
		var orig = btn.textContent;
		btn.textContent = '✓';
		setTimeout( function () { btn.textContent = orig; }, 1200 );
	}

	function copyText( text, btn ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( function () {
				setCopyFeedback( btn );
			} );
			return;
		}

		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity  = '0';
		document.body.appendChild( ta );
		ta.select();
		document.execCommand( 'copy' );
		document.body.removeChild( ta );
		setCopyFeedback( btn );
	}

	document.addEventListener( 'click', function ( evt ) {
		var btn = evt.target.closest( '.msgraph_copy-btn' );
		if ( ! btn ) {
			return;
		}

		var text = btn.getAttribute( 'data-copy' );
		if ( text ) {
			copyText( text, btn );
		}
	} );

	function updateResultsMeta() {
		var meta = document.getElementById( 'ms365-sp-results-meta' );
		if ( ! meta ) {
			return;
		}

		var visibleCount = document.querySelectorAll( '.msgraph_sp-explorer__site[data-site-id]' ).length;
		var label = visibleCount === 1 ? 'Found 1 site.' : 'Found ' + visibleCount + ' sites.';

		if ( hiddenDueToPermissions > 0 ) {
			label += ' Hidden ' + hiddenDueToPermissions + ' access-denied ' + ( hiddenDueToPermissions === 1 ? 'site.' : 'sites.' );
		}

		meta.textContent = label;
	}

	function renderDrivesTable( siteId, drives ) {
		if ( ! drives || ! drives.length ) {
			return '<p class="description"><?php echo esc_js( __( 'No drives found for this site.', 'wp-ms365-graph' ) ); ?></p>';
		}

		var rows = drives.map( function ( drive ) {
			var driveId = String( drive.id || '' );
			var driveName = String( drive.name || '—' );
			var driveType = String( drive.driveType || '' );
			var driveUrl = String( drive.webUrl || '' );
			var snippet = '[msgraph_sharepoint_library site_id="' + siteId + '" drive_id="' + driveId + '" title="' + driveName + '" folder=""]';
			var openLink = driveUrl
				? ' <a href="' + escapeHtml( driveUrl ) + '" target="_blank" rel="noopener noreferrer" title="<?php echo esc_js( __( 'Open library', 'wp-ms365-graph' ) ); ?>">↗</a>'
				: '';

			return ''
				+ '<tr>'
				+ '<td>' + escapeHtml( driveName ) + openLink + '</td>'
				+ '<td><code>' + escapeHtml( driveType ) + '</code></td>'
				+ '<td>'
				+ '<code style="word-break:break-all;">' + escapeHtml( snippet ) + '</code> '
				+ '<button type="button" class="button button-small msgraph_copy-btn" data-copy="' + escapeHtml( snippet ) + '"><?php echo esc_js( __( 'Copy', 'wp-ms365-graph' ) ); ?></button>'
				+ '</td>'
				+ '</tr>';
		} ).join( '' );

		return ''
			+ '<table class="widefat striped msgraph_sp-explorer__drives">'
			+ '<thead><tr>'
			+ '<th><?php echo esc_js( __( 'Library Name', 'wp-ms365-graph' ) ); ?></th>'
			+ '<th><?php echo esc_js( __( 'Type', 'wp-ms365-graph' ) ); ?></th>'
			+ '<th><?php echo esc_js( __( 'Shortcode snippet', 'wp-ms365-graph' ) ); ?></th>'
			+ '</tr></thead>'
			+ '<tbody>' + rows + '</tbody>'
			+ '</table>';
	}

	function loadDrivesForCard( card ) {
		var siteId = card.getAttribute( 'data-site-id' );
		var container = card.querySelector( '.msgraph_sp-explorer__drives-container' );
		if ( ! siteId || ! container ) {
			return Promise.resolve();
		}

		return fetch( ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: new URLSearchParams( {
				action: 'wp_ms365_sp_site_drives',
				nonce: nonce,
				site_id: siteId
			} )
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( payload ) {
			if ( payload && payload.success && payload.data ) {
				container.innerHTML = renderDrivesTable( siteId, payload.data.drives || [] );
				container.setAttribute( 'data-state', 'loaded' );
				return;
			}

			var accessDenied = payload && payload.data && payload.data.access_denied;
			if ( accessDenied ) {
				card.remove();
				hiddenDueToPermissions += 1;
				updateResultsMeta();
				return;
			}

			var message = payload && payload.data && payload.data.message
				? payload.data.message
				: '<?php echo esc_js( __( 'Could not load drives.', 'wp-ms365-graph' ) ); ?>';
			container.innerHTML = '<p class="description">' + escapeHtml( message ) + '</p>';
			container.setAttribute( 'data-state', 'error' );
		} ).catch( function () {
			container.innerHTML = '<p class="description"><?php echo esc_js( __( 'Could not load drives.', 'wp-ms365-graph' ) ); ?></p>';
			container.setAttribute( 'data-state', 'error' );
		} );
	}

	function runQueue( tasks, concurrency ) {
		var index = 0;

		function next() {
			if ( index >= tasks.length ) {
				return Promise.resolve();
			}

			var task = tasks[ index ];
			index += 1;
			return task().then( next, next );
		}

		var workers = [];
		for ( var i = 0; i < concurrency; i++ ) {
			workers.push( next() );
		}

		return Promise.all( workers );
	}

	if ( cards.length ) {
		var tasks = cards.map( function ( card ) {
			return function () {
				return loadDrivesForCard( card );
			};
		} );

		runQueue( tasks, Math.min( 4, cards.length ) ).then( function () {
			updateResultsMeta();
		} );
	}
} )();
</script>
