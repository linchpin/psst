<?php
/**
 * The admin page that hosts the React app.
 *
 * @package Linchpin\Psst\Controller\Admin
 */

namespace Linchpin\Psst\Controller\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Controller_Interface;
use Linchpin\Psst\Helper\Assets;
use Linchpin\Psst\Model\Settings;

/**
 * Class Admin_Page
 */
class Admin_Page implements Controller_Interface {

	public const SLUG = 'psst';

	/**
	 * The hook suffix add_submenu_page returned.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'plugin_action_links_' . PSST_BASENAME, [ $this, 'action_links' ] );
	}

	/**
	 * Under Mantle when it is installed, otherwise under Settings. The same
	 * arrangement as linchpin-blocks.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$parent = class_exists( 'Mantle' ) ? 'mantle' : 'options-general.php';

		$hook = add_submenu_page(
			$parent,
			__( 'Psst', 'psst' ),
			__( 'Psst', 'psst' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);

		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * The mount point.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<div id="psst-admin" class="psst-admin"></div>';
	}

	/**
	 * Load the app on its own page only.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue( $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		$asset = Assets::read( PSST_PATH . 'build/admin.asset.php' );

		if ( null === $asset ) {
			return;
		}

		wp_enqueue_script(
			'psst-admin',
			PSST_URL . 'build/admin.js',
			array_merge( $asset['dependencies'], [ 'wp-api-fetch' ] ),
			$asset['version'],
			true
		);

		wp_set_script_translations( 'psst-admin', 'psst', PSST_PATH . 'languages' );

		if ( file_exists( PSST_PATH . 'build/admin.css' ) ) {
			wp_enqueue_style(
				'psst-admin',
				PSST_URL . 'build/admin.css',
				[ 'wp-components' ],
				$asset['version']
			);
		}

		wp_add_inline_script(
			'psst-admin',
			'window.psstAdmin = ' . wp_json_encode(
				[
					'restUrl'   => esc_url_raw( rest_url( 'psst/v1/' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'version'   => PSST_VERSION,
					'createUrl' => Settings::get_create_url(),
					'siteUrl'   => home_url( '/' ),
					'adminUrl'  => admin_url(),
				]
			) . ';',
			'before'
		);
	}

	/**
	 * A Settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function action_links( $links ): array {
		$parent = class_exists( 'Mantle' ) ? 'admin.php' : 'options-general.php';

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( $parent . '?page=' . self::SLUG ) ),
			esc_html__( 'Settings', 'psst' )
		);

		return $links;
	}
}
