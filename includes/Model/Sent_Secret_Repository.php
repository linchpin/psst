<?php
/**
 * Every read and write of a sent-secret history row.
 *
 * The counterpart to Secret_Repository, and deliberately separate from it:
 * that class is the only thing allowed near an envelope, and this one is never
 * allowed near one at all.
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Helper\Ids;
use Linchpin\Psst\Model\Post_Type\Sent_Secret;

/**
 * Class Sent_Secret_Repository
 */
final class Sent_Secret_Repository {

	/**
	 * Record that a user sent a secret.
	 *
	 * @param int      $user_id    The sender.
	 * @param string   $public_id  The secret's identifier.
	 * @param array{expires_at: int, ttl_minutes: int, size: int, has_passphrase: bool} $meta What is known at creation.
	 * @param string   $recipient  What the sender typed in the recipient field, or ''.
	 * @param int|null $now        Clock override for tests.
	 *
	 * @return int The history post id, or 0.
	 */
	public function record( int $user_id, string $public_id, array $meta, string $recipient = '', ?int $now = null ): int {
		if ( $user_id <= 0 || ! Ids::is_public_id( $public_id ) ) {
			return 0;
		}

		$now = $now ?? time();

		$post_id = wp_insert_post(
			[
				'post_type'      => Sent_Secret::POST_TYPE,
				'post_status'    => 'publish',
				'post_title'     => '',
				'post_content'   => '',
				'post_author'    => $user_id,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => [
					Sent_Secret::META_PUBLIC_ID      => $public_id,
					Sent_Secret::META_EXPIRES_AT     => (int) $meta['expires_at'],
					Sent_Secret::META_TTL_MINUTES    => (int) $meta['ttl_minutes'],
					Sent_Secret::META_SIZE           => (int) $meta['size'],
					Sent_Secret::META_HAS_PASSPHRASE => ! empty( $meta['has_passphrase'] ) ? '1' : '0',
					Sent_Secret::META_RECIPIENT      => $recipient,
					Sent_Secret::META_NOTIFIED       => '0',
					Sent_Secret::META_STATE          => Sent_Secret::STATE_ACTIVE,
					Sent_Secret::META_STATE_AT       => $now,
					Sent_Secret::META_PRUNE_AFTER    => $now + Settings::history_retention(),
				],
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		/**
		 * Fires after a sent secret was recorded in a user's history.
		 *
		 * Receives identifiers only, in keeping with every other Psst hook.
		 *
		 * @param int    $post_id   The history row.
		 * @param int    $user_id   The sender.
		 * @param string $public_id The secret's identifier.
		 */
		do_action( 'psst_sent_secret_recorded', (int) $post_id, $user_id, $public_id );

		return (int) $post_id;
	}

	/**
	 * The history row describing a secret, if anyone is keeping one.
	 *
	 * @param string $public_id The secret's identifier.
	 *
	 * @return \WP_Post|null
	 */
	public function find_by_public_id( string $public_id ): ?\WP_Post {
		if ( ! Ids::is_public_id( $public_id ) ) {
			return null;
		}

		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = %s AND m.meta_value = %s
				 LIMIT 1",
				Sent_Secret::META_PUBLIC_ID,
				Sent_Secret::POST_TYPE,
				$public_id
			)
		);

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( (int) $post_id );

		/*
		 * The meta comparison may be case-insensitive; base64url is not, so
		 * confirm the stored value before trusting the match.
		 */
		if ( ! ( $post instanceof \WP_Post ) || (string) get_post_meta( $post->ID, Sent_Secret::META_PUBLIC_ID, true ) !== $public_id ) {
			return null;
		}

		return $post;
	}

	/**
	 * Move a history row to a terminal state.
	 *
	 * Terminal means terminal: the first outcome recorded wins, so a sweep that
	 * runs after a reveal cannot rewrite "viewed" into "expired".
	 *
	 * @param string   $public_id The secret's identifier.
	 * @param string   $state     One of the Sent_Secret::STATE_* constants.
	 * @param int|null $now       Clock override for tests.
	 *
	 * @return bool Whether a row was moved.
	 */
	public function mark_state( string $public_id, string $state, ?int $now = null ): bool {
		if ( ! in_array( $state, Sent_Secret::states(), true ) ) {
			return false;
		}

		$post = $this->find_by_public_id( $public_id );

		if ( ! $post ) {
			return false;
		}

		$current = (string) get_post_meta( $post->ID, Sent_Secret::META_STATE, true );

		if ( Sent_Secret::STATE_ACTIVE !== $current ) {
			return false;
		}

		update_post_meta( $post->ID, Sent_Secret::META_STATE, $state );
		update_post_meta( $post->ID, Sent_Secret::META_STATE_AT, $now ?? time() );

		/**
		 * Fires when a sent secret's outcome is recorded.
		 *
		 * @param string $public_id The secret's identifier.
		 * @param string $state     The new state.
		 * @param int    $post_id   The history row.
		 */
		do_action( 'psst_sent_secret_state', $public_id, $state, $post->ID );

		return true;
	}

