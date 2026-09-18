<?php
/**
 * Schema upgrades, including the one-way move off 1.x.
 *
 * Runs on init as well as on activation, because a Composer deploy replaces
 * the files without ever firing the activation hook.
 *
 * @package Linchpin\Psst\Controller
 */

namespace Linchpin\Psst\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Core\Scheduler;
use Linchpin\Psst\Model\Secret_Repository;
use Linchpin\Psst\Model\Settings;

/**
 * Class Upgrade
 */
class Upgrade implements Controller_Interface {

	/**
	 * The data layout this code expects.
	 */
	public const DB_VERSION = 2;

	/**
	 * The cron hooks 1.x scheduled.
	 */
	private const LEGACY_CRON = [ 'secret_expire', 'secret_purge', 'secret_cleanup' ];

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'init', [ self::class, 'maybe_upgrade' ], 20 );
		add_action( 'admin_notices', [ $this, 'crypto_key_notice' ] );
	}

	/**
	 * Bring the data layout up to date.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$stored = (int) get_option( Settings::OPTION_DB_VERSION, 0 );

		if ( $stored >= self::DB_VERSION ) {
			return;
		}

		// Anything below 2 is a 1.x install, or a fresh one: both get the same sweep.
		self::upgrade_from_1x();

		update_option( Settings::OPTION_DB_VERSION, self::DB_VERSION, true );
	}

	/**
	 * Purge everything 1.x left behind.
	 *
	 * Its secrets were encrypted with a server-side key this version no longer
	 * has any use for, its expiry never worked, and its options hold that key.
	 *
	 * @return void
	 */
	private static function upgrade_from_1x(): void {
		foreach ( self::LEGACY_CRON as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		$repository = new Secret_Repository();
		$repository->purge_legacy( 500 );

		if ( $repository->has_legacy() ) {
			Scheduler::queue_legacy_purge();
		}

		/*
		 * The 1.x `psst_settings` held `crypto_key` and `uninstall`; the 2.x
		 * option of the same name has a different shape. Only drop it when it
		 * is the old shape, so re-running this on a 2.x install is harmless.
		 */
		$settings = get_option( Settings::OPTION );

		if ( is_array( $settings ) && ( array_key_exists( 'crypto_key', $settings ) || array_key_exists( 'uninstall', $settings ) ) ) {
			delete_option( Settings::OPTION );
			Settings::forget();
		}

		foreach ( Settings::LEGACY_OPTIONS as $option ) {
			delete_option( $option );
		}

		global $wpdb;

		delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . 'psst_notifications', '', true );
		delete_metadata( 'user', 0, 'show_psst_welcome_panel', '', true );

		if ( defined( 'PSST_CRYPTO_KEY' ) ) {
			set_transient( 'psst_notice_crypto_key', 1, MONTH_IN_SECONDS );
		}

		Settings::seed_defaults();
		update_option( Settings::OPTION_FLUSH, 1 );
	}

	/**
	 * The 1.x key constant is unused now; tell an administrator to remove it.
	 *
	 * @return void
	 */
	public function crypto_key_notice(): void {
		if ( ! defined( 'PSST_CRYPTO_KEY' ) || ! get_transient( 'psst_notice_crypto_key' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html__( 'Psst 2.0 encrypts secrets in the browser and no longer uses PSST_CRYPTO_KEY. You can remove that constant from wp-config.php.', 'psst' )
		);
	}
}
