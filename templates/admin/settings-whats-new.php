<?php
/**
 * Updates, Bug Fixes and News Template
 *
 * @since      1.0.2
 * @package    Psst
 * @subpackage Admin
 */

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
	exit;
}

?>
<div id="whats-new">
	<div id="post-body" class="metabox-holder">
		<div id="postbox-container" class="postbox-container">
			<div class="whatsnew hero negative-bg">
				<div class="hero-text">
					<h1><?php echo esc_html__( 'Everything is new', 'psst' ); ?></h1>
				</div>
			</div>
			<div class="gray-bg negative-bg versioninfo">
				<div class="wrapper">
					<h2 class="light-weight">
						<?php
						printf(
							'Psst Version %s <span class="green-pipe">|</span> Released %s',
							esc_html( get_option( 'psst_version' ) ),
							esc_html__( 'Oct 28th, 2019', 'psst' )
						);
						?>
					</h2>
				</div>
			</div>
			<div class="wrapper">
				<p><?php esc_html_e( "It's all new, we don't have much to add here. Check out the about page.", 'psst' ); ?></p>
			</div>
		</div>
	</div>
</div>
