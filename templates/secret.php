<?php
/**
 * Secret Confirm View Template
 *
 * This template holds the secret confirmation button
 * This should probably have a controller for an endpoint vs using admin-post.php
 *
 * @since 1.0.4
 */
?>
<div class="grid-container">
	<div class="grid-x align-center">
		<div class="cell small-12 medium-centered">
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="psst-secret-view">
				<div class="grid-x">
					<div class="cell small-12">
						<p><?php esc_html_e( 'Once you click view, your secret will be revealed, onced viewed, it will be removed from the system', 'psst' ); ?></p>
						<input type="hidden" name="action" value="psst_view_secret" />
						<input type="hidden" name="confirm_secret" value="true" />
						<?php wp_nonce_field( 'view_secret', 'view_secret_nonce' ); ?>
						<button type="submit" class="button primary expanded"><?php esc_html_e( 'View Secret', 'psst' ); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>
