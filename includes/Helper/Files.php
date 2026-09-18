<?php
/**
 * File helpers.
 *
 * @package Linchpin\Psst\Helper
 */

namespace Linchpin\Psst\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Files
 */
final class Files {

	/**
	 * A glob() that descends into subdirectories.
	 *
	 * @param string $pattern Glob pattern.
	 * @param int    $flags   glob() flags.
	 *
	 * @return string[]
	 */
	public static function glob_recursive( string $pattern, int $flags = 0 ): array {
		$files = (array) glob( $pattern, $flags );

		foreach ( (array) glob( dirname( $pattern ) . '/*', GLOB_ONLYDIR | GLOB_NOSORT ) as $dir ) {
			$files = array_merge( $files, self::glob_recursive( $dir . '/' . basename( $pattern ), $flags ) );
		}

		return array_values( array_filter( $files, 'is_string' ) );
	}
}
