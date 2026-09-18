<?php
/**
 * Identifiers and tokens.
 *
 * WordPress-free apart from apply_filters(), which the unit suite stubs.
 *
 * @package Linchpin\Psst\Helper
 */

namespace Linchpin\Psst\Helper;

/**
 * Class Ids
 */
final class Ids {

	/**
	 * The fewest bytes of entropy a public id may carry: 128 bits.
	 */
	public const MIN_ID_BYTES = 16;

	/**
	 * Bytes of entropy in a management token: 256 bits.
	 */
	public const TOKEN_BYTES = 32;

	/**
	 * A secret's public identifier, used as the post slug and in the URL.
	 *
	 * @return string base64url, 22 characters at the default entropy.
	 */
	public static function public_id(): string {
		/**
		 * Filters the bytes of entropy in a public id.
		 *
		 * Kept from 1.x, where it meant characters. It now means bytes of random
		 * data and has a floor of 16 (128 bits).
		 *
		 * @param int $bytes Bytes of entropy.
		 */
		$bytes = (int) apply_filters( 'psst_slug_length', self::MIN_ID_BYTES );

		return Base64Url::encode( random_bytes( max( self::MIN_ID_BYTES, $bytes ) ) );
	}

	/**
	 * The sender's management token. Returned to the sender exactly once;
	 * only its hash is stored.
	 *
	 * @return string base64url, 43 characters.
	 */
	public static function manage_token(): string {
		return Base64Url::encode( random_bytes( self::TOKEN_BYTES ) );
	}

	/**
	 * The stored form of a management token.
	 *
	 * @param string $token The usable token.
	 *
	 * @return string 64 hex characters.
	 */
	public static function hash_token( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * Whether a presented token matches a stored hash.
	 *
	 * @param string $stored_hash The hash on record.
	 * @param mixed  $token       Whatever the caller presented.
	 *
	 * @return bool
	 */
	public static function token_matches( string $stored_hash, mixed $token ): bool {
		if ( ! is_string( $token ) || '' === $token || '' === $stored_hash ) {
			return false;
		}

		return hash_equals( $stored_hash, self::hash_token( $token ) );
	}

	/**
	 * The regular expression a public id must satisfy, without delimiters.
	 *
	 * @return string
	 */
	public static function id_pattern(): string {
		return '[A-Za-z0-9_-]{22,64}';
	}

	/**
	 * Whether a string is shaped like a public id.
	 *
	 * @param mixed $id Candidate.
	 *
	 * @return bool
	 */
	public static function is_public_id( mixed $id ): bool {
		return is_string( $id ) && 1 === preg_match( '/^' . self::id_pattern() . '$/', $id );
	}
}
