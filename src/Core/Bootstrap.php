<?php

namespace Psst\Core;

use \Psst\Model\Config;
use \Psst\Helper\Files;

class Bootstrap {

	private $config      = array();
	private $controllers = array();

	public function __construct() {
		$this->config = new Config();
	}

	public function run() {
		// Load the textdomain
		$this->load_textdomain();

		// Load the controllers in the Controller directory
		$this->load_controllers();

		// Register actions for each controller
		$this->register_actions();

		// The plugin is ready!
		do_action( 'psst_ready', $this );
	}

	private function load_textdomain() {
		$textdomain_dir  = dirname( $this->config->get( 'plugin_base_name' ) );
		$textdomain_path = $textdomain_dir . $this->config->get( 'text_domain_path' );
		load_plugin_textdomain(
			$this->config->get( 'textdomain' ),
			false,
			$textdomain_path
		);
	}

	/**
	 * Loop over all php files in the Controllers directory and add them to
	 * the $controllers array
	 */
	private function load_controllers() {
		$namespace = $this->config->get( 'namespace' );

		foreach ( Files::glob_recursive( $this->config->get( 'plugin_path' ) . 'src/Controller/*.php' ) as $file ) {
			preg_match( '/\/Controller\/(.+)\.php/', $file, $matches, PREG_OFFSET_CAPTURE );
			$name                       = str_replace( '/', '\\', $matches[1][0] );
			$class                      = "\\" . $namespace . "\\Controller\\" . $name;
			$this->controllers[ $name ] = new $class;
		}
	}

	private function register_actions() {
		foreach ( $this->controllers as $name => $class ) {
			if ( method_exists( $class, 'register_actions' ) ) {
				$class->register_actions();
			}
		}
	}
}
