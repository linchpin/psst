<?php
/**
 * The ciphertext envelope.
 *
 * The client encrypts; the server only checks the shape and stores the result.
 * Nothing here can decrypt anything, and nothing here should ever try.
 *
 * WordPress-free apart from apply_filters() and __(), which the unit suite stubs.
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

use Linchpin\Psst\Helper\Base64Url;

/**
 * Class Envelope
 */
final class Envelope {

	public const VERSION = 2;

	public const ALGORITHM = 'A256GCM';

	public const IV_BYTES = 12;

	public const SALT_BYTES = 16;

	public const CHECK_BYTES = 16;

	/**
	 * AES-GCM's authentication tag, appended to the ciphertext by WebCrypto.
	 */
	public const TAG_BYTES = 16;

	public const MIN_KDF_ITERATIONS = 600000;

	public const MAX_KDF_ITERATIONS = 5000000;

	/**
	 * Field names that must never appear in a create request. A client that sends
	 * one has been built wrong, and rejecting it keeps the value out of logs.
	 */
	public const FORBIDDEN_FIELDS = [ 'key', 'passphrase', 'secret', 'plaintext', 'message' ];

	/**
	 * Constructor.
	 *
	 * @param string      $ciphertext     base64url ciphertext including the GCM tag.
	 * @param string      $iv             base64url, 12 bytes.
	 * @param bool        $has_passphrase Whether a passphrase was folded into the key.
	 * @param string|null $salt           base64url, 16 bytes; only with a passphrase.
	 * @param int|null    $iterations     PBKDF2 iterations; only with a passphrase.
	 * @param string      $check          base64url, 16 bytes.
	 */
	private function __construct(
		public readonly string $ciphertext,
		public readonly string $iv,
		public readonly bool $has_passphrase,
		public readonly ?string $salt,
		public readonly ?int $iterations,
		public readonly string $check,
	) {}

	/**
	 * Validate a create request body and build the envelope from it.
	 *
	 * @param array<string, mixed> $input               The decoded request fields.
	 * @param int                  $max_plaintext_bytes The configured plaintext cap.
	 *
	 * @return Envelope|Validation_Error
	 */
	public static function from_request( array $input, int $max_plaintext_bytes ): Envelope|Validation_Error {
		foreach ( self::FORBIDDEN_FIELDS as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				return new Validation_Error( $field, __( 'The request carries a field the server must never receive.', 'psst' ), 'psst_unexpected_field' );
			}
		}

		if ( ! isset( $input['version'] ) || self::VERSION !== (int) $input['version'] || ! is_numeric( $input['version'] ) ) {
			return new Validation_Error( 'version', __( 'Unsupported envelope version.', 'psst' ) );
		}

		$has_passphrase = self::to_bool( $input['has_passphrase'] ?? null );

		if ( null === $has_passphrase ) {
			return new Validation_Error( 'has_passphrase', __( 'has_passphrase must be a boolean.', 'psst' ) );
		}

		$ciphertext = self::field_string( $input, 'ciphertext' );

		if ( null === $ciphertext ) {
			return new Validation_Error( 'ciphertext', __( 'ciphertext must be base64url.', 'psst' ) );
		}

		$ciphertext_bytes = Base64Url::decode( $ciphertext );

		if ( null === $ciphertext_bytes ) {
			return new Validation_Error( 'ciphertext', __( 'ciphertext must be base64url.', 'psst' ) );
		}

		$min_bytes = self::TAG_BYTES + 1;
		$max_bytes = max( 1, $max_plaintext_bytes ) + self::TAG_BYTES;

		if ( strlen( $ciphertext_bytes ) < $min_bytes || strlen( $ciphertext_bytes ) > $max_bytes ) {
			return new Validation_Error( 'ciphertext', __( 'ciphertext is empty or larger than the site allows.', 'psst' ), 'psst_payload_too_large' );
		}

		$iv = self::field_string( $input, 'iv' );

		if ( null === $iv || null === Base64Url::decode( $iv, self::IV_BYTES ) ) {
			return new Validation_Error( 'iv', __( 'iv must be 12 base64url-encoded bytes.', 'psst' ) );
		}

		$check = self::field_string( $input, 'check' );

		if ( null === $check || null === Base64Url::decode( $check, self::CHECK_BYTES ) ) {
			return new Validation_Error( 'check', __( 'check must be 16 base64url-encoded bytes.', 'psst' ) );
		}

		$salt       = null;
		$iterations = null;

