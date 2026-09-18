<?php
/**
 * What insert() hands back: everything the sender needs, exactly once.
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

/**
 * Class Created_Secret
 */
final class Created_Secret {

	/**
	 * Constructor.
	 *
	 * @param int    $post_id      The post.
	 * @param string $public_id    The URL identifier.
	 * @param string $manage_token The sender's shred token. Never stored in this form.
	 * @param int    $expires_at   Unix timestamp, UTC.
	 * @param int    $ttl_minutes  The ttl chosen.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly string $public_id,
		public readonly string $manage_token,
		public readonly int $expires_at,
		public readonly int $ttl_minutes,
	) {}
}
