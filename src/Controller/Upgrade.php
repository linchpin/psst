<?php
/**
 * Upgrade Controller
 *
 * @package Psst\Controller
 */

namespace Psst\Controller;

use \Psst\Model\Config;

/**
 * Class Upgrade
 */
class Upgrade {

	/**
	 * Registers hooks and filters
	 *
	 * @since 1.0
	 */
	public function register_actions() {
		add_action( 'admin_init', array( $this, 'upgrade' ), 999 );
		add_action( 'admin_notices', array( $this, 'show_review_nag' ), 11 );
	}

	/**
	 * Check and schedule plugin upgrading if necessary.
	 *
	 * @since 1.0
	 */
	public function upgrade() {
		$current_version = get_option( 'psst_version', '0.0.0' );

		if ( version_compare( '1.0.0', $current_version, '>' ) ) {
			$current_version = PSST_VERSION;
			update_option( 'psst_version', $current_version );
		}
	}

	/**
	 * Show a nag to the user to review Psst!
	 * Because it's awesome! -Patrick Swayze
	 *
	 * @since 1.0
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function show_review_nag() {
		$psst_settings = get_option( 'psst_options' );
		$notifications = get_user_option( 'psst_notifications' );

		// If we don't have a date die early.
		if ( ! isset( $psst_settings['first_activated_on'] ) || '' === $psst_settings['first_activated_on'] ) {
			return '';
		}

		$now          = new \DateTime();
		$install_date = new \DateTime();
		$install_date->setTimestamp( $psst_settings['first_activated_on'] );

		if ( $install_date->diff( $now )->days < 30 ) {
			return '';
		}

		if ( false !== $psst_settings && ( ! empty( $notifications['update-notice'] ) && empty( $notifications['review-notice'] ) ) ) {
			include PSST_PATH . 'templates/admin/review-notice.php';
		}

		return '';
	}
}

