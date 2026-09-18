<?php
/**
 * Time-to-live choices.
 *
 * WordPress-free apart from apply_filters() and __(), which the unit suite stubs.
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

/**
 * Class Ttl
 */
final class Ttl {

	/**
	 * Default when nothing is configured: one week.
	 */
	public const DEFAULT_MINUTES = 10080;

	/**
	 * Every choice the plugin knows, in minutes, with its label.
	 *
	 * The same eleven the 1.x plugin offered.
	 *
	 * @return array<int, string>
	 */
	public static function catalog(): array {
		return [
			10080 => __( '1 Week', 'psst' ),
			4320  => __( '3 Days', 'psst' ),
			1440  => __( '1 Day', 'psst' ),
			720   => __( '12 Hours', 'psst' ),
			360   => __( '6 Hours', 'psst' ),
			240   => __( '4 Hours', 'psst' ),
			120   => __( '2 Hours', 'psst' ),
			60    => __( '1 Hour', 'psst' ),
			30    => __( '30 Minutes', 'psst' ),
			15    => __( '15 Minutes', 'psst' ),
			5     => __( '5 Minutes', 'psst' ),
		];
	}

	/**
	 * The choices a sender may pick from: the catalog narrowed to what the
	 * settings enable, then filtered.
	 *
	 * @param int[]|null $enabled Enabled minute values; null means all.
	 *
	 * @return array<int, string>
	 */
	public static function options( ?array $enabled = null ): array {
		$catalog = self::catalog();

		if ( null !== $enabled ) {
			$enabled = array_map( 'intval', $enabled );
			$catalog = array_intersect_key( $catalog, array_flip( $enabled ) );

			if ( empty( $catalog ) ) {
				$catalog = self::catalog();
			}
		}

		/**
		 * Filters the expiration choices offered to a sender.
		 *
		 * Kept from 1.x under the same name and shape: minutes => label.
		 *
		 * @param array<int, string> $catalog Minutes => label.
		 */
		$filtered = apply_filters( 'psst_schedule_options', $catalog );

		return ! empty( $filtered ) ? $filtered : $catalog;
	}

	/**
	 * Whether a ttl is one of the offered choices.
	 *
	 * @param mixed      $minutes Candidate.
	 * @param int[]|null $enabled Enabled minute values; null means all.
	 *
	 * @return bool
	 */
	public static function is_allowed( mixed $minutes, ?array $enabled = null ): bool {
		if ( ! is_numeric( $minutes ) || (int) $minutes <= 0 ) {
			return false;
		}

		return array_key_exists( (int) $minutes, self::options( $enabled ) );
	}

	/**
	 * The default choice, guaranteed to be one of the options.
	 *
	 * @param int        $preferred The configured default.
	 * @param int[]|null $enabled   Enabled minute values; null means all.
	 *
	 * @return int
	 */
	public static function default_minutes( int $preferred = self::DEFAULT_MINUTES, ?array $enabled = null ): int {
		$options = self::options( $enabled );

		if ( array_key_exists( $preferred, $options ) ) {
			return $preferred;
		}

		return (int) array_key_first( $options );
	}

	/**
	 * When a secret created now with this ttl expires.
	 *
	 * @param int      $minutes The ttl.
	 * @param int|null $now     Unix timestamp to count from; defaults to time().
	 *
	 * @return int Unix timestamp, UTC.
	 */
	public static function expires_at( int $minutes, ?int $now = null ): int {
		return ( $now ?? time() ) + ( max( 1, $minutes ) * 60 );
	}
}
