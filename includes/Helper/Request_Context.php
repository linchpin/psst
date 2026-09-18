<?php
/**
 * Who is calling.
 *
 * WordPress-free: the value is accepted only when FILTER_VALIDATE_IP says it is
 * an IP address. Modelled on mantle's helper of the same name.
 *
 * @package Linchpin\Psst\Helper
 */

namespace Linchpin\Psst\Helper;

/**
 * Class Request_Context
 */
final class Request_Context {

	/**
	 * The `$_SERVER` key each supported proxy puts the real client IP in.
	 *
	 * @var array<string, string>
	 */
	private const PROVIDER_HEADERS = [
		'cloudflare' => 'HTTP_CF_CONNECTING_IP',
	];

	/**
	 * Resolve the client IP address.
	 *
	 * A provider header is consulted only when that provider is configured;
	 * trusting a forwarding header unconditionally would let any caller name
	 * their own address and walk around the rate limit.
	 *
	 * @param string $provider The configured trusted proxy, if any.
	 *
	 * @return string A validated IP address, or '' when none could be resolved.
	 */
	public static function client_ip( string $provider = '' ): string {
		$keys = [];

		if ( isset( self::PROVIDER_HEADERS[ $provider ] ) ) {
			$keys[] = self::PROVIDER_HEADERS[ $provider ];
		}

		$keys[] = 'REMOTE_ADDR';

		foreach ( $keys as $key ) {
			$candidate = self::server_ip( $key );

			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Read one `$_SERVER` key and return it only if it is an IP address.
	 *
	 * @param string $key The `$_SERVER` key.
	 *
	 * @return string
	 */
	private static function server_ip( string $key ): string {
		if ( ! isset( $_SERVER[ $key ] ) || ! is_string( $_SERVER[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,Linchpin.Security.ValidatedSanitizedInput.InputNotSanitized,Linchpin.Security.ValidatedSanitizedInput.MissingUnslash -- stripslashes() is wp_unslash() for a string, and FILTER_VALIDATE_IP below is stricter than any sanitizer.
		$value = stripslashes( $_SERVER[ $key ] );

		return false !== filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
	}
}
