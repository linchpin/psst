<?php
/**
 * The contract every controller fulfils.
 *
 * @package Linchpin\Psst\Controller
 */

namespace Linchpin\Psst\Controller;

/**
 * Interface Controller_Interface
 */
interface Controller_Interface {

	/**
	 * Register the hooks this controller listens on.
	 *
	 * @return void
	 */
	public function register_actions(): void;
}
