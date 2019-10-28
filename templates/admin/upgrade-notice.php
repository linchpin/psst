<?php
/**
 * Upgrade Notification Template
 * Display useful upgrade information within an admin notification
 *
 * @since      1.0.0
 * @package    Psst
 * @subpackage Admin
 */

$courier_version = get_option( 'psst_version', '0.0' );

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	exit;
}

?>
<div class="psst psst-update-notice notice notice-info is-dismissible" data-type="update-notice">
	<div class="table">
		<div class="table-cell">
			<img src="<?php echo esc_attr( PSST_PLUGIN_URL . 'assets/images/psst-full-logo-full-color@2x.png' ); ?>" alt="<?php echo esc_attr( 'Psst!', 'psst' ); ?>">
		</div>
		<div class="table-cell">
			<p class="no-margin">
				<?php
				printf(
					wp_kses_post(
						// translators: %1$s: Version Number %2$s: Link to what's new tab.
						__( 'Thanks for updating Psst to v. (%1$s). <strong>Major release</strong>. <a href="%2$s">what\'s new</a>', '[[ddy]]' )
					),
					esc_html( $courier_version ),
					esc_url( admin_url( 'options-general.php?page=psst&tab=new' ) )
				);
				?>
			</p>
			<p class="no-margin">
				<?php echo wp_kses_post( __( 'Initial Release', 'psst' ) ); ?>
			</p>
		</div>
	</div>
</div>
