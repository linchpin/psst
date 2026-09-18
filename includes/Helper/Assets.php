<?php
/**
 * Read a wp-scripts asset manifest.
 *
 * @package Linchpin\Psst\Helper
 */

namespace Linchpin\Psst\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
final class Assets {

	/**
	 * The dependencies and version a `*.asset.php` file declares.
	 *
	 * @param string $file Absolute path to the asset file.
	 *
	 * @return array{dependencies: string[], version: string}|null Null when the build is missing.
	 */
	public static function read( string $file ): ?array {
		if ( ! is_readable( $file ) ) {
			return null;
		}

		$asset = include $file;

		if ( ! is_array( $asset ) ) {
			return null;
		}

		return [
			'dependencies' => array_values( array_filter( (array) ( $asset['dependencies'] ?? [] ), 'is_string' ) ),
			'version'      => (string) ( $asset['version'] ?? PSST_VERSION ),
		];
	}
}
