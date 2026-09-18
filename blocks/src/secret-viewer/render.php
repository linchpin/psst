<?php
/**
 * Front-end markup for psst/secret-viewer.
 *
 * The server decides the starting state from a non-consuming lookup. Nothing
 * here fetches or reveals anything; a GET never burns the secret.
 *
 * Variables in scope: $attributes (array), $content (string), $block (WP_Block).
 *
 * @package Linchpin\Psst
 */

use Linchpin\Psst\Controller\Blocks;
use Linchpin\Psst\Model\Blocks\Secret_Viewer;

Blocks::send_client_config();

$psst_attributes = Secret_Viewer::attributes( (array) $attributes );
$psst_resolved   = Secret_Viewer::resolve();
$psst_context    = Secret_Viewer::context( $psst_resolved );
$psst_uid        = wp_unique_id( 'psst-viewer-' );
$psst_gone       = in_array( $psst_resolved['status'], [ 'gone', 'missing' ], true );

$psst_wrapper = get_block_wrapper_attributes(
	[
		'class'               => 'wp-block-psst-secret-viewer',
		'data-wp-interactive' => '{ "namespace": "psst/secret-viewer" }',
		'data-wp-context'     => wp_json_encode( $psst_context ),
		'data-wp-watch'       => 'callbacks.manageFocus',
		'data-wp-init'        => 'callbacks.checkKey',
	]
);
?>
<div <?php echo $psst_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Linchpin.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped attribute markup. ?>>

	<?php if ( '' !== $psst_attributes['heading'] ) : ?>
		<h1 class="psst-viewer__heading" tabindex="-1"><?php echo wp_kses_post( $psst_attributes['heading'] ); ?></h1>
	<?php endif; ?>

	<?php if ( $psst_gone ) : ?>
		<?php // Nothing about the id's existence is inferable from this markup: the interstitial is not rendered at all. ?>
		<div class="psst-viewer__gone" role="status">
			<p><?php echo 'missing' === $psst_resolved['status'] ? esc_html__( 'There is no secret at this address. Ask the sender for the full link.', 'psst' ) : esc_html__( 'This secret is no longer available.', 'psst' ); ?></p>
			<p class="psst-viewer__gone-help"><?php esc_html_e( 'Secrets can be read once and expire on their own. If you were expecting one, ask the sender to share it again.', 'psst' ); ?></p>
			<div class="wp-block-button">
				<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $psst_context['createUrl'] ); ?>"><?php esc_html_e( 'Create your own secret', 'psst' ); ?></a>
			</div>
		</div>
	<?php else : ?>

		<div class="psst-viewer__interstitial" data-wp-bind--hidden="!state.isInterstitial">
			<?php if ( '' !== $psst_attributes['intro'] ) : ?>
				<p class="psst-viewer__intro"><?php echo wp_kses_post( $psst_attributes['intro'] ); ?></p>
			<?php endif; ?>

			<div class="psst-viewer__passphrase psst-form__field" <?php echo 'protected' === $psst_resolved['status'] ? '' : 'hidden'; ?> data-wp-bind--hidden="!state.needsPassphrase">
				<label for="<?php echo esc_attr( $psst_uid ); ?>-passphrase"><?php esc_html_e( 'Pass Phrase', 'psst' ); ?></label>
				<input
					id="<?php echo esc_attr( $psst_uid ); ?>-passphrase"
					type="password"
					autocomplete="off"
					data-wp-bind--value="context.passphrase"
					data-wp-on--input="actions.updatePassphrase"
					data-wp-on--keydown="actions.submitOnEnter"
					data-wp-bind--aria-invalid="state.hasError"
				/>
				<p class="psst-viewer__help"><?php esc_html_e( 'The sender protected this secret with a pass phrase.', 'psst' ); ?></p>
			</div>

			<p class="psst-viewer__error" role="alert" data-wp-text="context.error" data-wp-bind--hidden="!state.hasError"></p>

			<div class="wp-block-button psst-viewer__actions">
				<button type="button" class="wp-block-button__link wp-element-button" data-wp-on--click="actions.reveal" data-wp-bind--disabled="state.isBusy" data-wp-text="state.viewLabel"><?php esc_html_e( 'View Secret', 'psst' ); ?></button>
			</div>
		</div>

		<div class="psst-viewer__revealed" hidden data-wp-bind--hidden="!state.isRevealed">
			<div class="psst-callout psst-callout--warning" role="status" data-wp-bind--hidden="context.warningDismissed">
				<button type="button" class="psst-callout__dismiss" data-wp-on--click="actions.dismissWarning" aria-label="<?php esc_attr_e( 'Dismiss', 'psst' ); ?>">&times;</button>
				<strong class="psst-callout__heading"><?php esc_html_e( 'Warning', 'psst' ); ?></strong>
				<p><?php esc_html_e( 'If you refresh this page or close this window, you will no longer be able to retrieve this secret.', 'psst' ); ?></p>
			</div>

			<pre class="psst-secret" tabindex="-1" data-wp-text="context.plaintext"></pre>

			<div class="wp-block-button psst-viewer__actions">
				<button type="button" class="wp-block-button__link wp-element-button" data-wp-on--click="actions.copy" data-wp-class--is-copied="context.copied" data-wp-text="state.copyLabel"><?php esc_html_e( 'Copy secret', 'psst' ); ?></button>
			</div>
			<p class="screen-reader-text" aria-live="polite" data-wp-text="state.copyAnnouncement"></p>
		</div>

		<div class="psst-viewer__gone" hidden role="status" data-wp-bind--hidden="!state.isGone">
			<p data-wp-text="state.goneMessage"><?php esc_html_e( 'This secret is no longer available.', 'psst' ); ?></p>
			<div class="wp-block-button">
				<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $psst_context['createUrl'] ); ?>"><?php esc_html_e( 'Create your own secret', 'psst' ); ?></a>
			</div>
		</div>

	<?php endif; ?>
</div>
