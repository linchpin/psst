<?php
/**
 * The sent-secret history post type.
 *
 * A secret row is hard-deleted the instant it is read, so a sender's history
 * cannot be a query over secrets — by the time it would be useful the rows are
 * gone. This is the durable record that outlives them.
 *
 * It holds metadata and nothing else: when the secret was made, when it
 * expires, what happened to it, roughly how big it was, and who the sender said
 * it was for. It never holds the ciphertext, the key, the pass phrase, or the
 * management token, because none of those are things this plugin is willing to
 * keep.
 *
 * @package Linchpin\Psst\Model\Post_Type
 */

namespace Linchpin\Psst\Model\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sent_Secret
 */
final class Sent_Secret {

	public const POST_TYPE = 'psst_sent';

	/**
	 * The secret's public identifier, which is how a history row is matched to
	 * the secret it describes while that secret still exists.
	 *
	 * This is the same identifier the admin secrets list already shows. It is
	 * not sufficient to read anything: the key never reaches the server, and
	 * once the secret is gone the identifier is inert.
	 */
	public const META_PUBLIC_ID = '_psst_public_id';

	public const META_EXPIRES_AT = '_psst_expires_at';

	public const META_TTL_MINUTES = '_psst_ttl_minutes';

	public const META_SIZE = '_psst_size';

	public const META_HAS_PASSPHRASE = '_psst_has_passphrase';

	/**
	 * What the sender typed in the recipient field. Free text, which may be an
	 * address or may be "Dave on the infra team".
	 */
	public const META_RECIPIENT = '_psst_recipient';

	/**
	 * Whether Psst emailed the link, as opposed to the sender copying it.
	 */
	public const META_NOTIFIED = '_psst_notified';

	public const META_STATE = '_psst_state';

	public const META_STATE_AT = '_psst_state_at';

	/**
	 * When the history row itself should be deleted, independent of the secret.
	 */
	public const META_PRUNE_AFTER = '_psst_prune_after';

	/**
	 * Live, unread, unexpired.
	 */
	public const STATE_ACTIVE = 'active';

	/**
	 * Read by the recipient, and therefore destroyed.
	 */
	public const STATE_REVEALED = 'revealed';

	/**
	 * Destroyed by the sender or an administrator before it was read.
	 */
	public const STATE_SHREDDED = 'shredded';

	/**
	 * Reached its expiry without being read.
	 */
	public const STATE_EXPIRED = 'expired';

	/**
	 * Every state a history row may report.
	 *
	 * @return string[]
	 */
	public static function states(): array {
		return [ self::STATE_ACTIVE, self::STATE_REVEALED, self::STATE_SHREDDED, self::STATE_EXPIRED ];
	}

	/**
	 * Map a `psst_secret_destroyed` reason onto a history state.
	 *
	 * `admin` and `sweep` both mean somebody or something removed a secret that
	 * had not been read, which is what shredded already describes. `uninstall`
	 * never reaches here because the history goes with it.
	 *
	 * @param string $reason The reason passed to psst_secret_destroyed.
	 *
	 * @return string One of the STATE_* constants.
	 */
	public static function state_for_reason( string $reason ): string {
		return match ( $reason ) {
			'revealed' => self::STATE_REVEALED,
			'expired'  => self::STATE_EXPIRED,
			default    => self::STATE_SHREDDED,
		};
	}

	/**
	 * A human label for a state.
	 *
	 * @param string $state One of the STATE_* constants.
	 *
	 * @return string
	 */
	public static function state_label( string $state ): string {
		return match ( $state ) {
			self::STATE_REVEALED => __( 'Viewed', 'psst' ),
			self::STATE_SHREDDED => __( 'Shredded', 'psst' ),
			self::STATE_EXPIRED  => __( 'Expired', 'psst' ),
			default              => __( 'Waiting to be read', 'psst' ),
		};
	}

	/**
	 * The register_post_type() arguments.
	 *
	 * Invisible everywhere WordPress would otherwise surface a post type. The
	 * account block is the only reader, and it queries by author.
	 *
	 * `delete_with_user` is true, which is the opposite of the secret post type:
	 * a secret has no author to be deleted with, whereas history is entirely
	 * about its author and has no reason to outlive them.
	 *
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		$args = [
			'labels'              => [
				'name'          => _x( 'Sent Secrets', 'post type general name', 'psst' ),
				'singular_name' => _x( 'Sent Secret', 'post type singular name', 'psst' ),
			],
			'description'         => __( 'Metadata about secrets a user has sent. Never contains a secret.', 'psst' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => false,
			'delete_with_user'    => true,
			'hierarchical'        => false,
			'supports'            => [ 'author' ],
			'capability_type'     => [ 'psst_secret', 'psst_secrets' ],
			'map_meta_cap'        => true,
			'capabilities'        => [
				'create_posts' => 'do_not_allow',
			],
		];

		/**
		 * Filters the sent-secret post type arguments.
		 *
		 * @param array<string, mixed> $args register_post_type() arguments.
		 */
		return (array) apply_filters( 'psst_sent_secret_args', $args );
	}
}
