<?php
/**
 * Envelope tests.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Model;

use Linchpin\Psst\Model\Envelope;
use Linchpin\Psst\Model\Validation_Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class EnvelopeTest
 */
final class EnvelopeTest extends TestCase {

	private const MAX = 32768;

	protected function tearDown(): void {
		psst_test_reset_filters();
	}

	/**
	 * The shared fixture, the same file the JS suite reads.
	 *
	 * @param string $which with_passphrase or without_passphrase.
	 *
	 * @return array<string, mixed>
	 */
	private static function fixture( string $which ): array {
		$all = json_decode( (string) file_get_contents( __DIR__ . '/../fixtures/envelope-valid.json' ), true );

		return $all[ $which ];
	}

	public function test_accepts_a_valid_envelope_with_passphrase(): void {
		$envelope = Envelope::from_request( self::fixture( 'with_passphrase' ), self::MAX );

		$this->assertInstanceOf( Envelope::class, $envelope );
		$this->assertTrue( $envelope->has_passphrase );
		$this->assertSame( 600000, $envelope->iterations );
		$this->assertSame( 'psst/2;pp=1', $envelope->aad() );
		$this->assertSame( 32, $envelope->size() );

		$array = $envelope->to_array();

		$this->assertSame( 2, $array['v'] );
		$this->assertSame( 'A256GCM', $array['alg'] );
		$this->assertSame( 'PBKDF2', $array['kdf']['name'] );
		$this->assertSame( self::fixture( 'with_passphrase' )['salt'], $array['kdf']['salt'] );
	}

	public function test_accepts_a_valid_envelope_without_passphrase(): void {
		$envelope = Envelope::from_request( self::fixture( 'without_passphrase' ), self::MAX );

		$this->assertInstanceOf( Envelope::class, $envelope );
		$this->assertFalse( $envelope->has_passphrase );
		$this->assertNull( $envelope->salt );
		$this->assertNull( $envelope->iterations );
		$this->assertSame( 'psst/2;pp=0', $envelope->aad() );
		$this->assertNull( $envelope->to_array()['kdf'] );
	}

	public function test_json_round_trips(): void {
		$envelope = Envelope::from_request( self::fixture( 'with_passphrase' ), self::MAX );
		$json     = $envelope->to_json();
		$again    = Envelope::from_json( $json );

		$this->assertSame( $envelope->to_array(), $again->to_array() );
		$this->assertStringNotContainsString( '\/', $json );
	}

	public function test_from_json_rejects_other_versions(): void {
		$this->assertNull( Envelope::from_json( '{"v":1}' ) );
		$this->assertNull( Envelope::from_json( 'not json' ) );
	}

	/**
	 * One broken field at a time.
	 *
	 * @return array<string, array{string, array<string, mixed>, string}>
	 */
	public static function invalid(): array {
		return [
			'version 1'                => [ 'with_passphrase', [ 'version' => 1 ], 'version' ],
			'version missing'          => [ 'with_passphrase', [ 'version' => null ], 'version' ],
			'ciphertext not base64url' => [ 'with_passphrase', [ 'ciphertext' => 'Zm9v+' ], 'ciphertext' ],
			'ciphertext only a tag'    => [ 'with_passphrase', [ 'ciphertext' => 'AAECAwQFBgcICQoLDA0ODw' ], 'ciphertext' ],
			'iv 11 bytes'              => [ 'with_passphrase', [ 'iv' => 'AAECAwQFBgcICQo' ], 'iv' ],
			'iv 13 bytes'              => [ 'with_passphrase', [ 'iv' => 'AAECAwQFBgcICQoLDA' ], 'iv' ],
			'check 15 bytes'           => [ 'with_passphrase', [ 'check' => 'AAECAwQFBgcICQoLDA0O' ], 'check' ],
			'salt 15 bytes'            => [ 'with_passphrase', [ 'salt' => 'AAECAwQFBgcICQoLDA0O' ], 'salt' ],
			'salt without passphrase'  => [ 'without_passphrase', [ 'salt' => 'AAECAwQFBgcICQoLDA0ODw' ], 'salt' ],
			'kdf without passphrase'   => [ 'without_passphrase', [ 'kdf' => [ 'name' => 'PBKDF2' ] ], 'kdf' ],
			'kdf missing'              => [ 'with_passphrase', [ 'kdf' => null ], 'kdf' ],
			'kdf wrong hash'           => [
				'with_passphrase',
				[
					'kdf' => [
						'name' => 'PBKDF2',
						'hash' => 'SHA-1',
						'iterations' => 600000,
					],
				],
				'kdf',
			],
			'kdf too few iterations'   => [
				'with_passphrase',
				[
					'kdf' => [
						'name' => 'PBKDF2',
						'hash' => 'SHA-256',
						'iterations' => 599999,
					],
				],
				'kdf',
			],
			'kdf too many iterations'  => [
				'with_passphrase',
				[
					'kdf' => [
						'name' => 'PBKDF2',
						'hash' => 'SHA-256',
						'iterations' => 5000001,
					],
				],
				'kdf',
			],
			'kdf float iterations'     => [
				'with_passphrase',
				[
					'kdf' => [
						'name' => 'PBKDF2',
						'hash' => 'SHA-256',
						'iterations' => 600000.5,
					],
				],
				'kdf',
			],
			'has_passphrase string'    => [ 'with_passphrase', [ 'has_passphrase' => 'yes' ], 'has_passphrase' ],
			'passphrase leaked'        => [ 'with_passphrase', [ 'passphrase' => 'hunter2' ], 'passphrase' ],
			'key leaked'               => [ 'with_passphrase', [ 'key' => 'abc' ], 'key' ],
			'plaintext leaked'         => [ 'without_passphrase', [ 'plaintext' => 'oops' ], 'plaintext' ],
		];
	}

	#[DataProvider( 'invalid' )]
	public function test_rejects_each_invalid_field( string $base, array $override, string $field ): void {
		$input  = array_merge( self::fixture( $base ), $override );
		$result = Envelope::from_request( $input, self::MAX );

		$this->assertInstanceOf( Validation_Error::class, $result );
		$this->assertSame( $field, $result->field );
	}

	public function test_oversize_ciphertext_is_a_payload_error(): void {
		$input               = self::fixture( 'without_passphrase' );
		$input['ciphertext'] = rtrim( strtr( base64_encode( random_bytes( 100 + 16 ) ), '+/', '-_' ), '=' );

		$result = Envelope::from_request( $input, 100 );
		$this->assertInstanceOf( Envelope::class, $result );

		$input['ciphertext'] = rtrim( strtr( base64_encode( random_bytes( 101 + 16 ) ), '+/', '-_' ), '=' );

		$result = Envelope::from_request( $input, 100 );
		$this->assertInstanceOf( Validation_Error::class, $result );
		$this->assertSame( 'psst_payload_too_large', $result->code );
	}

	public function test_min_iterations_filter_raises_the_floor_only(): void {
		add_filter( 'psst_min_kdf_iterations', static fn() => 700000 );

		$result = Envelope::from_request( self::fixture( 'with_passphrase' ), self::MAX );

		$this->assertInstanceOf( Validation_Error::class, $result );
		$this->assertSame( 'kdf', $result->field );
	}

	public function test_accepts_string_booleans_and_digit_strings(): void {
		$input                       = self::fixture( 'with_passphrase' );
		$input['has_passphrase']     = 'true';
		$input['kdf']['iterations']  = '600000';

		$this->assertInstanceOf( Envelope::class, Envelope::from_request( $input, self::MAX ) );
	}
}
