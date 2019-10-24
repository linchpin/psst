<?php
/**
 * Secret Confirmation Template
 *
 * This template holds the confirmation message displayed when you create a secret. This template is loaded as a partial
 * into the individual secret post type "secret-single.php" template.
 *
 * The confirmation includes the following item
 *
 * 1. The generate "permalink" for the url.
 * 2. The Date / Time that the secret expires on
 */
?>
<div class="grid-container">
	<div class="grid-x align-center">
		<div class="cell small-12 medium-8 medium-centered">
			<div class="psst-secret-confirmation">
				<h1><?php esc_html_e( 'Share this link', 'psst' ); ?></h1>
				<div class="input-group">
					<input class="input-group-field" type="url" id="psst-secret-url" value="<?php echo esc_url( get_the_permalink() ); ?>">
					<div class="input-group-button">
						<button class="psst button primary" id="copy-secret" data-clipboard-target="#psst-secret-url" data-tooltip data-disable-hover="true" tabindex="1" data-tip-text="Copied">
							<?php esc_attr_e( 'Copy to clipboard', 'psst' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="grid-x align-center">
		<div class="cell small-12 medium-8">
			<h3><?php esc_html_e( 'Make a mistake?', 'psst' ); ?></h3>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="psst_delete_secret" />
				<?php wp_nonce_field( 'create_secret', 'create_secret_nonce' ); ?>
				<input type="hidden" name="secret_confirm_key" value="<?php echo esc_attr( $secret_confirm_key ); ?>" />
				<button type="submit" class="alert button small expanded"><?php esc_html_e( 'Shred Secret Link', 'psst' ); ?></button>
				<div class="callout secondary padding-all-none" data-closeable>
					<h3><?php esc_html_e( 'Quick Tip!', 'psst' ); ?></h3>
					<p>
					<?php esc_html_e( 'You still have the ability to shred a secret before it is read. If you shred a secret link before the recipient views it, the link will no longer work', 'psst' ); ?>
					</p>
					<button class="close-button" aria-label="Dismiss alert" type="button" data-close>
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
			</form>
		</div>
	</div>
	<div class="grid-x align-center">
		<div class="cell small-12 medium-8">
			<h3><?php esc_html_e( 'Have more to share?', 'psst' ); ?></h3>
			<a class="button secondary small expanded" href="<?php esc_url( site_url( '/secret/' ) ); ?>"><?php esc_html_e( 'Create a new secret', 'psst' ); ?></a>
		</div>
	</div>
	<div class="grid-x align-center">
		<div class="cell small-12 medium-8">
			<h2><?php esc_html_e( 'Need some help?', 'psst' ); ?></h2>
			<h4><?php esc_html_e( 'Why would I shred a secret', 'psst' ); ?></h4>
			<p>Burning a secret will delete it before it has been read. If you send someone a secret link and burn the secret before they view it, they will not be able to read it. In fact, it will look to them like the secret never existed at all.</p>
			<h4><?php esc_html_e( 'Why can I only see the secret value once?', 'psst' ); ?></h4>
			<p>We display the value for you so that you can verify it but we do that once so that if someone gets this private page (in your browser history or if you accidentally send the private link instead of the secret one), they won't see the secret value.</p>
		</div>
	</div>
</div>
