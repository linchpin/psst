<?php
/**
 * Front-end markup for psst/register-form.
 *
 * Posts back to the page it is on. Controller\Account\Registration picks it up
 * on template_redirect, which keeps account creation off admin-post.php — and
 * therefore out of wp-admin, on the sites most likely to have just closed
 * wp-admin to the very people registering here.
 *
 * Variables in scope: $attributes (array), $content (string), $block (WP_Block).
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Account\Registration;
use Linchpin\Psst\Model\Settings;

if ( is_user_logged_in() ) {
	return;
}

$psst_uid   = wp_unique_id( 'psst-register-' );
$psst_login = ! empty( $attributes['showLoginLink'] ) ? Settings::get_login_url() : '';

$psst_wrapper = get_block_wrapper_attributes( [ 'class' => 'wp-block-psst-register-form psst-account' ] );

if ( ! Settings::registration_open() ) :
	?>
	<div <?php echo $psst_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Linchpin.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute markup. ?>>
		<p class="psst-account__closed"><?php esc_html_e( 'This site is not accepting new accounts right now.', 'psst' ); ?></p>
		<?php if ( '' !== $psst_login ) : ?>
			<p class="psst-account__alt"><a href="<?php echo esc_url( $psst_login ); ?>"><?php esc_html_e( 'Sign in', 'psst' ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
	return;
endif;

// phpcs:disable WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended -- Both reads only redraw the form after a rejected POST.
$psst_error = isset( $_GET[ Registration::ERROR_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ Registration::ERROR_ARG ] ) ) : '';
$psst_email = isset( $_GET['psst_email'] ) ? sanitize_email( wp_unslash( $_GET['psst_email'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended

$psst_heading = (string) ( $attributes['heading'] ?? '' );
$psst_intro   = (string) ( $attributes['intro'] ?? '' );
?>
<div <?php echo $psst_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Linchpin.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute markup. ?>>

	<?php if ( '' !== $psst_heading ) : ?>
		<h2 class="psst-account__heading"><?php echo wp_kses_post( $psst_heading ); ?></h2>
	<?php endif; ?>

	<?php if ( '' !== $psst_intro ) : ?>
		<p class="psst-account__intro"><?php echo wp_kses_post( $psst_intro ); ?></p>
	<?php endif; ?>

	<?php if ( '' !== $psst_error ) : ?>
		<p class="psst-account__error" role="alert"><?php echo esc_html( Registration::error_message( $psst_error ) ); ?></p>
	<?php endif; ?>

	<form class="psst-account__form" method="post" action="<?php echo esc_url( Settings::get_register_url() ); ?>">
		<?php wp_nonce_field( Registration::NONCE_ACTION, Registration::NONCE_FIELD ); ?>

		<div class="psst-account__field">
			<label for="<?php echo esc_attr( $psst_uid ); ?>-email"><?php esc_html_e( 'Email Address', 'psst' ); ?></label>
			<input
				id="<?php echo esc_attr( $psst_uid ); ?>-email"
				type="email"
				name="user_email"
				value="<?php echo esc_attr( $psst_email ); ?>"
				autocomplete="username"
				required
			/>
		</div>

		<div class="psst-account__field">
			<label for="<?php echo esc_attr( $psst_uid ); ?>-pass"><?php esc_html_e( 'Password', 'psst' ); ?></label>
			<input
				id="<?php echo esc_attr( $psst_uid ); ?>-pass"
				type="password"
				name="user_pass"
				autocomplete="new-password"
				minlength="<?php echo esc_attr( (string) Registration::MIN_PASSWORD_LENGTH ); ?>"
				aria-describedby="<?php echo esc_attr( $psst_uid ); ?>-hint"
				required
			/>
			<p id="<?php echo esc_attr( $psst_uid ); ?>-hint" class="psst-account__help">
				<?php
				printf(
					/* translators: %d: the minimum number of characters. */
					esc_html__( 'At least %d characters. A short sentence you will remember beats a short word you will not.', 'psst' ),
					(int) Registration::MIN_PASSWORD_LENGTH
				);
				?>
			</p>
		</div>

		<div class="psst-account__field">
			<label for="<?php echo esc_attr( $psst_uid ); ?>-confirm"><?php esc_html_e( 'Confirm Password', 'psst' ); ?></label>
			<input
				id="<?php echo esc_attr( $psst_uid ); ?>-confirm"
				type="password"
				name="user_pass_confirm"
				autocomplete="new-password"
				required
			/>
		</div>

		<div class="psst-account__hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $psst_uid ); ?>-hp"><?php esc_html_e( 'Leave this field empty', 'psst' ); ?></label>
			<input id="<?php echo esc_attr( $psst_uid ); ?>-hp" type="text" name="psst_hp" tabindex="-1" autocomplete="off" />
		</div>

		<?php
		/**
		 * Fires inside the registration form, before the submit button.
		 *
		 * Where a challenge widget or an extra field belongs. Anything added
		 * here should have a matching check on `psst_register_challenge`.
		 */
		do_action( 'psst_register_form' );
		?>

		<div class="wp-block-button">
			<button type="submit" class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Create Account', 'psst' ); ?></button>
		</div>
	</form>

	<?php if ( '' !== $psst_login ) : ?>
		<p class="psst-account__alt">
			<?php esc_html_e( 'Already have an account?', 'psst' ); ?>
			<a href="<?php echo esc_url( $psst_login ); ?>"><?php esc_html_e( 'Sign in', 'psst' ); ?></a>
		</p>
	<?php endif; ?>
</div>
