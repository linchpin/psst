<?php
/**
 * Headers and head cleanup on the create and reveal pages.
 *
 * @package Linchpin\Psst\Controller
 */

namespace Linchpin\Psst\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Model\Post_Type\Secret;
use Linchpin\Psst\Model\Route;
use Linchpin\Psst\Model\Settings;

/**
 * Class Hardening
 */
class Hardening implements Controller_Interface {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'template_redirect', [ $this, 'harden_request' ], 0 );
		add_filter( 'wp_sitemaps_post_types', [ $this, 'no_sitemap_post_type' ] );
		add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'no_sitemap_reveal_page' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'no_front_end_queries' ] );
	}

	/**
	 * On a secret or account page: no caching, no indexing, no referrers, no
	 * framing, and none of the head links that hand the URL to a third party.
	 *
	 * @return void
	 */
	public function harden_request(): void {
		if ( ! Route::is_secret_page() && ! Route::is_account_page() ) {
			return;
		}

		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Content-Type-Options: nosniff' );

		/**
		 * Filters the X-Frame-Options value sent on secret pages. Empty disables it.
		 *
		 * @param string $value Defaults to DENY.
		 */
		$frame = (string) apply_filters( 'psst_frame_options', 'DENY' );

		if ( '' !== $frame ) {
			header( 'X-Frame-Options: ' . $frame );
		}

		/**
		 * Filters an opt-in Content-Security-Policy for secret pages.
		 *
		 * Empty by default: block themes emit inline styles and Interactivity
		 * API state, so a strict default would break themes. A reasonable value
		 * is documented in the README.
		 *
		 * @param string $policy The header value.
		 */
		$csp = (string) apply_filters( 'psst_content_security_policy', '' );

		if ( '' !== $csp ) {
			header( 'Content-Security-Policy: ' . $csp );
		}

		/**
		 * Filters whether page caches are told to skip secret pages.
		 *
		 * @param bool $disable Defaults to true.
		 */
		if ( apply_filters( 'psst_disable_page_cache', true ) && ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The constant page caches read.
		}

		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_action( 'wp_head', [ $this, 'referrer_meta' ], 1 );

		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	}

	/**
	 * The meta twin of the Referrer-Policy header.
	 *
	 * @return void
	 */
	public function referrer_meta(): void {
		echo '<meta name="referrer" content="no-referrer">' . "\n";
	}

	/**
	 * Belt: the post type is already non-viewable, so it is not in sitemaps.
	 *
	 * @param array<string, \WP_Post_Type> $post_types Sitemap post types.
	 *
	 * @return array<string, \WP_Post_Type>
	 */
	public function no_sitemap_post_type( $post_types ): array {
		unset( $post_types[ Secret::POST_TYPE ] );

		return $post_types;
	}

	/**
	 * Keep the pages that are not content out of the sitemap.
	 *
	 * The empty /s/ page has nothing on it until a secret id is in the URL, and
	 * a sign-in, registration or account page is plumbing rather than something
	 * a search engine should be sending people to.
	 *
	 * @param array<string, mixed> $args      Query args.
	 * @param string               $post_type Post type.
	 *
	 * @return array<string, mixed>
	 */
	public function no_sitemap_reveal_page( $args, $post_type ): array {
		if ( 'page' !== $post_type ) {
			return $args;
		}

		$exclude = [ Settings::reveal_page_id() ];

		if ( Settings::accounts_enabled() ) {
			foreach ( [ 'login_page_id', 'register_page_id', 'account_page_id' ] as $key ) {
				$exclude[] = (int) Settings::get( $key );
			}
		}

		$exclude = array_filter( $exclude, static fn( int $page_id ): bool => $page_id > 0 );

		if ( empty( $exclude ) ) {
			return $args;
		}

		$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? [] ), $exclude );

		return $args;
	}

	/**
	 * A front-end query for the post type is always a 404.
	 *
	 * @param \WP_Query $query The query.
	 *
	 * @return void
	 */
	public function no_front_end_queries( $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$types = (array) $query->get( 'post_type' );

		if ( in_array( Secret::POST_TYPE, $types, true ) ) {
			$query->set_404();
		}
	}
}
