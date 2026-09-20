<?php
/**
 * The front end login, logout and lost-password flow.
 *
 * Core's wp-login.php still does the authenticating — it is the only thing that
 * should — but a visitor never has to look at it. The front end page posts
 * credentials to it and a failed attempt comes back to the front end rather
 * than landing on core's error screen.
 *
 * Structurally this follows the login controller in my.linchpin.com: a
 * settings-configured page, the `login_url` and `logout_url` filters pointed at
 * it, wp-login.php intercepted on plain GETs only, and a bypass key so the
 * front end page being broken can never mean nobody can log in.
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
 * Class Login
 */
class Login implements Controller_Interface {

	/**
	 * Hidden field marking a POST as coming from the front end form.
	 *
	 * Its presence is what tells a failed attempt to go back to the front end
	 * page instead of rendering core's error screen, so a login posted from
	 * anywhere else is left entirely alone.
	 */
	public const FRONTEND_FLAG = 'psst_frontend_login';

	/**
	 * Query arg that forces core's wp-login.php.
	 *
	 * The escape hatch. If the configured login page is deleted, unpublished or
	 * simply has no login block on it, every route to wp-admin would otherwise
	 * bounce off a page that cannot log anyone in — on a site where wp-admin is
	 * also locked down, that is unrecoverable without database access.
	 * `wp-login.php?psst=bypass` always renders core's form.
	 */
	public const BYPASS_ARG = 'psst';

	public const BYPASS_VALUE = 'bypass';

	/**
	 * Query arg carrying a failure back to the front end page.
	 */
	public const ERROR_ARG = 'psst_login_error';

	/**
	 * Query arg carrying the address that was tried, so the form can prefill it.
	 */
	public const LOGIN_ARG = 'psst_login';

	/**
	 * Query arg marking a completed lost-password request.
	 */
	public const SENT_ARG = 'psst_sent';