		if ( $has_passphrase ) {
			$salt = self::field_string( $input, 'salt' );

			if ( null === $salt || null === Base64Url::decode( $salt, self::SALT_BYTES ) ) {
				return new Validation_Error( 'salt', __( 'salt must be 16 base64url-encoded bytes when a passphrase is used.', 'psst' ) );
			}

			$kdf = $input['kdf'] ?? null;

			if ( ! is_array( $kdf ) ) {
				return new Validation_Error( 'kdf', __( 'kdf parameters are required when a passphrase is used.', 'psst' ) );
			}

			if ( 'PBKDF2' !== ( $kdf['name'] ?? null ) || 'SHA-256' !== ( $kdf['hash'] ?? null ) ) {
				return new Validation_Error( 'kdf', __( 'Only PBKDF2 with SHA-256 is supported.', 'psst' ) );
			}

			$iterations = $kdf['iterations'] ?? null;

			if ( ! is_int( $iterations ) && ! ( is_string( $iterations ) && ctype_digit( $iterations ) ) ) {
				return new Validation_Error( 'kdf', __( 'kdf.iterations must be an integer.', 'psst' ) );
			}

			$iterations = (int) $iterations;

			if ( $iterations < self::min_iterations() || $iterations > self::MAX_KDF_ITERATIONS ) {
				return new Validation_Error( 'kdf', __( 'kdf.iterations is outside the range the site allows.', 'psst' ) );
			}
		} else {
			if ( isset( $input['salt'] ) && '' !== $input['salt'] ) {
				return new Validation_Error( 'salt', __( 'salt is only valid with a passphrase.', 'psst' ) );
			}

			if ( isset( $input['kdf'] ) ) {
				return new Validation_Error( 'kdf', __( 'kdf is only valid with a passphrase.', 'psst' ) );
			}
		}

		return new self( $ciphertext, $iv, $has_passphrase, $salt, $iterations, $check );
	}

	/**
	 * Rebuild an envelope from its stored JSON.
	 *
	 * @param string $json The stored post_content.
	 *
	 * @return Envelope|null Null when the stored value is not a v2 envelope.
	 */
	public static function from_json( string $json ): ?Envelope {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || self::VERSION !== (int) ( $data['v'] ?? 0 ) ) {
			return null;
		}

		$has_passphrase = isset( $data['kdf'] ) && is_array( $data['kdf'] );

		return new self(
			(string) ( $data['ct'] ?? '' ),
			(string) ( $data['iv'] ?? '' ),
			$has_passphrase,
			$has_passphrase ? (string) ( $data['kdf']['salt'] ?? '' ) : null,
			$has_passphrase ? (int) ( $data['kdf']['iterations'] ?? 0 ) : null,
			(string) ( $data['check'] ?? '' ),
		);
	}

	/**
	 * The associative form, which is also what `reveal` returns.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'v'     => self::VERSION,
			'alg'   => self::ALGORITHM,
			'aad'   => $this->aad(),
			'iv'    => $this->iv,
			'ct'    => $this->ciphertext,
			'kdf'   => $this->has_passphrase ? [
				'name'       => 'PBKDF2',
				'hash'       => 'SHA-256',
				'iterations' => $this->iterations,
				'salt'       => $this->salt,
			] : null,
			'check' => $this->check,
		];
	}

	/**
	 * The exact string stored in post_content.
	 *
	 * @return string
	 */
	public function to_json(): string {
		return (string) json_encode( $this->to_array(), JSON_UNESCAPED_SLASHES );
	}

	/**
	 * The additional authenticated data the client bound the ciphertext to.
	 *
	 * @return string
	 */
	public function aad(): string {
		return 'psst/2;pp=' . ( $this->has_passphrase ? '1' : '0' );
	}

	/**
	 * Decoded ciphertext size in bytes, for the admin list.
	 *
	 * @return int
	 */
	public function size(): int {
		return strlen( (string) Base64Url::decode( $this->ciphertext ) );
	}

	/**
	 * The fewest PBKDF2 iterations the server accepts.
	 *
	 * @return int
	 */
	public static function min_iterations(): int {
		/**
		 * Filters the minimum PBKDF2 iteration count accepted from a client.
		 *
		 * @param int $iterations Defaults to 600000.
		 */
		return max( 1000, (int) apply_filters( 'psst_min_kdf_iterations', self::MIN_KDF_ITERATIONS ) );
	}

	/**
	 * A non-empty base64url string field, or null.
	 *
	 * @param array<string, mixed> $input The request fields.
	 * @param string               $key   The field.
	 *
	 * @return string|null
	 */
	private static function field_string( array $input, string $key ): ?string {
		$value = $input[ $key ] ?? null;

		if ( ! is_string( $value ) || '' === $value || ! Base64Url::is_valid( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * A strict boolean reading of a JSON value.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool|null Null when it is not unambiguously a boolean.
	 */
	private static function to_bool( mixed $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( 1 === $value || '1' === $value || 'true' === $value ) {
			return true;
		}

		if ( 0 === $value || '0' === $value || 'false' === $value ) {
			return false;
		}

		return null;
	}
}
