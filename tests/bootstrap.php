<?php
/**
 * PHPUnit bootstrap.
 *
 * Plain unit tests, no WordPress. Only the WordPress functions the code under
 * test actually calls are stubbed, and they are deliberately thin.
 *
 * @package Linchpin\Psst
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- stubs must carry WordPress's own names.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- signatures mirror WordPress.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constants, defined so the code under test can run without WordPress.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'PSST_VERSION' ) ) {
	define( 'PSST_VERSION', '0.0.0-tests' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
}

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Registered filter callbacks, keyed by hook name.
 *
 * @var array<string, callable[]>
 */
$GLOBALS['psst_test_filters'] = [];

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a filter callback.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Unused.
	 * @param int      $accepted_args Unused.
	 *
	 * @return bool
	 */
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['psst_test_filters'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Run a value through the registered callbacks for a hook.
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  $value   Value.
	 * @param mixed  ...$args Extra arguments.
	 *
	 * @return mixed
	 */
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		foreach ( $GLOBALS['psst_test_filters'][ $hook ] ?? [] as $callback ) {
			$value = $callback( $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translate.
	 *
	 * @param string $text   Text.
	 * @param string $domain Unused.
	 *
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_x' ) ) {
	/**
	 * Translate with context.
	 *
	 * @param string $text    Text.
	 * @param string $context Unused.
	 * @param string $domain  Unused.
	 *
	 * @return string
	 */
	function _x( string $text, string $context, string $domain = 'default' ): string {
		return $text;
	}
}

// phpcs:enable

/**
 * Forget every registered filter. Called from tearDown.
 *
 * @return void
 */
function psst_test_reset_filters(): void {
	$GLOBALS['psst_test_filters'] = [];
}
