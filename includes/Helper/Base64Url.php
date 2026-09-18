<?php
/**
 * Base64url (RFC 4648 §5) without padding.
 *
 * WordPress-free so the unit suite covers it directly.
 *
 * @package Linchpin\Psst\Helper
 */

namespace Linchpin\Psst\Helper;

/**
 * Class Base64Url
 */
final class Base64Url {

	/**
	 * Encode bytes.
	 *
	 * @param string $bytes Raw bytes.
	 *
	 * @return string
	 */
	public static function encode( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	/**
	 * Strictly decode a base64url string.
	 *
	 * @param string   $encoded      The encoded string.
	 * @param int|null $expected_len When given, the decoded byte length must match.
	 *
	 * @return string|null Raw bytes, or null when the input is not valid base64url.
	 */
	public static function decode( string $encoded, ?int $expected_len = null ): ?string {
		if ( '' === $encoded || ! self::is_valid( $encoded ) ) {
			return null;
		}

		$padded = strtr( $encoded, '-_', '+/' );
		$padded .= str_repeat( '=', ( 4 - strlen( $padded ) % 4 ) % 4 );

		$bytes = base64_decode( $padded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding a client envelope field, not obfuscated code.

		if ( false === $bytes ) {
			return null;
		}

		if ( null !== $expected_len && strlen( $bytes ) !== $expected_len ) {
			return null;
		}

		return $bytes;
	}

	/**
	 * Whether a string uses only the base64url alphabet.
	 *
	 * @param string $encoded The string.
	 *
	 * @return bool
	 */
	public static function is_valid( string $encoded ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $encoded );
	}

	/**
	 * REST sanitize_callback: drop whitespace, leave everything else for validation.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return string
	 */
	public static function sanitize( mixed $value ): string {
		return is_string( $value ) ? (string) preg_replace( '/\s+/', '', $value ) : '';
	}

	/**
	 * The maximum encoded length for a given number of bytes.
	 *
	 * @param int $bytes Byte count.
	 *
	 * @return int
	 */
	public static function encoded_length( int $bytes ): int {
		return (int) ceil( $bytes * 4 / 3 );
	}
}
