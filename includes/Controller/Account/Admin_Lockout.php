<?php
/**
 * Keep account holders out of wp-admin.
 *
 * A user who registered to send a secret has no business in the WordPress
 * administration area, their own profile screen included. This sends them to
 * the front end account area instead, and takes the admin bar away so the door
 * is not even visible.
 *
 * Every exemption below exists because something breaks without it. wp-admin is
 * not only screens: admin-ajax.php and admin-post.php are how front end code
 * talks to WordPress, and both boot the admin. Locking those down locks down
 * the front end.
 *
 * @package Linchpin\Psst\Controller\Account
 */

namespace Linchpin\Psst\Controller\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Controller_Interface;
use Linchpin\Psst\Model\Settings;

/**
 * Class Admin_Lockout
 */
class Admin_Lockout implements Controller_Interface {

	/**
	 * Entry points that boot the admin but are not the admin.
	 *
	 * Front end forms post to admin-post.php and front end scripts call
	 * admin-ajax.php. Both run `admin_init`. Redirecting them turns every such
	 * request into an HTML redirect the caller cannot parse.
	 */
	private const ALWAYS_ALLOWED = [ 'admin-ajax.php', 'admin-post.php' ];

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'admin_init', [ $this, 'block' ], 1 );
		add_action( 'after_setup_theme', [ $this, 'hide_admin_bar' ] );
	}

	/**
	 * Whether the lockout is switched on.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return Settings::accounts_enabled() && (bool) Settings::get( 'block_admin_access' );
	}

	/**
	 * The capability that earns someone a way into wp-admin.
	 *
	 * `edit_posts` rather than `manage_options`, because the question this
	 * setting answers is "does this person have work to do in there" and not
	 * "are they an administrator". A subscriber who signed up to send a secret
	 * has nothing to do in wp-admin; an editor does. A site that wants the
	 * strict reading filters this to `manage_options`.
	 *
	 * @return string
	 */
	public static function required_capability(): string {
		/**
		 * Filters the capability a user needs to reach wp-admin while the
		 * lockout is active.
		 *
		 * @param string $capability Defaults to edit_posts.
		 */
		return (string) apply_filters( 'psst_admin_access_capability', 'edit_posts' );
	}

	/**
	 * Whether a user is shut out of wp-admin.
	 *
	 * A super administrator never is, on any site of a network. Locking one out
	 * of a subsite would mean losing the only account that can put it right.
	 *
	 * @param \WP_User|null $user The user, or null for the current one.
	 *
	 * @return bool
	 */
	public static function user_is_locked_out( ?\WP_User $user = null ): bool {
		if ( ! self::is_active() ) {
			return false;
		}

		$user = $user ?? wp_get_current_user();

		// A logged-out visitor is not locked out of wp-admin; they are simply not in it.
		if ( 0 === $user->ID ) {
			return false;
		}

		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return false;
		}

		$locked = ! user_can( $user, self::required_capability() );

		/**
		 * Filters whether a user is kept out of wp-admin.
		 *
		 * @param bool     $locked Whether to lock this user out.
		 * @param \WP_User $user   The user.
		 */
		return (bool) apply_filters( 'psst_user_locked_out_of_admin', $locked, $user );
	}

	/**
	 * Whether a URL points into wp-admin.
	 *
	 * Used when deciding where to send someone after they log in, so they are
	 * never handed a destination they are about to be bounced off.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	public static function is_admin_url( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $path ) {
			return false;
		}

		foreach ( self::ALWAYS_ALLOWED as $allowed ) {
			if ( str_ends_with( $path, '/' . $allowed ) ) {
				return false;
			}
		}

		return false !== strpos( $path, '/wp-admin' );
	}

	/**
	 * Where a locked-out user goes instead.
	 *
	 * @return string
	 */
	public static function destination(): string {
		$url = Settings::get_account_url();

		if ( '' === $url ) {
			$url = Settings::get_create_url();
		}

		/**
		 * Filters where a user shut out of wp-admin is sent.
		 *
		 * @param string $url The destination.
		 */
		return (string) apply_filters( 'psst_admin_lockout_destination', $url );
	}

	/**
	 * Bounce a locked-out user off wp-admin.
	 *
	 * @return void
	 */
	public function block(): void {
		global $pagenow;

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( in_array( (string) $pagenow, self::ALWAYS_ALLOWED, true ) ) {
			return;
		}

		if ( ! self::user_is_locked_out() ) {
			return;
		}

		$destination = self::destination();

		if ( '' === $destination ) {
			return;
		}

		/**
		 * Fires just before a user is redirected out of wp-admin.
		 *
		 * @param int    $user_id     The user.
		 * @param string $destination Where they are being sent.
		 */
		do_action( 'psst_admin_access_blocked', get_current_user_id(), $destination );

		nocache_headers();
		wp_safe_redirect( $destination );
		exit;
	}

	/**
	 * No admin bar for someone who cannot use what it links to.
	 *
	 * @return void
	 */
	public function hide_admin_bar(): void {
		if ( self::user_is_locked_out() ) {
			show_admin_bar( false );
		}
	}
}
