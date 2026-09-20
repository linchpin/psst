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
	 * The sender's management token, from the header or the body.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return string
	 */
	protected function presented_token( \WP_REST_Request $request ): string {
		$header = $request->get_header( 'X-Psst-Manage-Token' );

		if ( is_string( $header ) && '' !== $header ) {
			return sanitize_text_field( $header );
		}

		$body = $request->get_param( 'token' );

		return is_string( $body ) ? $body : '';
	}

	/**
	 * Count a hit against a per-IP (or global) hourly limit.
	 *
	 * @param string      $scope      Scope.
	 * @param int         $limit      Hits per hour; zero disables.
	 * @param string|null $identifier Override the identifier, e.g. 'all' for a global bucket.
	 *
	 * @return \WP_Error|null
	 */
	protected function limit( string $scope, int $limit, ?string $identifier = null ): ?\WP_Error {
		/**
		 * Filters a rate limit before it is applied.
		 *
		 * @param int    $limit Hits per hour.
		 * @param string $scope The scope.
		 */
		$limit = (int) apply_filters( 'psst_rate_limit', $limit, $scope );

		if ( $limit <= 0 ) {
			return null;
		}

		$identifier = $identifier ?? $this->client_ip();

		if ( '' === $identifier ) {
			return null;
		}

		$retry_after = \Linchpin\Psst\Helper\Rate_Limiter::with_transients()->hit( $scope, $identifier, $limit );

		if ( 0 === $retry_after ) {
			return null;
		}

		return new \WP_Error(
			'psst_rate_limited',
			__( 'Too many requests. Try again later.', 'psst' ),
			[
				'status'      => 429,
				'retry_after' => $retry_after,
			]
		);
	}

	/**
	 * The client IP, honoring the configured trusted proxy.
	 *
	 * @return string
	 */
	protected function client_ip(): string {
		$ip = \Linchpin\Psst\Helper\Request_Context::client_ip( (string) \Linchpin\Psst\Model\Settings::get( 'trusted_proxy_header' ) );

		/**
		 * Filters the client IP used for rate limiting.
		 *
		 * @param string $ip The resolved address.
		 */
		return (string) apply_filters( 'psst_client_ip', $ip );
	}

	/**
	 * The one error for every kind of absence.
	 *
	 * A missing secret, an expired one and one that was never there are
	 * indistinguishable on purpose.
	 *
	 * @return \WP_Error
	 */
	protected function not_found(): \WP_Error {
		return new \WP_Error( 'psst_not_found', __( 'This secret is no longer available.', 'psst' ), [ 'status' => 404 ] );
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
