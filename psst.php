<?php
/**
 * Plugin Name:       Psst
 * Plugin URI:        https://github.com/linchpin/psst
 * Description:       Pretty Secure Secret Transmissions. Share one-time, expiring secrets that are encrypted in the browser; the server only ever stores ciphertext it cannot read.
 * x-release-please-start-version
 * Version:           2.2.0
 * x-release-please-end
 * Author:            Linchpin
 * Author URI:        https://linchpin.com
 * Requires PHP:      8.3
 * Requires at least: 6.9
 * Tested up to:      7.1
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       psst
 * Domain Path:       /languages
 *
 * @package Linchpin\Psst
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PSST_FILE', __FILE__ );
define( 'PSST_PATH', plugin_dir_path( __FILE__ ) );
define( 'PSST_URL', plugin_dir_url( __FILE__ ) );
define( 'PSST_BASENAME', plugin_basename( __FILE__ ) );
define( 'PSST_BLOCK_PATH', plugin_dir_path( __FILE__ ) . 'blocks/' );
// x-release-please-start-version.
define( 'PSST_VERSION', '2.2.0' );
// x-release-please-end.

/*
 * The Composer autoloader at file scope, deliberately. Besides the plugin's own
 * classes it loads Action Scheduler through composer.json's `autoload.files`
 * entry, and Action Scheduler registers itself on `plugins_loaded` — so it has to
 * be required before that hook fires, which is now.
 */
if ( file_exists( PSST_PATH . 'vendor/autoload.php' ) ) {
	require_once PSST_PATH . 'vendor/autoload.php';
}

add_action( 'plugins_loaded', 'psst_init' );

/**
 * Boot the plugin.
 *
 * @return void
 */
function psst_init(): void {
	if ( ! class_exists( \Linchpin\Psst\Core\Bootstrap::class ) ) {
		add_action( 'admin_notices', 'psst_missing_autoloader_notice' );
		return;
	}

	do_action( 'before_psst_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Kept from 1.x for compatibility.

	( new \Linchpin\Psst\Core\Bootstrap() )->run();

	do_action( 'after_psst_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Kept from 1.x for compatibility.
}

/**
 * Tell an administrator why Psst did not boot.
 *
 * @return void
 */
function psst_missing_autoloader_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Psst could not load its dependencies. Run composer install inside the plugin directory, or install a release build.', 'psst' )
	);
}

register_activation_hook( __FILE__, [ \Linchpin\Psst\Controller\Install::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Linchpin\Psst\Controller\Install::class, 'deactivate' ] );