	/**
	 * Actions on wp-login.php that core owns and must keep.
	 *
	 * Logging out, resetting a password and the interim login expiry modal are
	 * all core flows with their own security properties. Redirecting them to a
	 * front end page breaks them and gains nothing.
	 */
	private const NATIVE_ACTIONS = [
		'logout',
		'postpass',
		'rp',
		'resetpass',
		'confirm_admin_email',
		'confirmaction',
		'entered_recovery_mode',
	];

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'init', [ $this, 'intercept_wp_login' ] );
		add_action( 'template_redirect', [ $this, 'handle_lost_password' ] );
		add_action( 'template_redirect', [ $this, 'redirect_logged_in' ] );
		add_action( 'wp_login_failed', [ $this, 'bounce_failure' ], 10, 2 );

		add_filter( 'login_form_middle', [ $this, 'login_form_middle' ], 10, 2 );
		add_filter( 'login_url', [ $this, 'login_url' ], 10, 3 );
		add_filter( 'logout_url', [ $this, 'logout_url' ], 10, 2 );
		add_filter( 'lostpassword_url', [ $this, 'lostpassword_url' ], 10, 2 );
		add_filter( 'register_url', [ $this, 'register_url' ] );
		add_filter( 'login_redirect', [ $this, 'login_redirect' ], 10, 3 );
	}

	/**
	 * Whether the front end login is configured and switched on.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return '' !== Settings::get_login_url();
	}

	/**
	 * Whether this request asked for core's login form explicitly.
	 *
	 * @return bool
	 */
	public static function is_bypassed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended -- Reading a routing flag, not acting on input.
		return isset( $_GET[ self::BYPASS_ARG ] ) && self::BYPASS_VALUE === sanitize_text_field( wp_unslash( $_GET[ self::BYPASS_ARG ] ) );
	}

	/**
	 * Whether wp-login.php is doing something core has to keep doing.
	 *
	 * @return bool
	 */
	private function is_native_action(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended -- Reading the action to decide routing only.
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( in_array( $action, self::NATIVE_ACTIONS, true ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended -- Same.
		return isset( $_REQUEST['interim-login'] );
	}

	/**
	 * Send a plain GET of wp-login.php to the front end login page.
	 *
	 * Only ever a GET. wp-login.php is what actually checks the credentials the
	 * front end form posts to it, so redirecting a POST throws the submission
	 * away and reads, from the outside, as "logging in does nothing".
	 *
	 * @return void
	 */
	public function intercept_wp_login(): void {
		global $pagenow;

		if ( 'wp-login.php' !== $pagenow || ! self::is_active() ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( 'GET' !== $method || self::is_bypassed() || $this->is_native_action() || is_user_logged_in() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended -- Preserving the destination, validated by wp_safe_redirect below.
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

		$target = self::is_registration_request()
			? Settings::get_register_url()
			: $this->login_page_url( $redirect_to );

		if ( '' === $target ) {
			return;
		}

		nocache_headers();
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Whether wp-login.php was asked for the registration form.
	 *
	 * @return bool
	 */
	private static function is_registration_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, Linchpin.Security.NonceVerification.Recommended -- Routing only.
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		return 'register' === $action && '' !== Settings::get_register_url();
	}

	/**
	 * The login page URL, carrying a destination when there is one.
	 *
	 * @param string $redirect_to Where to go afterwards.
	 *
	 * @return string
	 */
	private function login_page_url( string $redirect_to = '' ): string {
		$url = Settings::get_login_url();

		if ( '' === $url || '' === $redirect_to ) {
			return $url;
		}

		// Never point a destination back at the login page itself.
		if ( untrailingslashit( $this->path_of( $redirect_to ) ) === untrailingslashit( $this->path_of( $url ) ) ) {
			return $url;
		}

		return add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url );
	}

	/**
	 * The path component of a URL, for comparing two URLs by page.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	private function path_of( string $url ): string {
		return (string) wp_parse_url( $url, PHP_URL_PATH );
	}

	/**
	 * Point wp_login_url() at the front end page.
	 *
	 * @param string $url          The URL core built.
	 * @param string $redirect     Where to go after logging in.
	 * @param bool   $force_reauth Whether to force reauthentication.
	 *
	 * @return string
	 */
	public function login_url( $url, $redirect, $force_reauth ): string {
		if ( ! self::is_active() || self::is_bypassed() || $this->is_native_action() ) {
			return $url;
		}

		$target = $this->login_page_url( (string) $redirect );

		if ( '' === $target ) {
			return $url;
		}

		return $force_reauth ? add_query_arg( 'reauth', '1', $target ) : $target;
	}

	/**
	 * Log out to the front end rather than to wp-login.php's "you are logged
	 * out" screen.
	 *
	 * @param string $url      The logout URL.
	 * @param string $redirect Where the caller wanted to go.
	 *
	 * @return string
	 */
	public function logout_url( $url, $redirect ): string {
		if ( ! self::is_active() || '' !== (string) $redirect ) {
			return $url;
		}

		$home = Settings::get_create_url();

		return add_query_arg( 'redirect_to', rawurlencode( $home ), $url );
	}

	/**
	 * Lost password lives on the login page, as a mode of it.
	 *
	 * @param string $url      The URL core built.
	 * @param string $redirect Where to go afterwards.
	 *
	 * @return string
	 */
	public function lostpassword_url( $url, $redirect ): string {
		if ( ! self::is_active() ) {
			return $url;
		}

		return add_query_arg( 'action', 'lostpassword', Settings::get_login_url() );
	}

	/**
	 * Point wp_registration_url() at the front end page.
	 *
	 * @param string $url The URL core built.
	 *
	 * @return string
	 */
	public function register_url( $url ): string {
		$target = Settings::get_register_url();

		return '' !== $target ? $target : $url;
	}

	/**
	 * Where a user lands after logging in.
	 *
	 * A user who cannot reach wp-admin must never be sent there, which is what
	 * core does by default.
	 *
	 * @param string           $redirect_to           Where core decided to go.
	 * @param string           $requested_redirect_to What was asked for.
	 * @param \WP_User|\WP_Error $user                The user, or the failure.
	 *
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested_redirect_to, $user ): string {
		if ( ! ( $user instanceof \WP_User ) || ! Settings::accounts_enabled() ) {
			return $redirect_to;
		}

		$account = Settings::get_account_url();

		if ( '' !== (string) $requested_redirect_to && ! Admin_Lockout::is_admin_url( (string) $requested_redirect_to ) ) {
			return $requested_redirect_to;
		}

		if ( Admin_Lockout::user_is_locked_out( $user ) ) {
			return '' !== $account ? $account : Settings::get_create_url();
		}

		return $redirect_to;
	}

	/**
	 * Send a failed front end login back to the front end.
	 *
	 * @param string    $username What was typed.
	 * @param \WP_Error $error    Why it failed.
	 *
	 * @return void
	 */
	public function bounce_failure( $username, $error = null ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, Linchpin.Security.NonceVerification.Missing -- Only reading our own marker; core has already rejected these credentials.
		if ( ! isset( $_POST[ self::FRONTEND_FLAG ] ) || ! self::is_active() ) {
			return;
		}

		$code = $error instanceof \WP_Error ? $error->get_error_code() : 'failed';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, Linchpin.Security.NonceVerification.Missing -- Same; the value is only echoed back into the form.
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		$url = add_query_arg(
			[
				self::ERROR_ARG => rawurlencode( (string) $code ),
				self::LOGIN_ARG => rawurlencode( sanitize_user( (string) $username, true ) ),
			],
			$this->login_page_url( $redirect_to )
		);

		nocache_headers();
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle a lost-password request posted from the login page.
	 *
	 * Core's `retrieve_password()` does the work, so the token, its expiry and
	 * the mail are all core's. The response is deliberately identical whether
	 * or not the address is known, because a differing one enumerates users.
	 *
	 * @return void
	 */
	public function handle_lost_password(): void {
		if ( ! self::is_active() || ! isset( $_POST['psst_lost_password_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['psst_lost_password_nonce'] ) ), 'psst_lost_password' ) ) {
			return;
		}

		$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';

		if ( '' !== $login ) {
			$_POST['user_login'] = $login;

			// Result deliberately discarded: telling the caller whether the address exists would enumerate users.
			retrieve_password();
		}

		nocache_headers();
		wp_safe_redirect( add_query_arg( self::SENT_ARG, '1', Settings::get_login_url() ) );
		exit;
	}

	/**
	 * Mark a form built by wp_login_form() as ours.
	 *
	 * `wp_login_form()` posts to wp-login.php, which is exactly right — core
	 * should be the only thing checking a password. What core cannot know is
	 * that a failure should be shown on a front end page, so the form carries a
	 * flag saying so and `bounce_failure()` reads it back.
	 *
	 * @param string               $content The markup so far.
	 * @param array<string, mixed> $args    The wp_login_form() arguments.
	 *
	 * @return string
	 */
	public function login_form_middle( $content, $args ): string {
		if ( ! self::is_active() ) {
			return (string) $content;
		}

		return (string) $content . sprintf(
			'<input type="hidden" name="%s" value="1" />',
			esc_attr( self::FRONTEND_FLAG )
		);
	}

	/**
	 * A logged-in user has no use for the login page.
	 *
	 * @return void
	 */
	public function redirect_logged_in(): void {
		if ( ! is_user_logged_in() || ! self::is_active() ) {
			return;
		}

		$login_page_id    = (int) Settings::get( 'login_page_id' );
		$register_page_id = (int) Settings::get( 'register_page_id' );

		if ( ! is_page( $login_page_id ) && ! ( $register_page_id > 0 && is_page( $register_page_id ) ) ) {
			return;
		}

		$target = Settings::get_account_url();

		if ( '' === $target ) {
			$target = Settings::get_create_url();
		}

		nocache_headers();
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * A readable message for a core login error code.
	 *
	 * Deliberately vague about which half of the pair was wrong. Core's own
	 * messages distinguish an unknown user from a bad password, which tells an
	 * attacker which addresses are worth attacking.
	 *
	 * @param string $code The error code.
	 *
	 * @return string
	 */
	public static function error_message( string $code ): string {
		switch ( $code ) {
			case 'empty_username':
			case 'empty_password':
				return __( 'Enter both your email address and your password.', 'psst' );

			case 'invalid_username':
			case 'invalid_email':
			case 'incorrect_password':
			case 'authentication_failed':
				return __( 'That email address and password do not match.', 'psst' );

			case 'too_many_retries':
				return __( 'Too many attempts. Please wait a few minutes and try again.', 'psst' );

			default:
				/**
				 * Filters the message shown for a login error code Psst does not
				 * have wording of its own for.
				 *
				 * @param string $message The fallback message.
				 * @param string $code    The error code.
				 */
				return (string) apply_filters( 'psst_login_error_message', __( 'We could not sign you in. Please try again.', 'psst' ), $code );
		}
	}
}
