<?php
/**
 * Every read and write of a secret row.
 *
 * Nothing outside this class touches wp_posts for the post type, and nothing
 * in it ever logs or returns an envelope except `claim_and_read()`.
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Helper\Ids;
use Linchpin\Psst\Model\Post_Type\Secret;

/**
 * Class Secret_Repository
 */
final class Secret_Repository {

	/**
	 * Store a new secret.
	 *
	 * @param Envelope $envelope    The validated envelope.
	 * @param int      $ttl_minutes The chosen ttl.
	 * @param int|null $now         Clock override for tests.
	 *
	 * @return Created_Secret|\WP_Error
	 */
	public function insert( Envelope $envelope, int $ttl_minutes, ?int $now = null ): Created_Secret|\WP_Error {
		$public_id = $this->unique_public_id();

		if ( null === $public_id ) {
			return new \WP_Error( 'psst_storage_failed', __( 'Could not allocate an identifier for the secret.', 'psst' ), [ 'status' => 500 ] );
		}

		$token      = Ids::manage_token();
		$expires_at = Ttl::expires_at( $ttl_minutes, $now );

		/*
		 * Anonymous callers have no unfiltered_html, so wp_insert_post would run
		 * the envelope through kses. It is JSON, not HTML; take kses off for the
		 * one call and put it straight back.
		 */
		kses_remove_filters();

		$post_id = wp_insert_post(
			[
				'post_type'      => Secret::POST_TYPE,
				'post_status'    => 'publish',
				'post_title'     => '',
				'post_content'   => wp_slash( $envelope->to_json() ),
				'post_author'    => 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => [
					Secret::META_EXPIRES_AT     => $expires_at,
					Secret::META_HAS_PASSPHRASE => $envelope->has_passphrase ? '1' : '0',
					Secret::META_TOKEN_HASH     => Ids::hash_token( $token ),
					Secret::META_TTL_MINUTES    => $ttl_minutes,
					Secret::META_SIZE           => $envelope->size(),
					Secret::META_VERSION        => Envelope::VERSION,
				],
			],
			true
		);

		kses_init_filters();

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error( 'psst_storage_failed', __( 'The secret could not be stored.', 'psst' ), [ 'status' => 500 ] );
		}

