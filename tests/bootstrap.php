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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Strip tags and collapse whitespace, as core does.
	 *
	 * @param string $str Value.
	 *
	 * @return string
	 */
	function sanitize_text_field( string $str ): string {
		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( $str ) ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Remove tags, and the content of script and style elements.
	 *
	 * @param string $text Value.
	 *
	 * @return string
	 */
	function wp_strip_all_tags( string $text ): string {
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );

		return trim( strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- The stub stands in for the function that wraps this.
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * Whether a string looks like an address.
	 *
	 * @param string $email Candidate.
	 *
	 * @return string|false The address, or false.
	 */
	function is_email( string $email ): string|false {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Normalize an address.
	 *
	 * @param string $email Value.
	 *
	 * @return string
	 */
	function sanitize_email( string $email ): string {
		return (string) filter_var( trim( $email ), FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Slug-ify.
	 *
	 * @param string $title Value.
	 *
	 * @return string
	 */
	function sanitize_title( string $title ): string {
		return trim( (string) preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( $title ) ), '-' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Parse a URL, with core's component default.
	 *
	 * @param string $url       URL.
	 * @param int    $component Component.
	 *
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- The stub is what it is standing in for.
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * The site address.
	 *
	 * @param string $path Path to append.
	 *
	 * @return string
	 */
	function home_url( string $path = '' ): string {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * A site property.
	 *
	 * @param string $show Which property.
	 *
	 * @return string
	 */
	function get_bloginfo( string $show = '' ): string {
		return 'name' === $show ? 'Example Site' : '';
	}
}

if ( ! function_exists( 'wp_specialchars_decode' ) ) {
	/**
	 * Decode entities.
	 *
	 * @param string $content Value.
	 * @param int    $quote_style Unused.
	 *
	 * @return string
	 */
	function wp_specialchars_decode( string $content, int $quote_style = ENT_NOQUOTES ): string {
		return html_entity_decode( $content, ENT_QUOTES );
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
