<?php

namespace Psst\Helper;

/**
 * Class Utils
 * @package Psst\Helper
 */
class Utils {

	/**
	 * Get the rounded time
	 *
	 * @param     $second
	 * @param int $precision
	 *
	 * @throws \Exception
	 */
	public static function round_time( $second, $precision = 30 ) {
		// 1) Set number of seconds to 0 (by rounding up to the nearest minute if necessary)
		$datetime = new \DateTime();

		$second = (int) $datetime->format( 's' );
		if ( $second > 30 ) {
			// Jumps to the next minute
			$datetime->add( new \DateInterval( 'PT' . ( 60 - $second ) . 'S' ) );
		} elseif ( $second > 0 ) {
			// Back to 0 seconds on current minute
			$datetime->sub( new \DateInterval( 'PT' . $second . 'S' ) );
		}
		// 2) Get minute
		$minute = (int) $datetime->format( 'i' );
		// 3) Convert modulo $precision
		$minute = $minute % $precision;
		if ( $minute > 0 ) {
			// 4) Count minutes to next $precision-multiple minutes
			$diff = $precision - $minute;
			// 5) Add the difference to the original date time
			$datetime->add( new \DateInterval( 'PT' . $diff . 'M' ) );
		}
	}

	/**
	 * Get a timestamp based on a date and duration
	 * The duration should be the number of months
	 *
	 * @since 1.0
	 *
	 * @throws \Exception If anything goes wrong.
	 *
	 * @param string $date     The date to get the timestamp for.
	 * @param string $duration The duration.
	 *
	 * @return int
	 */
	private static function get_timestamp( $date, $duration ) {
		$date_time = new \DateTime( $date );
		$old_day   = $date_time->format( 'd' );

		$date_time->add( new \DateInterval( 'P' . $duration . 'M' ) );

		$new_day = $date_time->format( 'd' );

		if ( $old_day !== $new_day ) {
			$date_time->sub( new \DateInterval( 'P' . $new_day . 'D' ) );
		}

		return $date_time->getTimestamp();
	}

	/**
	 * Get the expiration schedule
	 *
	 * @return array|mixed|void
	 */
	public static function get_secret_expiration_schedule() {
		$schedule_options = [
			10080 => esc_html__( '1 Week', 'psst' ),
			4320  => esc_html__( '3 Days', 'psst' ),
			1440  => esc_html__( '1 Day', 'psst' ),
			720   => esc_html__( '12 Hours', 'psst' ),
			360   => esc_html__( '6 Hours', 'psst' ),
			240   => esc_html__( '4 Hours', 'psst' ),
			120   => esc_html__( '2 Hours', 'psst' ),
			60    => esc_html__( '1 Hour', 'psst' ),
			30    => esc_html__( '30 Minutes', 'psst' ),
			15    => esc_html__( '15 Minutes', 'psst' ),
			5     => esc_html__( '5 Minutes', 'psst' ),
		];

		$schedule_options = apply_filters( 'psst_schedule_options', $schedule_options );

		return $schedule_options;
	}

	/**
	 * Get a list of our scheduled expiration times.
	 *
	 * @since 1.0
	 */
	public static function psst_schedule_selector() {

		$schedule_options = self::get_secret_expiration_schedule();
		?>
		<select name="expiration" id="psst-expiration" class="input-group-field">
			<?php
			foreach ( $schedule_options as $key => $option ) {
				printf(
					'<option value="%1$s">%2$s</option>',
					esc_attr( $key ),
					esc_html( $option )
				);
			}
			?>
		</select>
		<?php
	}

	/**
	 * Make sure that the unique slug we generated is unique.
	 *
	 * @param  string $slug Pass a slug and make sure we don't have the same slug already in the database.
	 *
	 * @return bool
	 */
	private static function is_unique_slug( $slug ) {

		$post_exists = get_page_by_path( $slug, OBJECT, 'secret' );

		if ( ! $post_exists ) {
			return true;
		}

		return false;
	}

	/**
	 * Create a unique slug for our secrets
	 *
	 * @return string
	 */
	public static function create_unique_slug() {
		$generated_slug_length = apply_filters( 'psst_slug_length', 32 );
		$generated_post_name   = uniqid(
			wp_generate_password(
				$generated_slug_length,
				false,
				false
			)
		);

		if ( ! self::is_unique_slug( $generated_post_name ) ) {
			$generated_post_name = self::create_unique_slug();
		}

		return $generated_post_name;
	}

	/**
	 * Return whether or not the default WP Cron process is being used.
	 * Typically with alternate/true crons the default WordPress cron will
	 * be disabled with the DISABLE_CRON constant
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public static function is_wp_cron_disabled() {
		return ( defined( 'DISABLE_CRON' ) && true === DISABLE_CRON );
	}

	/**
	 * Get an array of safe markup and classes to be used
	 * on settings pages.
	 *
	 * @since 1.0
	 *
	 * @return mixed|void
	 */
	public static function get_safe_markup() {
		$safe_content = array(
			'a'      => array(
				'href' => array(),
			),
			'span'   => array(),
			'strong' => array(),
			'p'      => array(),
			'br'     => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
		);

		return apply_filters( 'psst_safe_markup', $safe_content );
	}
}
