<?php
/**
 * Activation and deactivation.
 *
 * @package Linchpin\Psst\Controller
 */

namespace Linchpin\Psst\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Core\Scheduler;
use Linchpin\Psst\Model\Post_Type\Secret;
use Linchpin\Psst\Model\Settings;

/**
 * Class Install
 */
class Install implements Controller_Interface {

	/**
	 * Hooks. Activation itself is wired from psst.php.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'init', [ $this, 'ensure_caps' ], 5 );
		add_action( 'update_option_' . Settings::OPTION, [ $this, 'on_settings_saved' ], 10, 2 );
	}

	/**
	 * Create the account pages the first time the account layer is switched on.
	 *
	 * Not on activation: the layer is off by default, and three pages nobody
	 * asked for appearing in a site's menu is a worse first impression than one
	 * extra step when the feature is actually wanted.
	 *
	 * @param mixed $old_value The settings before the save.
	 * @param mixed $value     The settings after it.
	 *
	 * @return void
	 */
	public function on_settings_saved( $old_value, $value ): void {
		$was_on = ! empty( ( (array) $old_value )['accounts_enabled'] );
		$is_on  = ! empty( ( (array) $value )['accounts_enabled'] );

		if ( $is_on && ! $was_on ) {
			self::ensure_account_pages();
		}
	}

