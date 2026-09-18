<?php
/**
 * The secret post type definition.
 *
 * @package Linchpin\Psst\Model\Post_Type
 */

namespace Linchpin\Psst\Model\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Secret
 */
final class Secret {

	public const POST_TYPE = 'psst_secret';

	/**
	 * The post type 1.x used. Rows of this type are legacy data to purge.
	 */
	public const LEGACY_POST_TYPE = 'secret';

	/**
	 * The lock status between an atomic claim and the hard delete.
	 */
	public const STATUS_REVEALED = 'psst_revealed';

	public const META_EXPIRES_AT = '_psst_expires_at';

	public const META_HAS_PASSPHRASE = '_psst_has_passphrase';

	public const META_TOKEN_HASH = '_psst_token_hash';

	public const META_TTL_MINUTES = '_psst_ttl_minutes';

	public const META_SIZE = '_psst_size';

	public const META_ACTION_ID = '_psst_action_id';

	public const META_VERSION = '_psst_version';

	/**
	 * The capabilities register_post_type derives, granted to administrators on
	 * activation and removed on uninstall.
	 *
	 * @return string[]
	 */
	public static function capabilities(): array {
		return [
			'edit_psst_secrets',
			'edit_others_psst_secrets',
			'edit_published_psst_secrets',
			'edit_private_psst_secrets',
			'delete_psst_secrets',
			'delete_others_psst_secrets',
			'delete_published_psst_secrets',
			'delete_private_psst_secrets',
			'read_private_psst_secrets',
			'publish_psst_secrets',
		];
	}

	/**
	 * Labels.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		$labels = [
			'name'          => _x( 'Secrets', 'post type general name', 'psst' ),
			'singular_name' => _x( 'Secret', 'post type singular name', 'psst' ),
			'menu_name'     => _x( 'Secrets', 'admin menu', 'psst' ),
		];

		/**
		 * Filters the secret post type labels.
		 *
		 * @param array<string, string> $labels Labels.
		 */
		return (array) apply_filters( 'psst_secret_labels', $labels );
	}

	/**
	 * The register_post_type() arguments.
	 *
	 * Nothing about a secret is viewable through WordPress itself: not the
	 * front end, not REST, not feeds, sitemaps, oEmbed or the admin editor. The
	 * plugin's own REST routes and the viewer block are the only readers.
	 *
	 * @return array<string, mixed>
	 */
	public static function args(): array {
		$args = [
			'labels'              => self::labels(),
			'description'         => __( 'One-time encrypted secrets. Content is ciphertext the server cannot read.', 'psst' ),
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
			'delete_with_user'    => false,
			'hierarchical'        => false,
			'supports'            => false,
			'capability_type'     => [ 'psst_secret', 'psst_secrets' ],
			'map_meta_cap'        => true,
			'capabilities'        => [
				'create_posts' => 'do_not_allow',
			],
		];

		/**
		 * Filters the secret post type arguments.
		 *
		 * @param array<string, mixed> $args register_post_type() arguments.
		 */
		return (array) apply_filters( 'psst_secret_args', $args );
	}
}
