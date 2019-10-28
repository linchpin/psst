<?php

namespace Psst\Controller;

/**
 * Class Welcome
 * @package Psst\Controller
 */
class Welcome {

	/**
	 * Register our actions for where notifications will be placed.
	 */
	public function register_actions() {
		add_action( 'wp_ajax_psst_update_welcome_panel', array( $this, 'update_welcome_panel' ) );
		add_action( 'admin_init', array( $this, 'show_welcome' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Close our welcome panel if the user doesn't want to see it anymore.
	 *
	 * @since 1.0
	 */
	public function update_welcome_panel() {

		check_ajax_referer( 'psst_welcome_panel_nonce', 'psst_welcome_panel' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( - 1 );
		}

		update_user_meta( get_current_user_id(), 'show_psst_welcome_panel', empty( $_POST['visible'] ) ? 0 : 1 ); // input var okay, WPCS slow query ok.

		wp_die( 1 );
	}

	/**
	 * Add our welcome area to courier notices post list
	 *
	 * @since 1.0
	 */
	public function admin_notices() {

		$screen = get_current_screen();

		if ( 'edit-psst' === $screen->id ) {
			add_action( 'all_admin_notices', array( $this, 'welcome_message' ) );
		}
	}

	/**
	 * Output our welcome markup
	 */
	public function welcome_message() {
		include_once PSST_PATH . 'templates/admin/welcome.php';
	}

	/**
	 * On first install show new users a welcome screen.
	 * Only show the message when installing an individual message.
	 *
	 * @since 1.0
	 */
	public function show_welcome() {

		if ( is_admin() && 1 === intval( get_option( 'psst_activation' ) ) ) {

			delete_option( 'psst_activation' );

			$psst_count = wp_count_posts( 'psst' );

			// Send new users to the welcome so they learn how to use Courier.
			if ( ! isset( $_GET['activate-multi'] ) && 0 === $psst_count ) { // WPCS: CSRF ok, input var okay.
				wp_safe_redirect( admin_url( 'options-general.php?page=psst&tab=about' ) );
				exit;
			}
		}
	}
}
