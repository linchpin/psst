<?php
/**
 * Ttl tests.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Model;

use Linchpin\Psst\Model\Ttl;
use PHPUnit\Framework\TestCase;

/**
 * Class TtlTest
 */
final class TtlTest extends TestCase {

	protected function tearDown(): void {
		psst_test_reset_filters();
	}

	public function test_catalog_has_the_eleven_choices(): void {
		$this->assertSame( [ 10080, 4320, 1440, 720, 360, 240, 120, 60, 30, 15, 5 ], array_keys( Ttl::catalog() ) );
	}

	public function test_options_narrow_to_enabled(): void {
		$this->assertSame( [ 60, 5 ], array_keys( Ttl::options( [ 5, 60, 999 ] ) ) );
	}

	public function test_options_fall_back_to_catalog_when_nothing_enabled(): void {
		$this->assertCount( 11, Ttl::options( [] ) );
		$this->assertCount( 11, Ttl::options( [ 999 ] ) );
	}

	public function test_schedule_options_filter_is_honored(): void {
		add_filter( 'psst_schedule_options', static fn() => [ 1 => 'One minute' ] );

		$this->assertSame( [ 1 => 'One minute' ], Ttl::options() );
		$this->assertTrue( Ttl::is_allowed( 1 ) );
		$this->assertFalse( Ttl::is_allowed( 60 ) );
	}

	public function test_is_allowed(): void {
		$this->assertTrue( Ttl::is_allowed( 60 ) );
		$this->assertTrue( Ttl::is_allowed( '60' ) );
		$this->assertFalse( Ttl::is_allowed( 61 ) );
		$this->assertFalse( Ttl::is_allowed( 0 ) );
		$this->assertFalse( Ttl::is_allowed( -5 ) );
		$this->assertFalse( Ttl::is_allowed( 'sixty' ) );
		$this->assertFalse( Ttl::is_allowed( 60, [ 5 ] ) );
	}

	public function test_default_minutes_falls_back_to_first_option(): void {
		$this->assertSame( 10080, Ttl::default_minutes() );
		$this->assertSame( 60, Ttl::default_minutes( 60 ) );
		$this->assertSame( 1440, Ttl::default_minutes( 60, [ 1440, 5 ] ) );
	}

	public function test_expires_at_counts_from_the_given_clock(): void {
		$this->assertSame( 1_000_300, Ttl::expires_at( 5, 1_000_000 ) );
		$this->assertSame( 1_000_060, Ttl::expires_at( 0, 1_000_000 ) );
	}
}
