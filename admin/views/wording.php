<?php
/**
 * Admin wording customization page view.
 *
 * @package WP_MS365_Graph
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap msgraph_settings">
	<h1 class="msgraph_settings__heading">
		<img src="<?php echo esc_url( WP_MS365_Admin::get_icon_url() ); ?>" class="msgraph_page-icon" alt="" width="28" height="28" />
		<?php esc_html_e( 'MS Graph Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Wording', 'wp-ms365-graph' ); ?>
	</h1>

	<?php settings_errors( 'wp_ms365_settings' ); ?>

	<form method="post" action="options.php" class="msgraph_settings__form">
		<?php settings_fields( 'wp_ms365_settings_group' ); ?>
		<?php do_settings_sections( 'wp-ms365-wording' ); ?>

		<?php submit_button(); ?>
	</form>
</div>
