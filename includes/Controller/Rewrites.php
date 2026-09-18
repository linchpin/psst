<?php
/**
 * The /s/{id} route.
 *
 * Page-based, the WooCommerce-endpoint shape: the rule points at the page that
 * holds the viewer block, so the theme renders it like any page and the block
 * reads the id from a query var.
 *
 * @package Linchpin\Psst\Controller
 */

namespace Linchpin\Psst\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Helper\Ids;
use Linchpin\Psst\Model\Route;
use Linchpin\Psst\Model\Settings;

/**
 * Class Rewrites
 */
class Rewrites implements Controller_Interface {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'init', [ $this, 'add_rules' ] );
		add_action( 'init', [ $this, 'maybe_flush' ], 20 );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_filter( 'redirect_canonical', [ $this, 'no_canonical_redirect' ] );
		add_action( 'template_redirect', [ $this, 'status_headers' ], 1 );
	}

	/**
	 * The URL segment secrets live under.
	 *
	 * @return string
	 */
	public static function base(): string {
		/**
		 * Filters the URL segment secret links use. Defaults to `s`.
		 *
		 * @param string $base The segment.
		 */
		$base = sanitize_title( (string) apply_filters( 'psst_url_base', 's' ) );

		return '' !== $base ? $base : 's';
	}

	/**
	 * Rewrite rules.
	 *
	 * Ids are 22 to 64 base64url characters and there is no literal segment
	 * after the base, so nothing can shadow anything. Old 1.x links under
	 * /secret/ resolve to the viewer in its legacy state.
	 *
	 * @return void
	 */
	public function add_rules(): void {
		$page_id = Settings::reveal_page_id();

		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return;
		}

		$base = self::base();

		add_rewrite_rule(
			'^' . preg_quote( $base, '#' ) . '/(' . Ids::id_pattern() . ')/?$',
			'index.php?page_id=' . $page_id . '&' . Route::QUERY_ID . '=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^secret/.*$',
			'index.php?page_id=' . $page_id . '&' . Route::QUERY_VIEW . '=legacy',
			'top'
		);
	}

	/**
	 * Register the query vars.
	 *
	 * @param string[] $vars Query vars.
	 *
	 * @return string[]
	 */
	public function query_vars( $vars ): array {
		$vars[] = Route::QUERY_ID;
		$vars[] = Route::QUERY_VIEW;

		return $vars;
	}

	/**
	 * Core would otherwise redirect /s/{id} to the page's own permalink.
	 *
	 * @param string|false $redirect The redirect URL.
	 *
	 * @return string|false
	 */
	public function no_canonical_redirect( $redirect ) {
		return null !== Route::current() ? false : $redirect;
	}

	/**
	 * Flush when flagged: activation and page changes set the flag.
	 *
	 * @return void
	 */
	public function maybe_flush(): void {
		if ( get_option( Settings::OPTION_FLUSH ) ) {
			delete_option( Settings::OPTION_FLUSH );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * A legacy link is Gone. The viewer block sets 404 for a missing id itself,
	 * because only it knows whether the id exists.
	 *
	 * @return void
	 */
	public function status_headers(): void {
		$route = Route::current();

		if ( $route && 'legacy' === $route['view'] ) {
			status_header( 410 );
		}
	}
}
