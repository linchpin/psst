<?php
/**
 * Front-end markup for psst/login-form.
 *
 * Two modes on one page: the sign-in form, and the lost-password form when the
 * page is reached with `?action=lostpassword`. Both post away from here — sign
 * in to core's wp-login.php, which is the only thing that should ever check a
 * password, and lost password back to this page, where the controller hands it
 * to core's `retrieve_password()`.
 *
 * Variables in scope: $attributes (array), $content (string), $block (WP_Block).
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Account\Login;
use Linchpin\Psst\Model\Settings;

if ( is_user_logged_in() ) {
	return;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended -- Every read below only decides which message to draw; nothing acts on these.
$psst_mode     = isset( $_GET['action'] ) && 'lostpassword' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ? 'lost' : 'login';
$psst_error    = isset( $_GET[ Login::ERROR_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ Login::ERROR_ARG ] ) ) : '';
$psst_value    = isset( $_GET[ Login::LOGIN_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ Login::LOGIN_ARG ] ) ) : '';
$psst_sent     = isset( $_GET[ Login::SENT_ARG ] );
$psst_new      = isset( $_GET['psst_registered'] );
$psst_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended

$psst_heading  = (string) ( $attributes['heading'] ?? __( 'Sign in', 'psst' ) );
$psst_register = ! empty( $attributes['showRegisterLink'] ) ? Settings::get_register_url() : '';
$psst_lost     = ! empty( $attributes['showLostPassword'] );
$psst_uid      = wp_unique_id( 'psst-login-' );

if ( '' === $psst_redirect ) {
	$psst_redirect = Settings::get_account_url();
}

if ( '' === $psst_redirect ) {
	$psst_redirect = Settings::get_create_url();
}

$psst_wrapper = get_block_wrapper_attributes( [ 'class' => 'wp-block-psst-login-form psst-account' ] );
?>
<div <?php echo $psst_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Linchpin.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped attribute markup. ?>>

	<?php if ( 'lost' === $psst_mode ) : ?>

		<h2 class="psst-account__heading"><?php esc_html_e( 'Reset your password', 'psst' ); ?></h2>

		<?php if ( $psst_sent ) : ?>
			<div class="psst-callout psst-callout--tip" role="status">
				<p><?php esc_html_e( 'If that address has an account, a reset link is on its way. The link expires, so use it soon.', 'psst' ); ?></p>
			</div>
			<p><a href="<?php echo esc_url( Settings::get_login_url() ); ?>"><?php esc_html_e( 'Back to sign in', 'psst' ); ?></a></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Enter the email address you signed up with and we will send you a link to set a new password.', 'psst' ); ?></p>

			<form class="psst-account__form" method="post" action="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', Settings::get_login_url() ) ); ?>">
				<?php wp_nonce_field( 'psst_lost_password', 'psst_lost_password_nonce' ); ?>

				<div class="psst-account__field">
					<label for="<?php echo esc_attr( $psst_uid ); ?>-lost"><?php esc_html_e( 'Email Address', 'psst' ); ?></label>
					<input id="<?php echo esc_attr( $psst_uid ); ?>-lost" type="email" name="user_login" autocomplete="username" required />
				</div>

				<div class="wp-block-button">
					<button type="submit" class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Email me a reset link', 'psst' ); ?></button>
				</div>
			</form>

			<p class="psst-account__alt"><a href="<?php echo esc_url( Settings::get_login_url() ); ?>"><?php esc_html_e( 'Back to sign in', 'psst' ); ?></a></p>
		<?php endif; ?>

	<?php else : ?>

		<?php if ( '' !== $psst_heading ) : ?>
			<h2 class="psst-account__heading"><?php echo wp_kses_post( $psst_heading ); ?></h2>
		<?php endif; ?>

		<?php if ( $psst_new ) : ?>
			<div class="psst-callout psst-callout--tip" role="status">
				<p><?php esc_html_e( 'Your account is ready. Sign in to get started.', 'psst' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $psst_error ) : ?>
			<p class="psst-account__error" role="alert"><?php echo esc_html( Login::error_message( $psst_error ) ); ?></p>
		<?php endif; ?>

		<?php
		/*
		 * wp_login_form() and not hand-rolled markup: the action URL, the field
		 * names and the `login_form` hook other plugins hang two-factor and SSO
		 * fields off are all core's business, and a copy of them here would
		 * quietly rot. Login::login_form_middle() adds the flag that sends a
		 * failure back to this page.
		 */
		wp_login_form(
			[
				'echo'           => true,
				'redirect'       => $psst_redirect,
				'form_id'        => esc_attr( $psst_uid ) . '-form',
				'label_username' => __( 'Email Address', 'psst' ),
				'label_password' => __( 'Password', 'psst' ),
				'label_remember' => __( 'Stay signed in', 'psst' ),
				'label_log_in'   => __( 'Sign In', 'psst' ),
				'remember'       => true,
				'value_username' => $psst_value,
				'value_remember' => false,
			]
		);
		?>

		<p class="psst-account__alt">
			<?php if ( $psst_lost ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', Settings::get_login_url() ) ); ?>"><?php esc_html_e( 'Forgotten your password?', 'psst' ); ?></a>
			<?php endif; ?>

			<?php if ( '' !== $psst_register ) : ?>
				<a href="<?php echo esc_url( $psst_register ); ?>"><?php esc_html_e( 'Create an account', 'psst' ); ?></a>
			<?php endif; ?>
		</p>

	<?php endif; ?>
</div>
