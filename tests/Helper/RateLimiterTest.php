<?php
/**
 * Rate_Limiter tests.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Helper;

use Linchpin\Psst\Helper\Rate_Limiter;
use PHPUnit\Framework\TestCase;

/**
 * Class RateLimiterTest
 */
final class RateLimiterTest extends TestCase {

	/**
	 * In-memory store.
	 *
	 * @var array<string, array{value: array, expires: int}>
	 */
	private array $store = [];

	/**
	 * The injected clock.
	 *
	 * @var int
	 */
	private int $now = 1_000_000;

	private function limiter(): Rate_Limiter {
		return new Rate_Limiter(
			fn( string $key ) => isset( $this->store[ $key ] ) && $this->store[ $key ]['expires'] > $this->now ? $this->store[ $key ]['value'] : null,
			function ( string $key, array $value, int $ttl ): void {
				$this->store[ $key ] = [
					'value'   => $value,
					'expires' => $this->now + $ttl,
				];
			},
			fn() => $this->now,
			'secret'
		);
	}

	public function test_allows_up_to_the_limit_then_refuses_with_retry_after(): void {
		$limiter = $this->limiter();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertSame( 0, $limiter->hit( 'create', '1.2.3.4', 3, 3600 ) );
		}

		$this->assertSame( 3600, $limiter->hit( 'create', '1.2.3.4', 3, 3600 ) );

		$this->now += 100;

		$this->assertSame( 3500, $limiter->hit( 'create', '1.2.3.4', 3, 3600 ) );
	}

	public function test_window_rolls_over(): void {
		$limiter = $this->limiter();

		$limiter->hit( 'create', '1.2.3.4', 1, 60 );
		$this->assertSame( 60, $limiter->hit( 'create', '1.2.3.4', 1, 60 ) );

		$this->now += 60;

		$this->assertSame( 0, $limiter->hit( 'create', '1.2.3.4', 1, 60 ) );
	}

	public function test_scopes_and_identifiers_are_isolated(): void {
		$limiter = $this->limiter();

		$limiter->hit( 'create', '1.2.3.4', 1 );

		$this->assertSame( 0, $limiter->hit( 'reveal', '1.2.3.4', 1 ) );
		$this->assertSame( 0, $limiter->hit( 'create', '5.6.7.8', 1 ) );
		$this->assertNotSame( 0, $limiter->hit( 'create', '1.2.3.4', 1 ) );
	}

	public function test_zero_limit_disables(): void {
		$limiter = $this->limiter();

		for ( $i = 0; $i < 50; $i++ ) {
			$this->assertSame( 0, $limiter->hit( 'create', '1.2.3.4', 0 ) );
		}

		$this->assertSame( [], $this->store );
	}

	public function test_key_never_contains_the_identifier(): void {
		$key = $this->limiter()->key( 'create', '203.0.113.9' );

		$this->assertStringStartsWith( 'psst_rl_create_', $key );
		$this->assertStringNotContainsString( '203', $key );
		$this->assertStringNotContainsString( '113', $key );
		$this->assertMatchesRegularExpression( '/^psst_rl_create_[0-9a-f]{32}$/', $key );
	}
}
