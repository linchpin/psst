<?php
/**
 * Front-end markup for psst/secret-form.
 *
 * Variables in scope: $attributes (array), $content (string, the FAQ inner
 * blocks), $block (WP_Block).
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Blocks;
use Linchpin\Psst\Model\Blocks\Secret_Form;
use Linchpin\Psst\Model\Settings;

Blocks::send_client_config();

$psst_attributes = Secret_Form::attributes( (array) $attributes );
$psst_expiry     = Secret_Form::expiry( $psst_attributes['defaultExpiry'] );
$psst_context    = Secret_Form::context( (array) $attributes );
$psst_uid        = wp_unique_id( 'psst-form-' );

if ( Settings::turnstile_enabled() ) {
	// Turnstile is a third-party challenge service and its widget can only be
	// served by Cloudflare. It loads only when a site owner has opted in by
	// entering both keys, and is disclosed in readme.txt.
	wp_enqueue_script(
		'cf-turnstile',
		'https://challenges.cloudflare.com/turnstile/v0/api.js', // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Third-party service script, opt-in and disclosed.
		[],
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Cloudflare's endpoint is unversioned.
		[
			'in_footer' => true,
			'strategy'  => 'defer',
		]
	);
}

$psst_wrapper = get_block_wrapper_attributes(
	[
		'class'               => 'wp-block-psst-secret-form',
		'data-wp-interactive' => '{ "namespace": "psst/secret-form" }',
		'data-wp-context'     => wp_json_encode( $psst_context ),
		'data-wp-watch'       => 'callbacks.manageFocus',
		'data-wp-init'        => 'callbacks.checkSupport',
	]
);
?>
<div <?php echo $psst_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Linchpin.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped attribute markup. ?>>

	<form class="psst-form" novalidate data-wp-on--submit="actions.submit" data-wp-bind--hidden="!state.isIdle">
		<div class="psst-form__field">
			<label for="<?php echo esc_attr( $psst_uid ); ?>-message"><?php esc_html_e( 'Secret Message', 'psst' ); ?></label>
			<textarea
				id="<?php echo esc_attr( $psst_uid ); ?>-message"
				name="message"
				rows="7"
				required
				autocomplete="off"
				spellcheck="false"
				aria-describedby="<?php echo esc_attr( $psst_uid ); ?>-help <?php echo esc_attr( $psst_uid ); ?>-error"
				data-wp-bind--value="context.message"
				data-wp-on--input="actions.updateMessage"
				data-wp-bind--aria-invalid="state.hasError"
			></textarea>
			<p id="<?php echo esc_attr( $psst_uid ); ?>-help" class="psst-form__help" data-wp-text="state.remainingLabel"></p>
		</div>

		<?php if ( $psst_attributes['showTip'] ) : ?>
			<div class="psst-callout psst-callout--tip" role="note" data-wp-bind--hidden="context.tipDismissed">
				<button type="button" class="psst-callout__dismiss" data-wp-on--click="actions.dismissTip" aria-label="<?php esc_attr_e( 'Dismiss', 'psst' ); ?>">&times;</button>
				<?php if ( '' !== $psst_attributes['tipHeading'] ) : ?>
					<strong class="psst-callout__heading"><?php echo wp_kses_post( $psst_attributes['tipHeading'] ); ?></strong>
				<?php endif; ?>
				<p><?php echo wp_kses_post( $psst_attributes['tipText'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $psst_attributes['showPassphrase'] ) : ?>
			<div class="psst-form__field">
				<label for="<?php echo esc_attr( $psst_uid ); ?>-passphrase"><?php esc_html_e( 'Pass Phrase', 'psst' ); ?> <span class="psst-form__optional"><?php esc_html_e( '(optional)', 'psst' ); ?></span></label>
				<input
					id="<?php echo esc_attr( $psst_uid ); ?>-passphrase"
					name="passphrase"
					type="text"
					autocomplete="off"
					spellcheck="false"
					data-wp-bind--value="context.passphrase"
					data-wp-on--input="actions.updatePassphrase"
				/>
			</div>
		<?php endif; ?>

		<div class="psst-form__field">
			<label for="<?php echo esc_attr( $psst_uid ); ?>-expiry"><?php esc_html_e( 'Expiration', 'psst' ); ?></label>
			<select id="<?php echo esc_attr( $psst_uid ); ?>-expiry" name="expiry" data-wp-on--change="actions.updateExpiry">
				<?php foreach ( $psst_expiry['options'] as $psst_minutes => $psst_label ) : ?>
					<option value="<?php echo esc_attr( (string) $psst_minutes ); ?>" <?php selected( $psst_minutes, $psst_expiry['selected'] ); ?>><?php echo esc_html( $psst_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php if ( Settings::turnstile_enabled() ) : ?>
			<div class="cf-turnstile psst-form__turnstile" data-sitekey="<?php echo esc_attr( (string) Settings::get( 'turnstile_site_key' ) ); ?>" data-size="flexible"></div>
		<?php endif; ?>

		<div class="psst-form__hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $psst_uid ); ?>-hp"><?php esc_html_e( 'Leave this field empty', 'psst' ); ?></label>
			<input id="<?php echo esc_attr( $psst_uid ); ?>-hp" type="text" name="hp" tabindex="-1" autocomplete="off" data-wp-bind--value="context.hp" data-wp-on--input="actions.updateHp" />
		</div>

		<p id="<?php echo esc_attr( $psst_uid ); ?>-error" class="psst-form__error" role="alert" data-wp-text="context.error" data-wp-bind--hidden="!state.hasError"></p>

		<div class="wp-block-button psst-form__actions">
			<button type="submit" class="wp-block-button__link wp-element-button" data-wp-bind--disabled="state.isBusy" data-wp-text="state.submitLabel"><?php esc_html_e( 'Create Secret Link', 'psst' ); ?></button>
		</div>
	</form>

	<div class="psst-form__confirmation" hidden data-wp-bind--hidden="!state.isConfirmed">
		<h2 class="psst-form__heading" tabindex="-1"><?php esc_html_e( 'Share this link', 'psst' ); ?></h2>

		<div class="psst-form__share">
			<label class="screen-reader-text" for="<?php echo esc_attr( $psst_uid ); ?>-url"><?php esc_html_e( 'Secret link', 'psst' ); ?></label>
			<input id="<?php echo esc_attr( $psst_uid ); ?>-url" class="psst-form__url" type="url" readonly data-wp-bind--value="context.shareUrl" data-wp-on--focus="actions.selectAll" />
			<div class="wp-block-button">
				<button type="button" class="wp-block-button__link wp-element-button" data-wp-on--click="actions.copy" data-wp-class--is-copied="context.copied" data-wp-text="state.copyLabel"><?php esc_html_e( 'Copy to clipboard', 'psst' ); ?></button>
			</div>
		</div>
		<p class="screen-reader-text" aria-live="polite" data-wp-text="state.copyAnnouncement"></p>

		<p class="psst-form__expires"><strong data-wp-text="context.expiresLabel"></strong></p>

		<div class="psst-callout psst-callout--tip" role="note">
			<strong class="psst-callout__heading"><?php esc_html_e( 'Quick Tip!', 'psst' ); ?></strong>
			<p><?php esc_html_e( 'You can still shred this secret before it is read. If you shred the link before the recipient views it, the link will no longer work.', 'psst' ); ?></p>
		</div>

		<div class="psst-form__more">
			<div>
				<h3><?php esc_html_e( 'Make a mistake?', 'psst' ); ?></h3>
				<div class="wp-block-button is-style-outline">
					<button type="button" class="wp-block-button__link wp-element-button psst-form__shred" data-wp-on--click="actions.shred" data-wp-bind--disabled="state.isBusy"><?php esc_html_e( 'Shred Secret Link', 'psst' ); ?></button>
				</div>
			</div>
			<div>
				<h3><?php esc_html_e( 'Have more to share?', 'psst' ); ?></h3>
				<div class="wp-block-button is-style-outline">
					<button type="button" class="wp-block-button__link wp-element-button" data-wp-on--click="actions.reset"><?php esc_html_e( 'Create a new secret', 'psst' ); ?></button>
				</div>
			</div>
		</div>

		<?php if ( '' !== trim( (string) $content ) ) : ?>
			<div class="psst-form__faq">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Linchpin.Security.EscapeOutput.OutputNotEscaped -- Inner block content, already rendered by WordPress. ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="psst-form__shredded" hidden data-wp-bind--hidden="!state.isShredded">
		<h2 class="psst-form__heading" tabindex="-1"><?php esc_html_e( 'Your secret has been shredded', 'psst' ); ?></h2>
		<p><?php esc_html_e( 'The link no longer works. Nothing was stored that could recover it.', 'psst' ); ?></p>
		<div class="wp-block-button">
			<button type="button" class="wp-block-button__link wp-element-button" data-wp-on--click="actions.reset"><?php esc_html_e( 'Create a new secret', 'psst' ); ?></button>
		</div>
	</div>

	<div class="psst-form__unsupported" hidden data-wp-bind--hidden="!state.isUnsupported">
		<p role="alert"><?php esc_html_e( 'Your browser does not support the encryption this page needs. Please use a current browser.', 'psst' ); ?></p>
	</div>
</div>
