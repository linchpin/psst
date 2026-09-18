<?php
/**
 * Expiry, the sweep, and keeping the scheduler honest.
 *
 * @package Linchpin\Psst\Controller
 */

namespace Linchpin\Psst\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Core\Scheduler;
use Linchpin\Psst\Model\Post_Type\Secret;
use Linchpin\Psst\Model\Secret_Repository;

/**
 * Class Lifecycle
 */
class Lifecycle implements Controller_Interface {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( Scheduler::HOOK_EXPIRE, [ $this, 'expire' ] );
		add_action( Scheduler::HOOK_SWEEP, [ $this, 'sweep' ] );
		add_action( Scheduler::HOOK_PURGE_LEGACY, [ $this, 'purge_legacy' ] );
		add_action( 'before_delete_post', [ $this, 'unschedule_on_delete' ], 10, 2 );
		add_action( 'init', [ Scheduler::class, 'ensure_sweep' ], 30 );
	}

	/**
	 * The scheduled expiry of one secret. Idempotent.
	 *
	 * @param int $post_id The secret.
	 *
	 * @return void
	 */
	public function expire( $post_id ): void {
		$post_id = (int) $post_id;

		if ( Secret::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		( new Secret_Repository() )->destroy( $post_id, 'expired' );

		/**
		 * Fires after a secret expired on schedule.
		 *
		 * @param int $post_id The secret.
		 */
		do_action( 'psst_secret_expired', $post_id );
	}

	/**
	 * The daily safety net.
	 *
	 * @return void
	 */
	public function sweep(): void {
		$repository = new Secret_Repository();

		/**
		 * Filters how many rows one sweep pass deletes.
		 *
		 * @param int $batch Defaults to 200.
		 */
		$batch = max( 1, (int) apply_filters( 'psst_sweep_batch_size', 200 ) );
		$total = 0;

		do {
			$deleted = $repository->sweep( $batch );
			$total  += $deleted;
		} while ( $deleted >= $batch );

		/**
		 * Fires after a sweep.
		 *
		 * @param int $total Rows deleted.
		 */
		do_action( 'psst_secrets_swept', $total );
	}

	/**
	 * A batched continuation of the 1.x purge.
	 *
	 * @return void
	 */
	public function purge_legacy(): void {
		$repository = new Secret_Repository();
		$repository->purge_legacy( 500 );

		if ( $repository->has_legacy() ) {
			Scheduler::queue_legacy_purge();
		}
	}

	/**
	 * Every deletion path cancels the expiry action in one place.
	 *
	 * @param int      $post_id The post.
	 * @param \WP_Post $post    The post object.
	 *
	 * @return void
	 */
	public function unschedule_on_delete( $post_id, $post ): void {
		if ( Secret::POST_TYPE === $post->post_type ) {
			Scheduler::unschedule_expiry( (int) $post_id );
		}
	}

	/**
	 * Re-arm expiry for every active secret whose action is missing.
	 *
	 * Runs on activation, so a deactivate/reactivate cycle loses nothing.
	 *
	 * @return int Secrets re-armed.
	 */
	public static function rearm(): int {
		$repository = new Secret_Repository();
		$rearmed    = 0;

		foreach ( $repository->active_ids() as $post_id ) {
			$expires_at = $repository->expires_at( $post_id );

			if ( $expires_at <= time() ) {
				$repository->destroy( $post_id, 'expired' );
				continue;
			}

			if ( ! Scheduler::has_expiry( $post_id ) ) {
				Scheduler::schedule_expiry( $post_id, $expires_at );
				++$rearmed;
			}
		}

		return $rearmed;
	}
}
