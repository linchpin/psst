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
	 * Create the two pages the routes need, if they are missing.
	 *
	 * @return void
	 */
	public static function ensure_pages(): void {
		$settings = Settings::all();
		$changed  = false;

		if ( ! self::page_exists( (int) $settings['reveal_page_id'] ) ) {
			$settings['reveal_page_id'] = self::create_page(
				_x( 'Secret', 'reveal page title', 'psst' ),
				's',
				'<!-- wp:psst/secret-viewer /-->'
			);
			$changed                    = true;
		}

		if ( ! self::page_exists( (int) $settings['create_page_id'] ) ) {
			$settings['create_page_id'] = self::create_page(
				_x( 'Share a Secret', 'create page title', 'psst' ),
				'share',
				'<!-- wp:pattern {"slug":"psst/create-page"} /-->'
			);
			$changed                    = true;
		}

		if ( $changed ) {
			Settings::save( $settings );
		}
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
	 * Insert a page, reusing one that already has the slug.
	 *
	 * @param string $title   Title.
	 * @param string $slug    Slug.
	 * @param string $content Block markup.
	 *
	 * @return int The page id, or 0.
	 */
	private static function create_page( string $title, string $slug, string $content ): int {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );

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