	/**
	 * Activation.
	 *
	 * Runs inside a request where init has already fired, so the scheduler is
	 * available.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::add_caps();
		Settings::seed_defaults();
		self::ensure_pages();
		Upgrade::maybe_upgrade();
		Scheduler::ensure_sweep( true );
		Lifecycle::rearm();
		update_option( Settings::OPTION_FLUSH, 1 );

		/**
		 * Fires after activation.
		 */
		do_action( 'psst_activated' );
	}

	/**
	 * Deactivation. Data stays; only the schedule goes.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		Scheduler::clear_all();
		flush_rewrite_rules( false );
	}

	/**
	 * Grant the post type's capabilities to administrators.
	 *
	 * @return void
	 */
	public static function add_caps(): void {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( Secret::capabilities() as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * A Composer deploy never fires the activation hook; make sure the caps are
	 * there anyway. Cheap: one option read once the flag is set.
	 *
	 * @return void
	 */
	public function ensure_caps(): void {
		if ( get_option( 'psst_caps_added' ) ) {
			return;
		}

		self::add_caps();
		update_option( 'psst_caps_added', 1, true );
	}

	/**
	 * Remove the capabilities from every role.
	 *
	 * @return void
	 */
	public static function remove_caps(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( Secret::capabilities() as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		delete_option( 'psst_caps_added' );
	}

	/**
	 * The two pages the routes need, keyed the way the settings are.
	 */
	public const PAGE_CREATE = 'create';
	public const PAGE_REVEAL = 'reveal';

	/**
	 * The account pages, created only once the account layer is switched on.
	 */
	public const PAGE_LOGIN    = 'login';
	public const PAGE_REGISTER = 'register';
	public const PAGE_ACCOUNT  = 'account';

	/**
	 * Which setting holds the page id for each kind of page.
	 *
	 * @var array<string, string>
	 */
	private const PAGE_SETTINGS = [
		self::PAGE_CREATE   => 'create_page_id',
		self::PAGE_REVEAL   => 'reveal_page_id',
		self::PAGE_LOGIN    => 'login_page_id',
		self::PAGE_REGISTER => 'register_page_id',
		self::PAGE_ACCOUNT  => 'account_page_id',
	];

	/**
	 * The setting that stores a given kind of page.
	 *
	 * @param string $kind One of the PAGE_* constants.
	 *
	 * @return string The setting key, or '' for an unknown kind.
	 */
	public static function setting_for_page( string $kind ): string {
		return self::PAGE_SETTINGS[ $kind ] ?? '';
	}

	/**
	 * Create the three account pages, if they are missing.
	 *
	 * @return void
	 */
	public static function ensure_account_pages(): void {
		$settings = Settings::all();
		$changed  = false;

		foreach ( [ self::PAGE_LOGIN, self::PAGE_REGISTER, self::PAGE_ACCOUNT ] as $kind ) {
			$key = self::setting_for_page( $kind );

			if ( self::page_exists( (int) ( $settings[ $key ] ?? 0 ) ) ) {
				continue;
			}

			$settings[ $key ] = self::create_page( $kind );
			$changed          = true;
		}

		if ( $changed ) {
			Settings::save( $settings );
		}
	}

	/**
	 * Create the two pages the routes need, if they are missing.
	 *
	 * @return void
	 */
	public static function ensure_pages(): void {
		$settings = Settings::all();
		$changed  = false;

		if ( ! self::page_exists( (int) $settings['reveal_page_id'] ) ) {
			$settings['reveal_page_id'] = self::create_page( self::PAGE_REVEAL );
			$changed                    = true;
		}

		if ( ! self::page_exists( (int) $settings['create_page_id'] ) ) {
			$settings['create_page_id'] = self::create_page( self::PAGE_CREATE );
			$changed                    = true;
		}

		if ( $changed ) {
			Settings::save( $settings );
		}
	}

	/**
	 * What a page of each kind is made of.
	 *
	 * @param string $kind One of the PAGE_* constants.
	 *
	 * @return array{title: string, slug: string, content: string}|null Null for an unknown kind.
	 */
	public static function page_blueprint( string $kind ): ?array {
		switch ( $kind ) {
			case self::PAGE_REVEAL:
				return [
					'title'   => _x( 'Secret', 'reveal page title', 'psst' ),
					'slug'    => 's',
					'content' => '<!-- wp:psst/secret-viewer /-->',
				];

			case self::PAGE_CREATE:
				return [
					'title'   => _x( 'Share a Secret', 'create page title', 'psst' ),
					'slug'    => 'share',
					'content' => '<!-- wp:pattern {"slug":"psst/create-page"} /-->',
				];

			case self::PAGE_LOGIN:
				return [
					'title'   => _x( 'Sign In', 'login page title', 'psst' ),
					'slug'    => 'sign-in',
					'content' => '<!-- wp:pattern {"slug":"psst/sign-in-page"} /-->',
				];

			case self::PAGE_REGISTER:
				return [
					'title'   => _x( 'Create an Account', 'register page title', 'psst' ),
					'slug'    => 'register',
					'content' => '<!-- wp:pattern {"slug":"psst/register-page"} /-->',
				];

			case self::PAGE_ACCOUNT:
				return [
					'title'   => _x( 'Your Account', 'account page title', 'psst' ),
					'slug'    => 'account',
					'content' => '<!-- wp:pattern {"slug":"psst/account-page"} /-->',
				];
		}

		return null;
	}

	/**
	 * Insert a page of the given kind with the blocks it needs already in it.
	 *
	 * @param string $kind  One of the PAGE_* constants.
	 * @param bool   $reuse Return a published page that already has the slug
	 *                      instead of adding another. Activation reuses; the
	 *                      admin screen's "create a page" button does not, and
	 *                      WordPress gives the new page a unique slug.
	 *
	 * @return int The page id, or 0.
	 */
	public static function create_page( string $kind, bool $reuse = true ): int {
		$blueprint = self::page_blueprint( $kind );

		if ( null === $blueprint ) {
			return 0;
		}

		return self::insert_page( $blueprint['title'], $blueprint['slug'], $blueprint['content'], $reuse );
	}

	/**
	 * Whether a page id points at a published page.
	 *
	 * @param int $page_id The id.
	 *
	 * @return bool
	 */
	private static function page_exists( int $page_id ): bool {
		return $page_id > 0 && 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id );
	}

	/**
	 * Insert a page, optionally reusing one that already has the slug.
	 *
	 * @param string $title   Title.
	 * @param string $slug    Slug.
	 * @param string $content Block markup.
	 * @param bool   $reuse   Whether a published page with the slug counts.
	 *
	 * @return int The page id, or 0.
	 */
	private static function insert_page( string $title, string $slug, string $content, bool $reuse ): int {
		$existing = $reuse ? get_page_by_path( $slug, OBJECT, 'page' ) : null;

		if ( $existing instanceof \WP_Post && 'publish' === $existing->post_status ) {
			return (int) $existing->ID;
		}

		$page_id = wp_insert_post(
			[
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => $title,
				'post_name'      => $slug,
				'post_content'   => $content,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			],
			true
		);

		return is_wp_error( $page_id ) ? 0 : (int) $page_id;
	}
}
