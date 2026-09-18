<?php
/**
 * Plugin constants for static analysis.
 *
 * The main plugin file defines these at runtime, which PHPStan does not execute. Loaded via
 * `bootstrapFiles` in phpstan.neon and not part of the shipped plugin.
 *
 * @package Linchpin\Psst
 */

define( 'PSST_FILE', dirname( __DIR__, 2 ) . '/psst.php' );
define( 'PSST_PATH', dirname( __DIR__, 2 ) . '/' );
define( 'PSST_URL', 'https://example.test/wp-content/plugins/psst/' );
define( 'PSST_BASENAME', 'psst/psst.php' );
define( 'PSST_BLOCK_PATH', dirname( __DIR__, 2 ) . '/blocks/' );
define( 'PSST_VERSION', '0.0.0-static-analysis' );
define( 'WP_UNINSTALL_PLUGIN', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant.
