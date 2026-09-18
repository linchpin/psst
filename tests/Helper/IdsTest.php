<?php
/**
 * Ids tests.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Helper;

use Linchpin\Psst\Helper\Ids;
use PHPUnit\Framework\TestCase;

/**
 * Class IdsTest
 */
final class IdsTest extends TestCase {

	protected function tearDown(): void {
		psst_test_reset_filters();
	}

	public function test_public_id_is_22_url_safe_characters(): void {
		$id = Ids::public_id();

		$this->assertSame( 22, strlen( $id ) );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]+$/', $id );
		$this->assertTrue( Ids::is_public_id( $id ) );
	}

	public function test_slug_length_filter_has_a_floor_of_16_bytes(): void {
		add_filter( 'psst_slug_length', static fn() => 4 );

		$this->assertSame( 22, strlen( Ids::public_id() ) );
	}

	public function test_slug_length_filter_can_lengthen(): void {
		add_filter( 'psst_slug_length', static fn() => 32 );

		$this->assertSame( 43, strlen( Ids::public_id() ) );
	}

	public function test_manage_token_is_43_characters_and_hashes_to_64_hex(): void {
		$token = Ids::manage_token();

		$this->assertSame( 43, strlen( $token ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', Ids::hash_token( $token ) );
	}

	public function test_token_matches(): void {
		$token = Ids::manage_token();
		$hash  = Ids::hash_token( $token );

		$this->assertTrue( Ids::token_matches( $hash, $token ) );
		$this->assertFalse( Ids::token_matches( $hash, $token . 'x' ) );
		$this->assertFalse( Ids::token_matches( $hash, '' ) );
		$this->assertFalse( Ids::token_matches( $hash, null ) );
		$this->assertFalse( Ids::token_matches( '', $token ) );
	}

	public function test_is_public_id_rejects_short_long_and_unsafe(): void {
		$this->assertFalse( Ids::is_public_id( 'short' ) );
		$this->assertFalse( Ids::is_public_id( str_repeat( 'a', 65 ) ) );
		$this->assertFalse( Ids::is_public_id( str_repeat( 'a', 21 ) . '+' ) );
		$this->assertFalse( Ids::is_public_id( 42 ) );
		$this->assertTrue( Ids::is_public_id( str_repeat( 'a', 64 ) ) );
	}
}
