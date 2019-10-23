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
		<div class="cell small-12 medium-8 medium-centered">
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<div class="secret-message-container">
					<label for="secret" aria-hidden="true" class="screen-reader-text hide">
						<?php esc_html_e( 'Secret Message', 'psst' ); ?>
					</label>
					<?php wp_editor( '', 'secret', $secret_editor_options ); ?>
				</div>
				<div class="callout secondary">
					<p><strong>Quick Tip:</strong> If you would like to secure your post even further you can provide a pass phrase.</p>
				</div>
				<div class="pass-phrase-container">
					<div class="input-group">
						<span class="input-group-label">
							<label for="passphrase">
								<?php esc_html_e( 'Pass Phrase:', 'psst' ); ?>
							</label>
						</span>
						<input type="text" name="passphrase" id="passphrase" class="input-group-field" />
					</div>
				</div>
				<div class="expiration-container">
					<div class="input-group">
						<span class="input-group-label">
							<label for="expiration">
								<?php esc_html_e( 'Expiration:', 'psst' ); ?>
							</label>
						</span>
						<?php \Psst\Helper\Utils::psst_schedule_selector(); ?>
					</div>
				</div>
				<div>
					<input type="hidden" name="action" value="psst_create_secret" />
					<?php wp_nonce_field( 'create_secret', 'create_secret_nonce' ); ?>
					<button type="submit" class="button primary"><?php esc_html_e( 'Create Secret Link', 'psst' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
