<?php
/**
 * Front end account registration.
 *
 * Posts back to the page it is rendered on and is handled on `template_redirect`,
 * deliberately: admin-post.php would mean routing account creation through
 * wp-admin on the very sites that have just locked wp-admin down.
 *
 * @package Linchpin\Psst\Controller\Account
 */

namespace Linchpin\Psst\Controller\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Controller_Interface;
use Linchpin\Psst\Helper\Rate_Limiter;
use Linchpin\Psst\Helper\Request_Context;
use Linchpin\Psst\Model\Settings;

/**
 * Class Registration
 */
class Registration implements Controller_Interface {

	public const NONCE_ACTION = 'psst_register';

	public const NONCE_FIELD = 'psst_register_nonce';

	/**
	 * Query arg carrying a failure back to the form.
	 */
	public const ERROR_ARG = 'psst_register_error';

	/**
	 * Registrations allowed per IP per hour.
	 */
	public const RATE_LIMIT = 5;

	/**
	 * The shortest password accepted.
	 *
	 * Length is the only requirement. Composition rules push people towards
	 * `Passw0rd!` and away from passphrases, and this is a plugin whose users
	 * are, by definition, thinking about secrets.
	 */
	public const MIN_PASSWORD_LENGTH = 12;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'template_redirect', [ $this, 'handle' ] );
	}

	/**
	 * Handle a posted registration.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			$this->fail( 'psst_bad_nonce' );
		}

		if ( is_user_logged_in() ) {
			$this->fail( 'psst_already_logged_in' );
		}

		if ( ! Settings::registration_open() ) {
			$this->fail( 'psst_registration_closed' );
		}

		// The honeypot: a real person never sees this field, so anything in it is a bot.
		if ( isset( $_POST['psst_hp'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_POST['psst_hp'] ) ) ) ) {
			$this->fail( 'psst_rejected' );
		}

		$limited = $this->rate_limited();

		if ( $limited ) {
			$this->fail( 'psst_rate_limited' );
		}

		$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';

		/*
		 * Passwords are taken raw, and must be. Sanitizing one changes it: strip
		 * a tag or a slash out of a passphrase and the account is created with a
		 * password the person did not choose and cannot type again. They are
		 * never echoed and never interpolated into SQL — wp_hash_password()
		 * hashes them and nothing else sees them.
		 */
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, Linchpin.Security.ValidatedSanitizedInput.InputNotSanitized -- A sanitized password is a different password.
		$password = isset( $_POST['user_pass'] ) ? (string) wp_unslash( $_POST['user_pass'] ) : '';
		$confirm  = isset( $_POST['user_pass_confirm'] ) ? (string) wp_unslash( $_POST['user_pass_confirm'] ) : '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, Linchpin.Security.ValidatedSanitizedInput.InputNotSanitized

		$error = $this->validate( $email, $password, $confirm );

		if ( '' !== $error ) {
			$this->fail( $error, $email );
		}

		/**
		 * Filters whether a registration passes the site's anti-abuse challenge.
		 *
		 * The same shape as `psst_create_challenge`: return a WP_Error to refuse.
		 *
		 * @param true|\WP_Error $result True to allow.
		 * @param string         $email  The address being registered.
		 */
		$challenge = apply_filters( 'psst_register_challenge', true, $email );

		if ( is_wp_error( $challenge ) ) {
			$this->fail( 'psst_challenge_failed', $email );
		}

		$user_id = $this->create_user( $email, $password );

		if ( is_wp_error( $user_id ) ) {
			$this->fail( $user_id->get_error_code(), $email );
		}

		/**
		 * Fires after an account was created from the front end.
		 *
		 * @param int    $user_id The new user.
		 * @param string $email   Their address.
		 */
		do_action( 'psst_user_registered', $user_id, $email );

		$this->finish( $user_id );
	}

	/**
	 * Check the submitted values.
	 *
	 * @param string $email    The address.
	 * @param string $password The password.
	 * @param string $confirm  The repeated password.
	 *
	 * @return string An error code, or '' when everything is in order.
	 */
	private function validate( string $email, string $password, string $confirm ): string {
		if ( '' === $email || ! is_email( $email ) ) {
			return 'psst_bad_email';
		}

		if ( ! $this->email_allowed( $email ) ) {
			return 'psst_email_not_allowed';
		}

		if ( email_exists( $email ) ) {
			return 'psst_email_exists';
		}

		if ( mb_strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
			return 'psst_password_short';
		}

		if ( $password !== $confirm ) {
			return 'psst_password_mismatch';
		}

		return '';
	}

	/**
	 * Whether an address is one this site will accept.
	 *
	 * On multisite the network's banned-domains list already applies through
	 * `is_email_address_unsafe()`; this adds a filter so a site can narrow it
	 * further, which is the usual ask on an internal tool.
	 *
	 * @param string $email The address.
	 *
	 * @return bool
	 */
	private function email_allowed( string $email ): bool {
		if ( is_multisite() && is_email_address_unsafe( $email ) ) {
			return false;
		}

		/**
		 * Filters whether an email address may register an account.
		 *
		 * Returning false for anything outside a domain allow-list is the
		 * common use.
		 *
		 * @param bool   $allowed Whether to accept it.
		 * @param string $email   The address.
		 */
		return (bool) apply_filters( 'psst_registration_email_allowed', true, $email );
	}

	/**
	 * Create the account, on multisite or off it.
	 *
	 * On a network, `wpmu_create_user()` makes a user who belongs to the network
	 * and to no site on it — that is what it is for — so the account has to be
	 * added to this site explicitly or the user logs in to nothing.
	 *
	 * @param string $email    The address, which is also the username.
	 * @param string $password The chosen password.
	 *
	 * @return int|\WP_Error The new user id.
	 */
	private function create_user( string $email, string $password ): int|\WP_Error {
		$username = $this->username_from_email( $email );

		if ( is_multisite() ) {
			$validation = wpmu_validate_user_signup( $username, $email );

			if ( $validation['errors']->has_errors() ) {
				return new \WP_Error( 'psst_signup_invalid', $validation['errors']->get_error_message() );
			}

			$user_id = wpmu_create_user( $validation['user_name'], $password, $email );

			if ( ! $user_id ) {
				return new \WP_Error( 'psst_create_failed', __( 'The account could not be created.', 'psst' ) );
			}

			$added = add_user_to_blog( get_current_blog_id(), (int) $user_id, $this->default_role() );

			if ( is_wp_error( $added ) ) {
				return $added;
			}

			return (int) $user_id;
		}

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = new \WP_User( (int) $user_id );
		$user->set_role( $this->default_role() );

		return (int) $user_id;
	}

	/**
	 * The role a new account gets.
	 *
	 * @return string
	 */
	private function default_role(): string {
		/**
		 * Filters the role given to an account registered from the front end.
		 *
		 * @param string $role Defaults to the site's own default role.
		 */
		return (string) apply_filters( 'psst_registration_role', (string) get_option( 'default_role', 'subscriber' ) );
	}

	/**
	 * A free username derived from an address.
	 *
	 * People log in with their email address, so the username is plumbing. It
	 * still has to be unique and legal, and `sanitize_user()` can empty a
	 * perfectly good address, so there is a fallback.
	 *
	 * @param string $email The address.
	 *
	 * @return string
	 */
	private function username_from_email( string $email ): string {
		$base = sanitize_user( (string) strstr( $email, '@', true ), true );
		$base = '' !== $base ? strtolower( $base ) : 'user';

		if ( ! username_exists( $base ) ) {
			return $base;
		}

		for ( $suffix = 2; $suffix < 100; $suffix++ ) {
			$candidate = $base . $suffix;

			if ( ! username_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $base . wp_generate_password( 6, false );
	}

	/**
	 * Log the new user in and send them on.
	 *
	 * @param int $user_id The new account.
	 *
	 * @return void
	 */
	private function finish( int $user_id ): void {
		/**
		 * Filters whether a newly registered user is signed in immediately.
		 *
		 * They chose the password and typed it twice, so they have proved as
		 * much as a login would. Sites that want a confirmed address before an
		 * active session return false and send their own verification.
		 *
		 * @param bool $auto_login Defaults to true.
		 * @param int  $user_id    The new account.
		 */
		$auto_login = (bool) apply_filters( 'psst_registration_auto_login', true, $user_id );

		if ( $auto_login ) {
			$user = new \WP_User( $user_id );

			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, false );

			/*
			 * wp_set_auth_cookie() does not fire this; wp_signon() does. Session
			 * managers and audit logs listen for it, so a registration that signs
			 * someone in has to announce itself the same way a login would.
			 */
			do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, deliberately fired.
		}

		wp_new_user_notification( $user_id, null, 'admin' );

		$destination = $auto_login
			? ( Settings::get_account_url() ?: Settings::get_create_url() )
			: add_query_arg( 'psst_registered', '1', Settings::get_login_url() );

		nocache_headers();
		wp_safe_redirect( $destination );
		exit;
	}

	/**
	 * Count this attempt against the hourly per-IP limit.
	 *
	 * @return bool Whether the caller is over it.
	 */
	private function rate_limited(): bool {
		/**
		 * Filters how many accounts one address may register per hour.
		 *
		 * @param int $limit Defaults to five. Zero disables the limit.
		 */
		$limit = (int) apply_filters( 'psst_registration_rate_limit', self::RATE_LIMIT );

		if ( $limit <= 0 ) {
			return false;
		}

		$ip = Request_Context::client_ip( (string) Settings::get( 'trusted_proxy_header' ) );

		if ( '' === $ip ) {
			return false;
		}

		return 0 !== Rate_Limiter::with_transients()->hit( 'register', $ip, $limit );
	}

	/**
	 * Send the visitor back to the form with an error, and what they typed.
	 *
	 * @param string $code  The error code.
	 * @param string $email The address to redraw, if any.
	 *
	 * @return void
	 */
	private function fail( string $code, string $email = '' ): void {
		$url = Settings::get_register_url();

		if ( '' === $url ) {
			$url = Settings::get_login_url();
		}

		nocache_headers();
		wp_safe_redirect(
			add_query_arg(
				[
					self::ERROR_ARG => rawurlencode( $code ),
					'psst_email'    => rawurlencode( $email ),
				],
				$url
			)
		);
		exit;
	}

	/**
	 * A readable message for a registration error code.
	 *
	 * @param string $code The code.
	 *
	 * @return string
	 */
	public static function error_message( string $code ): string {
		switch ( $code ) {
			case 'psst_bad_email':
				return __( 'Enter a valid email address.', 'psst' );

			case 'psst_email_exists':
				return __( 'An account already uses that email address. Try signing in instead.', 'psst' );

			case 'psst_email_not_allowed':
				return __( 'That email address cannot be used on this site.', 'psst' );

			case 'psst_password_short':
				return sprintf(
					/* translators: %d: the minimum number of characters. */
					__( 'Use a password of at least %d characters. A short sentence works well.', 'psst' ),
					self::MIN_PASSWORD_LENGTH
				);

			case 'psst_password_mismatch':
				return __( 'The two passwords do not match.', 'psst' );

			case 'psst_rate_limited':
				return __( 'Too many accounts have been created from here. Please try again later.', 'psst' );

			case 'psst_registration_closed':
				return __( 'This site is not accepting new accounts right now.', 'psst' );

			case 'psst_bad_nonce':
				return __( 'That form expired. Please try again.', 'psst' );

			default:
				/**
				 * Filters the message shown for a registration error code Psst
				 * has no wording of its own for.
				 *
				 * @param string $message The fallback.
				 * @param string $code    The code.
				 */
				return (string) apply_filters( 'psst_registration_error_message', __( 'The account could not be created. Please try again.', 'psst' ), $code );
		}
	}
}
