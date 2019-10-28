<?php
/**
 * Provide a meta box view for the settings page
 * Renders a single meta box.
 *
 * @since      1.0.0
 * @package    Psst
 * @subpackage Admin
 */

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	exit;
}
?>
<form action="options.php" class="settings-form" method="post">
	<div class="about hero negative-bg">
		<div class="hero-text">
			<h1><?php esc_html_e( 'Psst! Settings', 'psst' ); ?></h1>
		</div>
	</div>
	<?php settings_fields( 'psst_settings' ); ?>
	<?php do_settings_sections( 'psst_settings' ); ?>
	<?php submit_button(); ?>
</form>
<br class="clear" />
