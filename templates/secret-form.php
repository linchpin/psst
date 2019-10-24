<?php
/**
 * Secret Form Template
 *
 * This template holds the secret form.
 * This should probably have a controller for an endpoint vs using admin-post.php
 */
?>
<div class="grid-container">
	<div class="grid-x align-center">
		<div class="cell small-12 medium-centered">
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="psst-secret-form">
				<div class="secret-message-container margin-bottom-small">
					<label for="secret" aria-hidden="true" class="screen-reader-text hide">
						<?php esc_html_e( 'Secret Message', 'psst' ); ?>
					</label>
					<?php wp_editor( '', 'secret', $secret_editor_options ); ?>
				</div>
				<div class="callout secondary padding-all-none" data-closable>
					<h3><span class="icon-lightbulb"></span><?php esc_html_e( 'Quick Tip!', 'psst' ); ?></h3>
					<p>If you would like to secure your post even further you can provide a pass phrase.</p>
					<button class="close-button" aria-label="Dismiss alert" type="button" data-close tabindex="10000">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="pass-phrase-container margin-bottom-small">
					<div class="grid-x">
						<div class="cell small-12 medium-3">
							<label for="passphrase">
								<?php esc_html_e( 'Pass Phrase:', 'psst' ); ?>
							</label>
						</div>
						<div class="cell small-12 medium-9">
							<input type="text" name="passphrase" id="passphrase" class="input-group-field" />
						</div>
					</div>
				</div>
				<div class="expiration-container">
					<div class="grid-x margin-bottom-small">
						<div class="cell small-12 medium-3">
							<label for="expiration">
								<?php esc_html_e( 'Expiration:', 'psst' ); ?>
							</label>
						</div>
						<div class="cell small-12 medium-9">
							<?php \Psst\Helper\Utils::psst_schedule_selector(); ?>
						</div>
					</div>
				</div>
				<div class="grid-x">
					<div class="cell small-12">
						<input type="hidden" name="action" value="psst_create_secret" />
						<?php wp_nonce_field( 'create_secret', 'create_secret_nonce' ); ?>
						<button type="submit" class="button primary expanded"><?php esc_html_e( 'Create Secret Link', 'psst' ); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>
