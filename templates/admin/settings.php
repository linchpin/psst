<?php
/**
 * Provides a dashboard view for the plugin
 *
 * This file is used to markup the plugin settings and faq pages
 *
 * @since      1.0
 * @package    Psst
 * @subpackage Admin
 */

// Make sure we don't expose any info if called directly.
use Psst\Core\View;

if ( ! function_exists( 'add_action' ) ) {
	exit;
}

/**
 * Options Page
 *
 * Renders the settings page contents.
 *
 * @since       1.0.0
 */
?>
<div class="psst-wrap" id="psst-settings">
	<div class="table">
		<h2><?php esc_html_e( 'Psst!', 'psst' ); ?></h2>
		<h3 class="com-button table-cell">
		<?php
			// translators: %1$s: Linchpin Website URL %2$s: Visit CTA. WPCS: xss ok.
			printf( wp_kses_post( __( '<a href="%1$s" class="button" target="_blank">%2$s</a>', 'psst' ) ), esc_url( 'https://linchpin.com' ), esc_html__( 'Visit linchpin.com', 'psst' ) ); // WPCS: xss ok.
		?>
		</h3>
		<div class="clearfix"></div>
	</div>
	<?php settings_errors( self::$plugin_name . '-notices' ); ?>
	<h2 class="nav-tab-wrapper negative-bg">
		<?php
		foreach ( $tabs as $tab_slug => $tab ) :

			$tab_url = add_query_arg( array(
				'settings-updated' => false,
				'tab'              => $tab_slug,
			) );

			$active = ( $active_tab === $tab_slug ) ? ' nav-tab-active' : '';

			?>
			<a href="<?php echo esc_url( $tab_url ); ?>" title="<?php echo esc_attr( $tab['label'] ); ?>" class="nav-tab <?php echo esc_attr( $active ); ?>">
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</h2>
	<?php $sub_tabs = self::get_sub_tabs( $active_tab ); ?>
	<?php if ( ! empty( $sub_tabs ) ) : ?>
	<div class="psst-sub-menu">
		<?php
			$active_sub_tab = self::get_request_param( 'subtab', '' );

			foreach ( $sub_tabs as $sub_tab_slug => $sub_tab ) :

				$sub_tab_url = add_query_arg( array(
					'settings-updated' => false,
					'tab'              => $active_tab,
					'subtab'           => $sub_tab_slug,
				) );

				$active_sub_tab_class = ( $active_sub_tab === $sub_tab_slug ) ? ' nav-sub-tab-active' : '';

				?>
				<a href="<?php echo esc_url( $sub_tab_url ); ?>" title="<?php echo esc_attr( $sub_tab['label'] ); ?>" class="sub-tab <?php echo esc_attr( $active_sub_tab_class ); ?>">
					<?php echo esc_html( $sub_tab['label'] ); ?>
				</a>
			<?php
			endforeach;
		?>
	</div>
	<?php endif; ?>
	<div id="poststuff">
		<div id="post-body" class="metabox-holder">
			<div id="postbox-container" class="postbox-container">
				<?php if ( empty( $active_sub_tab ) ) : ?>
					<?php if ( 'settings' === $active_tab ) : ?>
						<?php
						$general = new View();
						$general->render( 'admin/settings-general' );
						?>
					<?php elseif ( 'design' === $active_tab ) : ?>
						<?php
						$design = new View();
						$design->render( 'admin/settings-design' );
						?>
					<?php elseif ( 'about' === $active_tab ) : ?>
						<?php
						$about_courier = new View();
						$about_courier->render( 'admin/settings-about-psst' );
						?>
					<?php elseif ( 'addons' === $active_tab ) : ?>
						<?php
						$addons = new View();
						$addons->render( 'admin/settings-addons' );
						?>
					<?php elseif ( 'new' === $active_tab ) : ?>
						<?php require_once PSST_PATH . 'templates/admin/settings-whats-new.php'; ?>
					<?php else : ?>
						<?php do_action( 'psst_setting_' . sanitize_title( $active_tab ) ); ?>
					<?php endif; ?>
				<?php elseif ( $active_sub_tab ) : ?>
					<?php do_action( 'psst_setting_' . sanitize_title( $active_sub_tab ) ); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
