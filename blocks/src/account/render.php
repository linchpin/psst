<?php
/**
 * Front-end markup for psst/account.
 *
 * What a signed-in sender is allowed to know about their own secrets, which is
 * everything except the one thing that matters: metadata, never content. There
 * is no code path from this page to a plaintext, because there is no plaintext
 * on the server to path to.
 *
 * Variables in scope: $attributes (array), $content (string), $block (WP_Block).
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\REST\Secrets;
use Linchpin\Psst\Model\Sent_Secret_Repository;
use Linchpin\Psst\Model\Settings;

$psst_wrapper = get_block_wrapper_attributes( [ 'class' => 'wp-block-psst-account psst-account' ] );

if ( ! is_user_logged_in() ) :
	?>
	<div <?php echo $psst_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Linchpin.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute markup. ?>>
		<p><?php esc_html_e( 'Sign in to see the secrets you have sent.', 'psst' ); ?></p>
		<?php if ( '' !== Settings::get_login_url() ) : ?>
			<div class="wp-block-button">
				<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Settings::get_login_url() ); ?>"><?php esc_html_e( 'Sign In', 'psst' ); ?></a>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return;
endif;

$psst_user     = wp_get_current_user();
$psst_heading  = (string) ( $attributes['heading'] ?? '' );
$psst_per_page = max( 5, min( 100, (int) ( $attributes['perPage'] ?? 20 ) ) );
$psst_sign_out = ! empty( $attributes['showSignOut'] );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended -- Pagination only.
$psst_page = isset( $_GET['psst_page'] ) ? max( 1, absint( wp_unslash( $_GET['psst_page'] ) ) ) : 1;

$psst_history = Settings::history_enabled()
	? ( new Sent_Secret_Repository() )->for_user( (int) $psst_user->ID, $psst_page, $psst_per_page )
	: [
		'items' => [],
		'total' => 0,
		'pages' => 0,
	];
?>
<div <?php echo $psst_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, Linchpin.Security.EscapeOutput.OutputNotEscaped -- Escaped attribute markup. ?>>

	<header class="psst-account__header">
		<?php if ( '' !== $psst_heading ) : ?>
			<h2 class="psst-account__heading"><?php echo wp_kses_post( $psst_heading ); ?></h2>
		<?php endif; ?>

		<p class="psst-account__who">
			<?php
			printf(
				/* translators: %s: the signed-in user's name or email address. */
				esc_html__( 'Signed in as %s', 'psst' ),
				esc_html( '' !== trim( (string) $psst_user->display_name ) ? $psst_user->display_name : $psst_user->user_email )
			);
			?>
			<?php if ( $psst_sign_out ) : ?>
				<a class="psst-account__signout" href="<?php echo esc_url( wp_logout_url() ); ?>"><?php esc_html_e( 'Sign out', 'psst' ); ?></a>
			<?php endif; ?>
		</p>
	</header>

	<div class="wp-block-button">
		<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( Settings::get_create_url() ); ?>"><?php esc_html_e( 'Share a secret', 'psst' ); ?></a>
	</div>

	<?php if ( ! Settings::history_enabled() ) : ?>

		<p class="psst-account__empty"><?php esc_html_e( 'This site does not keep a record of sent secrets.', 'psst' ); ?></p>

	<?php elseif ( empty( $psst_history['items'] ) ) : ?>

		<p class="psst-account__empty"><?php esc_html_e( 'You have not sent any secrets yet. When you do, they will be listed here.', 'psst' ); ?></p>

	<?php else : ?>

		<h3 class="psst-account__subheading"><?php esc_html_e( 'Secrets you have sent', 'psst' ); ?></h3>

		<p class="psst-account__note">
			<?php esc_html_e( 'This is a record that a secret existed, not the secret. Nothing here can be decrypted, by you or by anyone with the database, because the key never reached this server.', 'psst' ); ?>
		</p>

		<table class="psst-history">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Secret', 'psst' ); ?></th>
					<th scope="col"><?php esc_html_e( 'For', 'psst' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sent', 'psst' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'psst' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'psst' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $psst_history['items'] as $psst_row ) : ?>
					<tr class="psst-history__row is-<?php echo esc_attr( $psst_row['state'] ); ?>">
						<td data-label="<?php esc_attr_e( 'Secret', 'psst' ); ?>">
							<code><?php echo esc_html( $psst_row['short_id'] ); ?></code>
							<?php if ( $psst_row['has_passphrase'] ) : ?>
								<span class="psst-history__badge" title="<?php esc_attr_e( 'Protected with a pass phrase', 'psst' ); ?>"><?php esc_html_e( 'Pass phrase', 'psst' ); ?></span>
							<?php endif; ?>
						</td>

						<td data-label="<?php esc_attr_e( 'For', 'psst' ); ?>">
							<?php if ( '' !== $psst_row['recipient'] ) : ?>
								<?php echo esc_html( $psst_row['recipient'] ); ?>
								<?php if ( $psst_row['notified'] ) : ?>
									<span class="psst-history__badge"><?php esc_html_e( 'Emailed', 'psst' ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="psst-history__none"><?php esc_html_e( 'Not recorded', 'psst' ); ?></span>
							<?php endif; ?>
						</td>

						<td data-label="<?php esc_attr_e( 'Sent', 'psst' ); ?>">
							<?php
							printf(
								/* translators: %s: a human time difference, e.g. "2 hours". */
								esc_html__( '%s ago', 'psst' ),
								esc_html( human_time_diff( $psst_row['created_at'] ) )
							);
							?>
						</td>

						<td data-label="<?php esc_attr_e( 'Expires', 'psst' ); ?>">
							<?php if ( $psst_row['is_live'] && $psst_row['expires_at'] > 0 ) : ?>
								<?php echo esc_html( Secrets::format_expiry( $psst_row['expires_at'] ) ); ?>
							<?php else : ?>
								<span class="psst-history__none">&mdash;</span>
							<?php endif; ?>
						</td>

						<td data-label="<?php esc_attr_e( 'Status', 'psst' ); ?>">
							<span class="psst-history__state"><?php echo esc_html( $psst_row['state_label'] ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $psst_history['pages'] > 1 ) : ?>
			<nav class="psst-history__pagination" aria-label="<?php esc_attr_e( 'Sent secrets pages', 'psst' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						[
							'base'      => add_query_arg( 'psst_page', '%#%' ),
							'format'    => '',
							'current'   => $psst_page,
							'total'     => (int) $psst_history['pages'],
							'prev_text' => __( 'Newer', 'psst' ),
							'next_text' => __( 'Older', 'psst' ),
						]
					)
				);
				?>
			</nav>
		<?php endif; ?>

	<?php endif; ?>
</div>
