<?php
/**
 * Server data for the psst/secret-viewer block.
 *
 * Decides the initial state from a non-consuming lookup, so a GET never touches
 * REST and never burns the secret.
 *
 * @package Linchpin\Psst\Model\Blocks
 */

namespace Linchpin\Psst\Model\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Model\Route;
use Linchpin\Psst\Model\Secret_Repository;
use Linchpin\Psst\Model\Settings;

/**
 * Class Secret_Viewer
 */
final class Secret_Viewer {

	/**
	 * Attribute defaults, mirrored from block.json.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return array<string, mixed>
	 */
	public static function attributes( array $attributes ): array {
		return [
			'heading' => (string) ( $attributes['heading'] ?? __( 'Your Shared Secret', 'psst' ) ),
			'intro'   => (string) ( $attributes['intro'] ?? __( 'Once you click view, your secret will be revealed. Once viewed it will be removed.', 'psst' ) ),
		];
	}

	/**
	 * Resolve the request to an initial status.
	 *
	 * Also sets the HTTP status: 404 for anything that is not an active secret,
	 * 410 for a 1.x link.
	 *
	 * @return array{status: string, id: string}
	 */
	public static function resolve(): array {
		$route = Route::current();

		if ( null === $route ) {
			if ( ! is_admin() && ! wp_is_json_request() && ! headers_sent() ) {
				status_header( 404 );
			}

			return [
				'status' => 'missing',
				'id'     => '',
			];
		}

		if ( 'legacy' === $route['view'] ) {
			return [
				'status' => 'gone',
				'id'     => '',
			];
		}

		$post = ( new Secret_Repository() )->find_active( $route['id'] );

		if ( ! $post ) {
			if ( ! headers_sent() ) {
				status_header( 404 );
			}

			return [
				'status' => 'gone',
				'id'     => $route['id'],
			];
		}

		return [
			'status' => ( new Secret_Repository() )->has_passphrase( $post->ID ) ? 'protected' : 'ready',
			'id'     => $route['id'],
		];
	}

	/**
	 * The initial Interactivity context.
	 *
	 * @param array{status: string, id: string} $resolved From resolve().
	 *
	 * @return array<string, mixed>
	 */
	public static function context( array $resolved ): array {
		return [
			'id'               => $resolved['id'],
			'status'           => $resolved['status'],
			'passphrase'       => '',
			'plaintext'        => '',
			'error'            => '',
			'copied'           => false,
			'warningDismissed' => false,
			'createUrl'        => Settings::get_create_url(),
		];
	}
}
