<?php
/**
 * Base64Url tests.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Helper;

use Linchpin\Psst\Helper\Base64Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class Base64UrlTest
 */
final class Base64UrlTest extends TestCase {

	/**
	 * RFC 4648 §10 vectors, unpadded.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function vectors(): array {
		return [
			'empty'  => [ '', '' ],
			'f'      => [ 'f', 'Zg' ],
			'fo'     => [ 'fo', 'Zm8' ],
			'foo'    => [ 'foo', 'Zm9v' ],
			'foob'   => [ 'foob', 'Zm9vYg' ],
			'fooba'  => [ 'fooba', 'Zm9vYmE' ],
			'foobar' => [ 'foobar', 'Zm9vYmFy' ],
		];
	}

	#[DataProvider( 'vectors' )]
	public function test_encode_matches_rfc_vectors( string $plain, string $encoded ): void {
		$this->assertSame( $encoded, Base64Url::encode( $plain ) );
	}

	#[DataProvider( 'vectors' )]
	public function test_decode_matches_rfc_vectors( string $plain, string $encoded ): void {
		if ( '' === $encoded ) {
			$this->assertNull( Base64Url::decode( $encoded ) );

			return;
		}

		$this->assertSame( $plain, Base64Url::decode( $encoded ) );
	}

	public function test_uses_url_safe_alphabet(): void {
		$this->assertSame( '-_8', Base64Url::encode( "\xfb\xff" ) );
	}

	public function test_rejects_standard_alphabet_and_padding(): void {
		$this->assertNull( Base64Url::decode( 'Zm9v+' ) );
		$this->assertNull( Base64Url::decode( 'Zm9vYg==' ) );
		$this->assertNull( Base64Url::decode( 'not base64!' ) );
	}

	public function test_expected_length_is_enforced(): void {
		$twelve = Base64Url::encode( random_bytes( 12 ) );

		$this->assertSame( 12, strlen( (string) Base64Url::decode( $twelve, 12 ) ) );
		$this->assertNull( Base64Url::decode( $twelve, 16 ) );
	}

	public function test_sanitize_strips_whitespace_only(): void {
		$this->assertSame( 'Zm9v', Base64Url::sanitize( " Zm9\n v " ) );
		$this->assertSame( '', Base64Url::sanitize( 42 ) );
	}
}
