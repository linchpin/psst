<?php
/**
 * A fixed-window rate limiter.
 *
 * The store and the clock are injected so the logic is unit-testable without
 * WordPress; the default store is transients.
 *
 * @package Linchpin\Psst\Helper
 */

namespace Linchpin\Psst\Helper;

/**
 * Class Rate_Limiter
 */
final class Rate_Limiter {

	/**
	 * Read a counter. Signature: fn( string $key ): array|null.
	 *
	 * @var callable
	 */
	private $read;

	/**
	 * Write a counter. Signature: fn( string $key, array $value, int $ttl ): void.
	 *
	 * @var callable
	 */
	private $write;

	/**
	 * The current time. Signature: fn(): int.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * HMAC key for hashing identifiers, so the store never holds a raw IP.
	 *
	 * @var string
	 */
	private string $secret;

	/**
	 * Constructor.
	 *
	 * @param callable $read   Reader.
	 * @param callable $write  Writer.
	 * @param callable $clock  Clock.
	 * @param string   $secret HMAC key.
	 */
	public function __construct( callable $read, callable $write, callable $clock, string $secret ) {
		$this->read   = $read;
		$this->write  = $write;
		$this->clock  = $clock;
		$this->secret = $secret;
	}

	/**
	 * The production limiter, backed by transients and the site's salts.
	 *
	 * @return Rate_Limiter
	 */
	public static function with_transients(): Rate_Limiter {
		return new self(
			static function ( string $key ) {
				$value = get_transient( $key );

				return is_array( $value ) ? $value : null;
			},
			static function ( string $key, array $value, int $ttl ): void {
				set_transient( $key, $value, $ttl );
			},
			'time',
			wp_salt( 'nonce' )
		);
	}

	/**
	 * Count one hit and say whether it is over the limit.
	 *
	 * @param string $scope      What is being limited, e.g. `create`.
	 * @param string $identifier Who, e.g. an IP address. Hashed before storage.
	 * @param int    $limit      Hits allowed per window. Zero or less disables the limit.
	 * @param int    $window     Window length in seconds.
	 *
	 * @return int 0 when allowed; otherwise the seconds until the window resets.
	 */
	public function hit( string $scope, string $identifier, int $limit, int $window = 3600 ): int {
		if ( $limit <= 0 ) {
			return 0;
		}

		$now   = (int) ( $this->clock )();
		$key   = $this->key( $scope, $identifier );
		$state = ( $this->read )( $key );

		if ( ! is_array( $state ) || ! isset( $state['count'] ) || ! isset( $state['start'] ) || ( (int) $state['start'] + $window ) <= $now ) {
			$state = [
				'count' => 0,
				'start' => $now,
			];
		}

		$retry_after = max( 1, (int) $state['start'] + $window - $now );

		if ( (int) $state['count'] >= $limit ) {
			return $retry_after;
		}

		++$state['count'];

		( $this->write )( $key, $state, $retry_after );

		return 0;
	}

	/**
	 * The storage key for a scope and identifier.
	 *
	 * @param string $scope      Scope.
	 * @param string $identifier Identifier.
	 *
	 * @return string
	 */
	public function key( string $scope, string $identifier ): string {
		return 'psst_rl_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $scope ) ) . '_' . substr( hash_hmac( 'sha256', $identifier, $this->secret ), 0, 32 );
	}
}
