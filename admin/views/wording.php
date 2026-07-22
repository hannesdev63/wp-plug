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
		<?php esc_html_e( 'ESC Connect', 'wp-ms365-graph' ); ?> &mdash; <?php esc_html_e( 'Wording', 'wp-ms365-graph' ); ?>
	</h1>

	<?php settings_errors( 'wp_ms365_settings' ); ?>

	<form method="post" action="options.php" class="msgraph_settings__form">
		<?php settings_fields( 'wp_ms365_settings_group' ); ?>
		<?php
		global $wp_settings_sections;
		$wording_sections = isset( $wp_settings_sections['wp-ms365-wording'] ) ? $wp_settings_sections['wp-ms365-wording'] : array();
		?>

		<div class="msgraph_settings__cards">
			<?php foreach ( $wording_sections as $section_id => $section ) : ?>
				<div class="msgraph_card msgraph_card--settings">
					<h2 class="msgraph_card__title"><?php echo esc_html( $section['title'] ); ?></h2>
					<?php
					if ( ! empty( $section['callback'] ) ) {
						call_user_func( $section['callback'] );
					}
					?>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'wp-ms365-wording', $section_id ); ?>
					</table>
				</div>
			<?php endforeach; ?>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
