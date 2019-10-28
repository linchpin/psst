<?php
/**
 * Welcome / About
 *
 * @since      1.0
 * @package    Psst
 * @subpackage Admin
 */

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	exit;
}

use Psst\Helper\Utils as Utils;

?>
<div id="about-psst">
	<div id="post-body" class="metabox-holder">
		<div id="postbox-container" class="postbox-container">
			<div class="about hero negative-bg">
				<div class="hero-text">
					<h1><?php esc_html_e( 'Thank you for installing Psst', 'psst' ); ?></h1>
				</div>
			</div>
			<div class="gray-bg negative-bg">
				<div class="wrapper">
					<h2 class="color-darkgreen light-weight">
						<?php
						printf(
							wp_kses(
								// translators: %1$s Getting Started, %2$s Using Psst.
								__( '<span class="bold">%1$s</span> %2$s', 'psst' ),
								Utils::get_safe_markup()
							),
							esc_html( 'Getting Started:' ),
							esc_html( 'Using Psst allows you to provide your vistors/users with secret messages' )
						);
						?>
					</h2>
				</div>
			</div>

			<div class="wrapper help-row table">
				<div class="psst-columns-6 table-cell">
					<h3 class="color-darkgreen"><?php esc_html_e( 'A Quick 2 Minute Primer', 'psst' ); ?></h3>
					<p><?php esc_html_e( 'Psst allows you to notify your site visitors/users of different information and events on your site.', 'psst' ); ?></p>
					<p><?php esc_html_e( 'We built this plugin as a base notification system for a few other projects and decided to share it with the community.', 'psst' ); ?></p>
					<?php if ( ! Utils::is_wp_cron_disabled() ) : ?>
					<p>
						<strong><?php esc_html_e( 'Recommendation: If you are using the default WP Cron, we suggest utilizing an alternate cron so the timing of notice expiration is more accurate.', 'psst' ); ?></strong>
					</p>
					<p>
						<?php
						printf(
							// translators: %s WP Cron Documentation.
							wp_kses( __( 'You can read more about <a href="%s" target="_blank" rel="noopener">WP Cron Here</a>.', 'psst' ), $safe_content ),
							esc_url( 'https://developer.wordpress.org/plugins/cron/' )
						);
						?>
					</p>
					<?php endif; ?>
				</div>

				<div class="psst-columns-6 right table-cell">
					<img src="<?php echo esc_url( COURIER_PLUGIN_URL . 'assets/images/help-admin-comp2.gif' ); ?>" alt="<?php esc_attr_e( 'Enable Psst', 'psst' ); ?>" width="90%"/>
				</div>
			</div>

			<div class="gray-bg negative-bg">
				<div class="wrapper">
					<h2 class="color-darkgreen light-weight"><?php esc_html_e( 'Getting Started / Directions', 'psst' ); ?></h2>
					<div class="grey-box-container help-row" data-equalizer="">
						<div class="psst-columns-12">
							<div class="grey-box" data-equalizer-watch="">
								<div class="about-box-icon">
									<img src="<?php echo esc_url( COURIER_PLUGIN_URL . 'assets/images/feature-easy-familiar-2.svg' ); ?>" alt=""/>
								</div>
								<div class="about-box-copy">
									<h4 class="no-margin"><?php esc_html_e( 'Settings', 'psst' ); ?></h4>
									<p><?php esc_html_e( 'Create secrets using an interface similar to default pages and posts in WordPress.', 'psst' ); ?></p>
								</div>
							</div>
						</div>
					</div>
					<div class="grey-box-container help-row" data-equalizer="">
						<div class="psst-columns-12">
							<div class="grey-box" data-equalizer-watch="">
								<div class="about-box-icon">
									<img src="<?php echo esc_url( COURIER_PLUGIN_URL . 'assets/images/feature-easy-familiar-2.svg' ); ?>" alt=""/>
								</div>
								<div class="about-box-copy">
									<h4 class="no-margin"><?php esc_html_e( 'Adding / Editing Notices', 'psst' ); ?></h4>
									<p><?php esc_html_e( 'Creating and Editing notices is similar to pages and posts. Where each notice has a title and content.', 'psst' ); ?></p>
									<h5 class="no-margin"><?php esc_html_e( 'More Settings', 'psst' ); ?></h5>
									<dl>
										<dt><?php esc_html_e( 'Notice Types', 'psst' ); ?></dt>
										<dd><?php esc_html_e( 'Notice Types are used to display, success, error, warning and informational notices to your users' ); ?></dd>
										<dd><?php esc_html_e( 'You can also create more notice types as needed.' ); ?></dd>
										<dt><?php esc_html_e( 'Notice Placement', 'psst' ); ?></dt>
										<dd>
											<ul>
												<li><?php esc_html_e( 'Header', 'psst' ); ?></li>
												<li><?php esc_html_e( 'Footer', 'psst' ); ?></li>
												<li><?php esc_html_e( 'Modal / Popup', 'psst' ); ?></li>
											</ul>
										</dd>
										<dt><?php esc_html_e( 'Notice Expriration', 'psst' ); ?></dt>
									</dl>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="gray-bg negative-bg">
				<div class="wrapper">
					<h2 class="color-darkgreen light-weight"><?php esc_html_e( 'More Quick Tips', 'psst' ); ?></h2>
					<div class="grey-box-container help-row" data-equalizer="">
						<div class="psst-columns-6">
							<div class="grey-box" data-equalizer-watch="">
								<div class="about-box-icon">
									<img src="<?php echo esc_url( COURIER_PLUGIN_URL . 'assets/images/feature-easy-familiar-2.svg' ); ?>" alt=""/>
								</div>
								<div class="about-box-copy">
									<h4 class="no-margin"><?php esc_html_e( 'Familiar &amp; Easy to Use', 'psst' ); ?></h4>
									<p><?php esc_html_e( 'Create site notices using an interface similar to default pages and posts in WordPress.', 'psst' ); ?></p>
								</div>
							</div>
						</div>

						<div class="psst-columns-6">
							<div class="grey-box" data-equalizer-watch="">
								<div class="about-box-icon">
									<img src="<?php echo esc_url( COURIER_PLUGIN_URL . 'assets/images/feature-visualize.svg' ); ?>" alt=""/>
								</div>
								<div class="about-box-copy">
									<h4 class="no-margin"><?php esc_html_e( 'Notices For Users/Visitors', 'psst' ); ?></h4>
									<ul class="psst-notice-types-list">
										<li><?php esc_html_e( 'Global Notices (For all users)', 'psst' ); ?></li>
										<li><?php esc_html_e( 'User Specific Notices', 'psst' ); ?></li>
										<li><?php esc_html_e( 'User Group Notices (Coming Soon)', 'psst' ); ?></li>
									</ul>
									<p><?php esc_html_e( 'More features coming soon.', 'psst' ); ?></p>
								</div>
							</div>
						</div>

						<div class="psst-columns-6">
							<div class="grey-box " data-equalizer-watch="">
								<div class="about-box-icon">
									<img src="<?php echo esc_url( COURIER_PLUGIN_URL . 'assets/images/feature-responsive.svg' ); ?>" alt=""/>
								</div>
								<div class="about-box-copy">
									<h4 class="no-margin"><?php esc_html_e( 'Create Your Own Notice Types ', 'psst' ); ?></h4>
									<p><?php esc_html_e( 'You can use the default Psst notice types or feel free to add your own.', 'psst' ); ?></p>
									<p><?php esc_html_e( 'Pick your own notice colors and icons, disable the styles completely to theme Psst yourself.', 'psst' ); ?></p>
								</div>
							</div>
						</div>

						<div class="psst-columns-6">
							<div class="grey-box" data-equalizer-watch="">
								<div class="about-box-icon">
									<img src="<?php echo esc_url( COURIER_PLUGIN_URL . 'assets/images/feature-plays-well.svg' ); ?>" alt=""/>
								</div>
								<div class="about-box-copy">
									<h4 class="no-margin"><?php esc_html_e( 'Plays Well with Others', 'psst' ); ?></h4>
									<p>
										<?php
										printf(
											// translators: %s Psst github URL.
											wp_kses( __( 'Continually updated with hooks and filters to extend functionality. For a full list, check out <a href="%s" target="_blank" rel="noopener">Psst on github.com</a>.', 'psst' ), Utils::get_safe_markup() ),
											esc_url( 'https://github.com/linchpin/psst' )
										);
										?>
									</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="wrapper help-row">
				<h2 class="color-darkgreen light-weight"><?php esc_html_e( 'About the Team Behind Psst!', 'psst' ); ?></h2>
				<div class="about-devs-container help-columns-12">
					<div class="no-margin">
						<p class="no-margin">
							<?php
							printf(
								wp_kses(
									// translators: %1$s Linchpin URL, %2$s Linchpin Profile URL, %3$s WordPress RI meetup URL.
									__(
										'<a href="%1$s">Linchpin</a> is a Digital Agency that specializes in WordPress.
										Committed to contributing to the WordPress community, Linchpin has released several
										<a href="%2$s">plugins</a> on WordPress.org. Linchpin is also an active member in
										their local WordPress communities, not only leading the <a href="%3$s">WordPress
										Rhode Island Meetup</a> group for several years, but also organizing, volunteering,
										speaking at or sponsoring local WordCamp conferences in the greater New England area.',
										'psst'
									),
									Utils::get_safe_markup()
								),
								esc_url( 'https://linchpin.com' ),
								esc_url( 'https://profiles.wordpress.org/linchpin_agency/' ),
								esc_url( 'https://www.meetup.com/WordPressRI/' )
							);
							?>
						</p>
						<p>
							<?php
							printf(
								wp_kses(
									// translators: %s Linchpin URL.
									__(
										' <a href="%s">Check out our website</a>, connect with us or come say hi at a local event.',
										'psst'
									),
									Utils::get_safe_markup()
								),
								esc_url( 'https://linchpin.com' )
							);
							?>
						</p>
						<p class="no-margin">
							<?php

							printf(
								wp_kses(
									// translators: %1$s Linchpin URL, %2$s Linchpin. %3$s Jetpack.pro url, %4$s Jetpack.pro label.
									__(
										'<a href="%1$s">%2$s</a> |',
										'psst'
									),
									Utils::get_safe_markup()
								),
								esc_url( 'https://linchpin.com' ),
								esc_html__( 'Linchpin', 'psst' )
							);
							?>
							<?php
							printf(
								wp_kses(
									// translators: %1$s Linchpin Facebook URL, %2$s Facebook Label. %3$s Linchpin Twitter url, %4$s Twitter label.
									__(
										'<a href="%1$s">%2$s</a> | <a href="%3$s">%4$s</a> | ',
										'psst'
									),
									Utils::get_safe_markup()
								),
								esc_url( 'https://facebook.com/linchpinagency' ),
								esc_html__( 'Facebook', 'psst' ),
								esc_url( 'https://twitter.com/linchpin_agency' ),
								esc_html__( 'Twitter', 'psst' )
							);
							?>
							<?php
								printf(
									wp_kses(
										// translators: %1$s Linchpin Instagram URL, %2$s Instagram Label.
										__(
											'<a href="%1$s">%2$s</a>',
											'psst'
										),
										Utils::get_safe_markup()
									),
									esc_url( 'https://www.instagram.com/linchpinagency/' ),
									esc_html__( 'Instagram', 'psst' )
								);
								?>
							</p>
						<img src="<?php echo esc_url( PSST_PLUGIN_URL . 'assets/images/linchpin-logo-lockup-fill-gray.svg' ); ?>" width="200px"/>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

