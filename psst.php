<?php
/*
 * Plugin Name: psst (Pretty Secure Secret Transmission)
 * Description: As simple plugin to send a relatively secure message to an individuals.
 * Plugin URI:  https://wordpress.org/plugins/courier
 * Version: 1.0.0
 * License: GPL-2.0+
 * Author URI: https://linchpin.com
 * Text Domain: psst
 * Author:      Linchpin
 * Author URI:  http://linchpin.com
 * Domain Path: \languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Globals
 */
// Define the main plugin file to make it easy to reference in subdirectories
if ( ! defined( 'PSST_FILE' ) ) {
	define( 'PSST_FILE', __FILE__ );
}

if ( ! defined( 'PSST_PATH' ) ) {
	define( 'PSST_PATH', trailingslashit( __DIR__ ) );
}

if ( ! defined( 'PSST_URL' ) ) {
	define( 'PSST_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
}

if ( ! defined( 'PSST_VERSION' ) ) {
	define( 'PSST_VERSION', '1.0.0' );
}

/**
 * Include Libraries
 */
require_once PSST_PATH . 'lib/cmb2/init.php';

/**
 * Autoload Classes
 */
// Include composer
require PSST_PATH . 'vendor/autoload.php';

include PSST_PATH . 'src/Core/Psr4Autoloader.php';
$loader = new \Psst\Core\Psr4Autoloader();
$loader->addNamespace( 'Psst', dirname( __FILE__ ) . '/src' );
$loader->register();

/***
 * Kick everything off when plugins are loaded
 */
add_action( 'plugins_loaded', 'psst_init' );

/**
 * Callback for starting the plugin.
 *
 * @wp-hook plugins_loaded
 *
 * @return void
 */
function psst_init() {
	do_action( 'before_psst_init' );

	$psst = new \Psst\Core\Bootstrap();

	try {
		$psst->run();
	} catch ( Exception $e ) {
		wp_die( print_r( $e, true ) );
	}

	do_action( 'after_psst_init' );
}

register_activation_hook( __FILE__, 'psst_activation' );

/**
 * Setup Crons to purge, expire and cleanup secrets upon plugin activation.
 */
function psst_activation() {
	// Create our crons.
	wp_schedule_event( current_time( 'timestamp' ), '5min', 'secret_expire' );
	wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'secret_purge' );
	wp_schedule_event( current_time( 'timestamp' ), 'daily', 'secret_cleanup' );

	if ( ! get_option( 'psst_flush_rewrite_rules' ) ) {
		add_option( 'psst_flush_rewrite_rules', true );
	}
}

register_deactivation_hook( __FILE__, 'psst_deactivation' );

/**
 * Clear hooks to clean up secrets
 * @todo this should also clear out all data from the DB if the user requests to delete all information
 *       upon uninstall.
 */
function psst_deactivation() {
	wp_clear_scheduled_hook( 'secret_purge' );
	wp_clear_scheduled_hook( 'secret_expire' );
	wp_clear_scheduled_hook( 'secret_cleanup' );
}

add_action( 'init', 'psst_flush_rewrite_rules', 20 );

/**
 * Flush rewrite rules if the previously added flag exists,
 * and then remove the flag.
 */
function psst_flush_rewrite_rules() {
	if ( get_option( 'psst_flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
		delete_option( 'psst_flush_rewrite_rules' );
	}
}
