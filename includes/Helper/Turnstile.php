<?php
/**
 * Cloudflare Turnstile verification.
 *
 * Attached to the `psst_create_challenge` filter only when a site key and a
 * secret are both configured.
 *
 * @package Linchpin\Psst\Helper
 */

namespace Linchpin\Psst\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Model\Settings;

/**
 * Class Turnstile
 */
final class Turnstile {

	// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Cloudflare's verification endpoint; an opt-in third-party service disclosed in readme.txt.
	private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Hook the check up when configured.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'psst_create_challenge', [ self::class, 'challenge' ], 10, 2 );
	}

	/**
	 * The filter callback.
	 *
	 * @param true|\WP_Error   $result  The running result.
	 * @param \WP_REST_Request $request The create request.
	 *
	 * @return true|\WP_Error
	 */
	public static function challenge( $result, \WP_REST_Request $request ) {
		if ( is_wp_error( $result ) || ! Settings::turnstile_enabled() ) {
			return $result;
		}

		$token = $request->get_param( 'challenge' );

		return self::verify( is_string( $token ) ? $token : '', Request_Context::client_ip( (string) Settings::get( 'trusted_proxy_header' ) ) );
	}

	/**
	 * Verify a token with Cloudflare. Fails closed.
	 *
	 * @param string $token The client token.
	 * @param string $ip    The client IP, if known.
	 *
	 * @return true|\WP_Error
	 */
	public static function verify( string $token, string $ip = '' ): true|\WP_Error {
		$failed = new \WP_Error( 'psst_challenge_failed', __( 'The anti-abuse challenge could not be verified. Reload and try again.', 'psst' ), [ 'status' => 403 ] );

		if ( '' === $token ) {
			return $failed;
		}

		$body = [
			'secret'   => Settings::turnstile_secret(),
			'response' => $token,
		];

		if ( '' !== $ip ) {
			$body['remoteip'] = $ip;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout' => 5,
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $failed;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) && true === ( $data['success'] ?? false ) ? true : $failed;
	}
}
