<?php
/**
 * Register the blocks, patterns and the bindings source.
 *
 * @package Linchpin\Psst\Controller
 */

namespace Linchpin\Psst\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Model\Settings;

/**
 * Class Blocks
 */
class Blocks implements Controller_Interface {

	public const BUILD_DIR = 'build';

	/**
	 * Whether the shared client config has been printed for this request.
	 *
	 * @var bool
	 */
	private static bool $config_sent = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'init', [ $this, 'register_patterns' ] );
		add_action( 'init', [ $this, 'register_bindings' ] );
		add_action( 'admin_notices', [ $this, 'missing_build_notice' ] );
	}

	/**
	 * Register every built block.
	 *
	 * Reads the *built* tree: block.json is copied there with render.php by
	 * `--webpack-copy-php`, and the manifest by `--blocks-manifest`.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$build_path = PSST_BLOCK_PATH . self::BUILD_DIR;

		if ( ! is_dir( $build_path ) ) {
			return;
		}

		$manifest = $build_path . '/blocks-manifest.php';

		if ( file_exists( $manifest ) && function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
			wp_register_block_types_from_metadata_collection( $build_path, $manifest );

			return;
		}

		foreach ( (array) glob( $build_path . '/*/block.json' ) as $block_json ) {
			register_block_type( dirname( (string) $block_json ) );
		}
	}

	/**
	 * Patterns, from patterns/*.php.
	 *
	 * @return void
	 */
	public function register_patterns(): void {
		register_block_pattern_category(
			'psst',
			[ 'label' => _x( 'Psst', 'block pattern category', 'psst' ) ]
		);

		foreach ( (array) glob( PSST_PATH . 'patterns/*.php' ) as $file ) {
			$pattern = include $file;

			if ( is_array( $pattern ) && isset( $pattern['slug'] ) && isset( $pattern['content'] ) ) {
				$slug = (string) $pattern['slug'];
				unset( $pattern['slug'] );
				register_block_pattern( $slug, $pattern );
			}
		}
	}

	/**
	 * A bindings source for the create page URL, so a core/button in a pattern
	 * follows the setting.
	 *
	 * @return void
	 */
	public function register_bindings(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		register_block_bindings_source(
			'psst/create-url',
			[
				'label'              => __( 'Psst create page URL', 'psst' ),
				'get_value_callback' => static fn(): string => Settings::get_create_url(),
			]
		);
	}

	/**
	 * Print the shared client config and strings once per request.
	 *
	 * Called from each block's render.php so it only reaches pages that render
	 * a block.
	 *
	 * @return void
	 */
	public static function send_client_config(): void {
		if ( self::$config_sent ) {
			return;
		}

		self::$config_sent = true;

		wp_interactivity_config( 'psst', Settings::client_config() );

		wp_interactivity_state(
			'psst',
			[
				'i18n' => [
					'required'         => __( 'Enter a secret message.', 'psst' ),
					'tooLong'          => __( 'Your secret is too long.', 'psst' ),
					'network'          => __( 'Something went wrong. Please try again.', 'psst' ),
					'rateLimited'      => __( 'Too many requests. Please try again later.', 'psst' ),
					'copy'             => __( 'Copy to clipboard', 'psst' ),
					'copied'           => __( 'Copied', 'psst' ),
					'copySecret'       => __( 'Copy secret', 'psst' ),
					'creating'         => __( 'Encrypting…', 'psst' ),
					'create'           => __( 'Create Secret Link', 'psst' ),
					'revealing'        => __( 'Decrypting…', 'psst' ),
					'view'             => __( 'View Secret', 'psst' ),
					'wrongPassphrase'  => __( 'That pass phrase did not unlock the secret. Try again.', 'psst' ),
					'corrupted'        => __( 'This secret could not be decrypted. The link may have been altered.', 'psst' ),
					'missingKey'       => __( 'This link is missing its key. Ask the sender to send the full link again.', 'psst' ),
					'gone'             => __( 'This secret is no longer available.', 'psst' ),
					'unsupported'      => __( 'Your browser does not support the encryption this page needs.', 'psst' ),
					'passphraseNeeded' => __( 'Enter the pass phrase to continue.', 'psst' ),
				],
			]
		);
	}

	/**
	 * A checkout without a build registers nothing, silently. Say so.
	 *
	 * @return void
	 */
	public function missing_build_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) || is_dir( PSST_BLOCK_PATH . self::BUILD_DIR ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Psst: the blocks have not been built. Run npm run build:all inside the plugin directory, or install a release build.', 'psst' )
		);
	}
}