		/*
		 * The id goes in post_name directly. wp_insert_post would run it through
		 * sanitize_title(), which lowercases, and base64url is case-sensitive.
		 */
		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->posts,
			[ 'post_name' => $public_id ],
			[ 'ID' => (int) $post_id ],
			[ '%s' ],
			[ '%d' ]
		);

		clean_post_cache( (int) $post_id );

		if ( 1 !== $updated || get_post_field( 'post_name', (int) $post_id, 'raw' ) !== $public_id ) {
			wp_delete_post( (int) $post_id, true );

			return new \WP_Error( 'psst_storage_failed', __( 'The secret could not be stored.', 'psst' ), [ 'status' => 500 ] );
		}

		return new Created_Secret( (int) $post_id, $public_id, $token, $expires_at, $ttl_minutes );
	}

	/**
	 * An active, unexpired secret by public id. Never consumes.
	 *
	 * An expired row found here is destroyed on the way out, which is what makes
	 * expiry hold even when the scheduler is behind.
	 *
	 * @param string   $public_id The identifier.
	 * @param int|null $now       Clock override for tests.
	 *
	 * @return \WP_Post|null
	 */
	public function find_active( string $public_id, ?int $now = null ): ?\WP_Post {
		$post = $this->find_any( $public_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		if ( $this->expires_at( $post->ID ) <= ( $now ?? time() ) ) {
			$this->destroy( $post->ID, 'expired' );

			return null;
		}

		return $post;
	}

	/**
	 * A secret row by public id in any status.
	 *
	 * @param string $public_id The identifier.
	 *
	 * @return \WP_Post|null
	 */
	public function find_any( string $public_id ): ?\WP_Post {
		if ( ! Ids::is_public_id( $public_id ) ) {
			return null;
		}

		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s LIMIT 1",
				Secret::POST_TYPE,
				$public_id
			)
		);

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( (int) $post_id );

		// The SQL comparison may be case-insensitive; the id is not.
		if ( ! ( $post instanceof \WP_Post ) || $post->post_name !== $public_id ) {
			return null;
		}

		return $post;
	}

	/**
	 * The one consuming read.
	 *
	 * The claim is a conditional UPDATE, so under concurrent requests exactly one
	 * caller flips the row out of `publish` and gets the envelope; every other
	 * caller gets null. The row is then hard-deleted.
	 *
	 * @param int $post_id The post.
	 *
	 * @return Envelope|null
	 */
	public function claim_and_read( int $post_id ): ?Envelope {
		global $wpdb;

		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_status = %s, post_modified_gmt = %s WHERE ID = %d AND post_type = %s AND post_status = 'publish'",
				Secret::STATUS_REVEALED,
				current_time( 'mysql', true ),
				$post_id,
				Secret::POST_TYPE
			)
		);

		clean_post_cache( $post_id );

		if ( 1 !== $rows ) {
			return null;
		}

		$json     = (string) get_post_field( 'post_content', $post_id, 'raw' );
		$envelope = Envelope::from_json( $json );

		$this->destroy( $post_id, 'revealed' );

		return $envelope;
	}

	/**
	 * Hard-delete a secret.
	 *
	 * @param int    $post_id The post.
	 * @param string $reason  One of expired, revealed, shredded, admin, sweep, uninstall.
	 *
	 * @return bool
	 */
	public function destroy( int $post_id, string $reason ): bool {
		if ( Secret::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}

		$public_id = (string) get_post_field( 'post_name', $post_id, 'raw' );

		/**
		 * Fires before a secret is deleted, for any reason.
		 *
		 * Receives identifiers only; the envelope is never passed to a hook.
		 *
		 * @param int    $post_id   The post.
		 * @param string $reason    Why.
		 * @param string $public_id The URL identifier.
		 */
		do_action( 'psst_secret_destroyed', $post_id, $reason, $public_id );

		$deleted = wp_delete_post( $post_id, true );

		return $deleted instanceof \WP_Post;
	}

	/**
	 * Shred by public id with the sender's token.
	 *
	 * @param string $public_id The identifier.
	 * @param string $token     The presented token.
	 *
	 * @return bool True when a secret was deleted.
	 */
	public function shred_with_token( string $public_id, string $token ): bool {
		$post = $this->find_any( $public_id );

		if ( ! $post ) {
			return false;
		}

		$stored = (string) get_post_meta( $post->ID, Secret::META_TOKEN_HASH, true );

		if ( ! Ids::token_matches( $stored, $token ) ) {
			return false;
		}

		return $this->destroy( $post->ID, 'shredded' );
	}

	/**
	 * Whether a token unlocks a secret, without acting on it.
	 *
	 * @param \WP_Post $post  The secret.
	 * @param mixed    $token The presented token.
	 *
	 * @return bool
	 */
	public function token_matches( \WP_Post $post, mixed $token ): bool {
		return Ids::token_matches( (string) get_post_meta( $post->ID, Secret::META_TOKEN_HASH, true ), $token );
	}

	/**
	 * The expiry timestamp of a secret.
	 *
	 * @param int $post_id The post.
	 *
	 * @return int Zero when unset, which reads as already expired.
	 */
	public function expires_at( int $post_id ): int {
		return (int) get_post_meta( $post_id, Secret::META_EXPIRES_AT, true );
	}

	/**
	 * Whether a secret needs a passphrase.
	 *
	 * @param int $post_id The post.
	 *
	 * @return bool
	 */
	public function has_passphrase( int $post_id ): bool {
		return '1' === (string) get_post_meta( $post_id, Secret::META_HAS_PASSPHRASE, true );
	}

	/**
	 * Delete everything that should already be gone.
	 *
	 * Expired rows, rows stuck in the revealed lock, rows with no expiry, and
	 * legacy 1.x rows. Idempotent; safe to run any time.
	 *
	 * @param int      $batch Rows per pass.
	 * @param int|null $now   Clock override for tests.
	 *
	 * @return int Rows deleted.
	 */
	public function sweep( int $batch = 200, ?int $now = null ): int {
		global $wpdb;

		$now     = $now ?? time();
		$deleted = 0;
		$batch   = max( 1, $batch );

		// Expired.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND CAST(m.meta_value AS UNSIGNED) <= %d LIMIT %d",
				Secret::META_EXPIRES_AT,
				Secret::POST_TYPE,
				$now,
				$batch
			)
		);

		foreach ( $ids as $id ) {
			$deleted += (int) $this->destroy( (int) $id, 'expired' );
		}

		// Stuck in the reveal lock for more than five minutes.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_modified_gmt < %s LIMIT %d",
				Secret::POST_TYPE,
				Secret::STATUS_REVEALED,
				gmdate( 'Y-m-d H:i:s', $now - 5 * MINUTE_IN_SECONDS ),
				$batch
			)
		);

		foreach ( $ids as $id ) {
			$deleted += (int) $this->destroy( (int) $id, 'sweep' );
		}

		// No expiry at all.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND m.meta_id IS NULL LIMIT %d",
				Secret::META_EXPIRES_AT,
				Secret::POST_TYPE,
				$batch
			)
		);

		foreach ( $ids as $id ) {
			$deleted += (int) $this->destroy( (int) $id, 'sweep' );
		}

		$deleted += $this->purge_legacy( $batch );

		return $deleted;
	}

	/**
	 * Delete 1.x `secret` rows.
	 *
	 * @param int $batch Rows per pass.
	 *
	 * @return int Rows deleted.
	 */
	public function purge_legacy( int $batch = 200 ): int {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT %d",
				Secret::LEGACY_POST_TYPE,
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
	 * Whether any legacy rows remain.
	 *
	 * @return bool
	 */
	public function has_legacy(): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1", Secret::LEGACY_POST_TYPE )
		);
	}

	/**
	 * Active secret count.
	 *
	 * @return int
	 */
	public function count_active(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", Secret::POST_TYPE )
		);
	}

	/**
	 * Every active secret's id, for re-arming the scheduler.
	 *
	 * @return int[]
	 */
	public function active_ids(): array {
		global $wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", Secret::POST_TYPE )
			)
		);
	}

	/**
	 * A page of secrets as metadata rows for the admin list. Never content.
	 *
	 * @param int    $page     1-based page.
	 * @param int    $per_page Rows per page.
	 * @param string $orderby  One of created, expires.
	 * @param string $order    asc or desc.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function list_for_admin( int $page, int $per_page, string $orderby = 'created', string $order = 'desc' ): array {
		global $wpdb;

		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;
		$order    = 'asc' === strtolower( $order ) ? 'ASC' : 'DESC';

		$order_sql = 'expires' === $orderby
			? "CAST(m.meta_value AS UNSIGNED) {$order}"
			: "p.post_date_gmt {$order}";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order_sql is built from two allow-listed literals above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_name, p.post_status, p.post_date_gmt, m.meta_value AS expires_at
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = %s
				 ORDER BY {$order_sql}
				 LIMIT %d OFFSET %d",
				Secret::META_EXPIRES_AT,
				Secret::POST_TYPE,
				$per_page,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", Secret::POST_TYPE )
		);

		$items = [];
		$now   = time();

		foreach ( (array) $rows as $row ) {
			$post_id    = (int) $row->ID;
			$expires_at = (int) $row->expires_at;

			$items[] = [
				'id'             => $post_id,
				'public_id'      => (string) $row->post_name,
				'short_id'       => substr( (string) $row->post_name, 0, 8 ),
				'created_at'     => gmdate( 'c', strtotime( (string) $row->post_date_gmt . ' UTC' ) ),
				'expires_at'     => $expires_at > 0 ? gmdate( 'c', $expires_at ) : null,
				'expired'        => $expires_at <= $now,
				'status'         => Secret::STATUS_REVEALED === $row->post_status ? 'pending_delete' : ( $expires_at <= $now ? 'expired' : 'active' ),
				'has_passphrase' => $this->has_passphrase( $post_id ),
				'size'           => (int) get_post_meta( $post_id, Secret::META_SIZE, true ),
				'ttl_minutes'    => (int) get_post_meta( $post_id, Secret::META_TTL_MINUTES, true ),
			];
		}

		return [
			'items' => $items,
			'total' => $total,
		];
	}

	/**
	 * A public id no existing row uses.
	 *
	 * @return string|null Null after repeated collisions, which would mean random_bytes is broken.
	 */
	private function unique_public_id(): ?string {
		global $wpdb;

		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$candidate = Ids::public_id();

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s LIMIT 1",
					Secret::POST_TYPE,
					$candidate
				)
			);

			if ( ! $exists ) {
				return $candidate;
			}
		}

		return null;
	}
}