	/**
	 * Note that Psst emailed the link, and to whom.
	 *
	 * @param string $public_id The secret's identifier.
	 * @param string $recipient The address the mail went to.
	 *
	 * @return bool
	 */
	public function mark_notified( string $public_id, string $recipient ): bool {
		$post = $this->find_by_public_id( $public_id );

		if ( ! $post ) {
			return false;
		}

		update_post_meta( $post->ID, Sent_Secret::META_NOTIFIED, '1' );

		if ( '' !== $recipient ) {
			update_post_meta( $post->ID, Sent_Secret::META_RECIPIENT, $recipient );
		}

		return true;
	}

	/**
	 * A page of one user's history, newest first.
	 *
	 * A row still marked active whose expiry has passed is reported as expired
	 * without being written to. The scheduler gets there eventually; the reader
	 * should not have to wait for it to be told the truth.
	 *
	 * @param int      $user_id  The sender.
	 * @param int      $page     1-based page.
	 * @param int      $per_page Rows per page.
	 * @param int|null $now      Clock override for tests.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int}
	 */
	public function for_user( int $user_id, int $page = 1, int $per_page = 20, ?int $now = null ): array {
		$empty = [
			'items' => [],
			'total' => 0,
			'pages' => 0,
		];

		if ( $user_id <= 0 ) {
			return $empty;
		}

		$now      = $now ?? time();
		$per_page = max( 1, min( 100, $per_page ) );

		$query = new \WP_Query(
			[
				'post_type'              => Sent_Secret::POST_TYPE,
				'post_status'            => 'publish',
				'author'                 => $user_id,
				'posts_per_page'         => $per_page,
				'paged'                  => max( 1, $page ),
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
			]
		);

		$items = [];

		foreach ( $query->posts as $post ) {
			$post_id    = (int) $post->ID;
			$state      = (string) get_post_meta( $post_id, Sent_Secret::META_STATE, true );
			$expires_at = (int) get_post_meta( $post_id, Sent_Secret::META_EXPIRES_AT, true );

			if ( Sent_Secret::STATE_ACTIVE === $state && $expires_at > 0 && $expires_at <= $now ) {
				$state = Sent_Secret::STATE_EXPIRED;
			}

			$public_id = (string) get_post_meta( $post_id, Sent_Secret::META_PUBLIC_ID, true );

			$items[] = [
				'id'             => $post_id,
				'short_id'       => substr( $public_id, 0, 8 ),
				'created_at'     => (int) get_post_time( 'U', true, $post ),
				'expires_at'     => $expires_at,
				'state'          => $state,
				'state_label'    => Sent_Secret::state_label( $state ),
				'state_at'       => (int) get_post_meta( $post_id, Sent_Secret::META_STATE_AT, true ),
				'recipient'      => (string) get_post_meta( $post_id, Sent_Secret::META_RECIPIENT, true ),
				'notified'       => '1' === (string) get_post_meta( $post_id, Sent_Secret::META_NOTIFIED, true ),
				'has_passphrase' => '1' === (string) get_post_meta( $post_id, Sent_Secret::META_HAS_PASSPHRASE, true ),
				'size'           => (int) get_post_meta( $post_id, Sent_Secret::META_SIZE, true ),
				'ttl_minutes'    => (int) get_post_meta( $post_id, Sent_Secret::META_TTL_MINUTES, true ),

				/*
				 * Only a live secret has a link worth offering, and even then it
				 * is the keyless URL. The key was never here to put back.
				 */
				'is_live'        => Sent_Secret::STATE_ACTIVE === $state,
			];
		}

		wp_reset_postdata();

		return [
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		];
	}

	/**
	 * Delete history rows that have outlived their retention window.
	 *
	 * @param int      $batch Rows per pass.
	 * @param int|null $now   Clock override for tests.
	 *
	 * @return int Rows deleted.
	 */
	public function prune( int $batch = 200, ?int $now = null ): int {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = %s AND CAST(m.meta_value AS UNSIGNED) <= %d
				 LIMIT %d",
				Sent_Secret::META_PRUNE_AFTER,
				Sent_Secret::POST_TYPE,
				$now ?? time(),
				max( 1, $batch )
			)
		);

		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) instanceof \WP_Post ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Delete every history row, for uninstall.
	 *
	 * @param int $batch Rows per pass.
	 *
	 * @return int Rows deleted.
	 */
	public function purge_all( int $batch = 200 ): int {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT %d",
				Sent_Secret::POST_TYPE,
				max( 1, $batch )
			)
		);

		$deleted = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) instanceof \WP_Post ) {
				++$deleted;
			}
		}

		return $deleted;
	}
}
