<?php

namespace Psst\Controller\Admin;

/**
 * Admin Class
 *
 * Functionality for the admin area of WordPress
 *
 * @package Psst\Controller\Admin
 */
class Admin {

	public function register_actions() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public function admin_init() {
	}

}
