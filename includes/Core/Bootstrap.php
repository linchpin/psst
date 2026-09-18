<?php
/**
 * Bootstrap
 *
 * Instantiates every controller under includes/Controller and wires its hooks.
 *
 * @package Linchpin\Psst\Core
 */

namespace Linchpin\Psst\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Controller_Interface;
use Linchpin\Psst\Helper\Files;

/**
 * Class Bootstrap
 */
class Bootstrap {

	/**
	 * Controller instances, keyed by class name relative to the Controller namespace.
	 *
	 * @var array<string, Controller_Interface>
	 */
	private array $controllers = [];

	/**
	 * Start the plugin. Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->load_controllers();

		foreach ( $this->controllers as $controller ) {
			$controller->register_actions();
		}

		/**
		 * Fires once every controller has registered its hooks.
		 *
		 * @param Bootstrap $bootstrap The bootstrap instance.
		 */
		do_action( 'psst_ready', $this );
	}

	/**
	 * Instantiate every concrete controller class.
	 *
	 * Only classes implementing Controller_Interface are kept: the directory also
	 * holds the interface, the abstract REST base and value objects.
	 *
	 * @return void
	 */
	private function load_controllers(): void {
		foreach ( Files::glob_recursive( PSST_PATH . 'includes/Controller/*.php' ) as $file ) {
			if ( ! preg_match( '#/Controller/(.+)\.php$#', $file, $matches ) ) {
				continue;
			}

			$name  = str_replace( '/', '\\', $matches[1] );
			$class = '\\Linchpin\\Psst\\Controller\\' . $name;

			if ( ! class_exists( $class ) ) {
				continue;
			}

			$reflection = new \ReflectionClass( $class );

			if ( $reflection->isAbstract() || $reflection->isInterface() || ! $reflection->implementsInterface( Controller_Interface::class ) ) {
				continue;
			}

			$this->controllers[ $name ] = new $class();
		}
	}

	/**
	 * A loaded controller by name, e.g. `REST\Secrets`.
	 *
	 * @param string $name Class name relative to the Controller namespace.
	 *
	 * @return Controller_Interface|null
	 */
	public function get_controller( string $name ): ?Controller_Interface {
		return $this->controllers[ $name ] ?? null;
	}
}
