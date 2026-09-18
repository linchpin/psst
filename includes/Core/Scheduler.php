<?php
/**
 * Scheduling, through Action Scheduler with a WP-Cron fallback.
 *
 * @package Linchpin\Psst\Core
 */

namespace Linchpin\Psst\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Model\Post_Type\Secret;

/**
 * Class Scheduler
 */
final class Scheduler {

	public const GROUP = 'psst';

	public const HOOK_EXPIRE = 'psst_expire_secret';

	public const HOOK_SWEEP = 'psst_sweep_secrets';

	public const HOOK_PURGE_LEGACY = 'psst_purge_legacy';

	/**
	 * Whether Action Scheduler is loaded.
	 *
	 * @return bool
	 */
	public static function has_action_scheduler(): bool {
		return function_exists( 'as_schedule_single_action' )
			&& function_exists( 'as_unschedule_action' )
			&& function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_has_scheduled_action' );
	}

	/**
	 * Schedule a secret's expiry.
	 *
	 * @param int $post_id    The secret.
	 * @param int $expires_at Unix timestamp.
	 *
	 * @return void
	 */
	public static function schedule_expiry( int $post_id, int $expires_at ): void {
		if ( self::has_action_scheduler() ) {
			/*
			 * `unique` is false on purpose: Action Scheduler's uniqueness check
			 * compares hook and group only, not args, so `true` would let the
			 * first secret's action block every later one.
			 */
			$action_id = as_schedule_single_action( $expires_at, self::HOOK_EXPIRE, [ $post_id ], self::GROUP, false );

			if ( $action_id ) {
				update_post_meta( $post_id, Secret::META_ACTION_ID, (int) $action_id );
			}

			return;
		}

		wp_schedule_single_event( $expires_at, self::HOOK_EXPIRE, [ $post_id ] );
	}

	/**
	 * Cancel a secret's expiry.
	 *
	 * @param int $post_id The secret.
	 *
	 * @return void
	 */
	public static function unschedule_expiry( int $post_id ): void {
		if ( self::has_action_scheduler() ) {
			as_unschedule_action( self::HOOK_EXPIRE, [ $post_id ], self::GROUP );
		}

		wp_clear_scheduled_hook( self::HOOK_EXPIRE, [ $post_id ] );
	}

	/**
	 * Whether a secret's expiry is scheduled.
	 *
	 * @param int $post_id The secret.
	 *
	 * @return bool
	 */
	public static function has_expiry( int $post_id ): bool {
		if ( self::has_action_scheduler() ) {
			return as_has_scheduled_action( self::HOOK_EXPIRE, [ $post_id ], self::GROUP );
		}

		return false !== wp_next_scheduled( self::HOOK_EXPIRE, [ $post_id ] );
	}

	/**
	 * Make sure the daily sweep is scheduled.
	 *
	 * Throttled through a transient so init does not query the scheduler on
	 * every request.
	 *
	 * @param bool $force Skip the throttle.
	 *
	 * @return void
	 */
	public static function ensure_sweep( bool $force = false ): void {
		if ( ! $force && get_transient( 'psst_sweep_checked' ) ) {
			return;
		}

		if ( self::has_action_scheduler() ) {
			if ( ! as_has_scheduled_action( self::HOOK_SWEEP, [], self::GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK_SWEEP, [], self::GROUP, true );
			}
		} elseif ( ! wp_next_scheduled( self::HOOK_SWEEP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_SWEEP );
		}

		set_transient( 'psst_sweep_checked', 1, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * The next sweep, or null.
	 *
	 * @return int|null Unix timestamp.
	 */
	public static function next_sweep(): ?int {
		if ( self::has_action_scheduler() && function_exists( 'as_next_scheduled_action' ) ) {
			$next = as_next_scheduled_action( self::HOOK_SWEEP, [], self::GROUP );

			return is_int( $next ) ? $next : null;
		}

		$next = wp_next_scheduled( self::HOOK_SWEEP );

		return false === $next ? null : (int) $next;
	}

	/**
	 * Queue a continuation of the legacy purge.
	 *
	 * @return void
	 */
	public static function queue_legacy_purge(): void {
		if ( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK_PURGE_LEGACY, [], self::GROUP ) ) {
				as_enqueue_async_action( self::HOOK_PURGE_LEGACY, [], self::GROUP );
			}

			return;
		}

		if ( ! wp_next_scheduled( self::HOOK_PURGE_LEGACY ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK_PURGE_LEGACY );
		}
	}

	/**
	 * Cancel everything this plugin scheduled.
	 *
	 * @return void
	 */
	public static function clear_all(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', [], self::GROUP );
		}

		wp_clear_scheduled_hook( self::HOOK_SWEEP );
		wp_clear_scheduled_hook( self::HOOK_PURGE_LEGACY );
		delete_transient( 'psst_sweep_checked' );
	}
}
