<?php
/**
 * Secret Refresh Template
 *
 * This template is displayed when the user views a secret. It warns them that if they refresh all will be lost.
 */
?>
<div class="callout primary padding-all-none margin-top-small" data-closable>
	<h3><?php esc_html_e( 'Warning', 'psst' ); ?></h3>
	<p><?php esc_html_e( 'If you refresh this page or close this window, you will no longer be able to retrieve this secret.', 'psst' ); ?></p>
	<button class="close-button" aria-label="Dismiss alert" type="button" data-close>
		<span aria-hidden="true">&times;</span>
	</button>
</div>
