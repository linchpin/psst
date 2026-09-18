<?php
/**
 * Register the secret post type and its lock status.
 *
 * @package Linchpin\Psst\Controller
 */

namespace Linchpin\Psst\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Model\Post_Type\Secret;

/**
 * Class Post_Type
 */
class Post_Type implements Controller_Interface {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'init', [ $this, 'register' ] );
		add_filter( 'pre_trash_post', [ $this, 'never_trash' ], 10, 2 );
		add_filter( 'comments_open', [ $this, 'closed' ], 10, 2 );
		add_filter( 'pings_open', [ $this, 'closed' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'never_output_ciphertext' ], 0 );
	}

	/**
	 * Register the post type and the revealed status.
	 *
	 * @return void
	 */
	public function register(): void {
		register_post_type( Secret::POST_TYPE, Secret::args() );

		register_post_status(
			Secret::STATUS_REVEALED,
			[
				'label'                     => _x( 'Revealed', 'secret status', 'psst' ),
				'internal'                  => true,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
			]
		);
	}

	/**
	 * A secret is never trashed; any trash attempt becomes a hard delete.
	 *
	 * @param bool|null $trash Whether to short-circuit.
	 * @param \WP_Post  $post  The post.
	 *
	 * @return bool|null
	 */
	public function never_trash( $trash, $post ) {
		if ( Secret::POST_TYPE === $post->post_type ) {
			wp_delete_post( $post->ID, true );

			return false;
		}

		return $trash;
	}

	/**
	 * Comments and pings are always closed on a secret.
	 *
	 * @param bool $open    Whether open.
	 * @param int  $post_id The post.
	 *
	 * @return bool
	 */
	public function closed( $open, $post_id ): bool {
		return Secret::POST_TYPE === get_post_type( (int) $post_id ) ? false : (bool) $open;
	}

	/**
	 * Belt and braces: the_content never carries an envelope, whatever asks.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	public function never_output_ciphertext( $content ) {
		return Secret::POST_TYPE === get_post_type() ? '' : $content;
	}
}
