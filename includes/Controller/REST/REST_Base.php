<?php
/**
 * Base for the plugin's REST controllers.
 *
 * @package Linchpin\Psst\Controller\REST
 */

namespace Linchpin\Psst\Controller\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Controller_Interface;

/**
 * Class REST_Base
 */
abstract class REST_Base implements Controller_Interface {

	public const NAMESPACE = 'psst/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'no_store_headers' ], 10, 3 );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * The namespace.
	 *
	 * @return string
	 */
	public function get_api_namespace(): string {
		return self::NAMESPACE;
	}

	/**
	 * Administrators only.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool|\WP_Error
	 */
	public function get_admin_permissions( \WP_REST_Request $request ): bool|\WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error(
			'psst_forbidden',
			__( 'You are not allowed to do that.', 'psst' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * Every response from this namespace is uncacheable and unindexable.
	 *
	 * @param \WP_HTTP_Response $result  The response.
	 * @param \WP_REST_Server   $server  The server.
	 * @param \WP_REST_Request  $request The request.
	 *
	 * @return \WP_HTTP_Response
	 */
	public function no_store_headers( $result, $server, $request ) {
		if ( 0 !== strpos( (string) $request->get_route(), '/' . self::NAMESPACE ) ) {
			return $result;
		}

		$result->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$result->header( 'Pragma', 'no-cache' );
		$result->header( 'X-Robots-Tag', 'noindex, nofollow' );
		$result->header( 'Referrer-Policy', 'no-referrer' );

		return $result;
	}
}
