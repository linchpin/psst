<?php
namespace Psst\Controller;

use \Psst\Model\Config;

/**
 * Class Install
 * @package Psst\Controller
 */
class Install {

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * Install constructor.
	 *
	 */
	public function __construct() {
		$this->config = new Config();
	}

	/**
	 * Register Actions
	 */
	public function register_actions() {
		add_action( 'admin_notices', array( $this, 'check_for_updates' ) );
	}

	/**
	 * Check to see if we have any updates
	 */
	public function check_for_updates() {
		$plugin_options  = get_option( 'psst_options', array() );
		$current_version = isset( $plugin_options['plugin_version'] ) ? $plugin_options['plugin_version'] : '0';

		// If your version is different than this version, run install
		if ( version_compare( $current_version, $this->config->get( 'version' ), '!=' ) ) {
			$this->install( $current_version );
		}
	}

	/**
	 * Install our default options and version numbber
	 * @param $current_version
	 */
	public function install( $current_version ) {
		$plugin_options   = get_option( 'psst_options', array() );

		// Example
		// if ( version_compare( $current_version, '1.0.5', '<' ) ) {
		// }

		// Keep the plugin version up to date
		$plugin_options['plugin_version'] = $this->config->get( 'version' );

		update_option( 'psst_options', $plugin_options );
	}
}

