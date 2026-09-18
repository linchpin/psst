<?php
/**
 * Which secret view, if any, the current request is.
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Helper\Ids;

/**
 * Class Route
 */
final class Route {

	public const QUERY_ID = 'psst_id';

	public const QUERY_VIEW = 'psst_view';

	/**
	 * The current secret route.
	 *
	 * @return array{view: string, id: string}|null `view` is reveal or legacy; null when this is not a secret request.
	 */
	public static function current(): ?array {
		$view = (string) get_query_var( self::QUERY_VIEW, '' );
		$id   = (string) get_query_var( self::QUERY_ID, '' );

		if ( 'legacy' === $view ) {
			return [
				'view' => 'legacy',
				'id'   => '',
			];
		}

		if ( Ids::is_public_id( $id ) ) {
			return [
				'view' => 'reveal',
				'id'   => $id,
			];
		}

		return null;
	}

	/**
	 * Whether the current request renders the create or the reveal page.
	 *
	 * @return bool
	 */
	public static function is_secret_page(): bool {
		if ( null !== self::current() ) {
			return true;
		}

		$create = (int) Settings::get( 'create_page_id' );
		$reveal = Settings::reveal_page_id();

		return ( $create > 0 && is_page( $create ) ) || ( $reveal > 0 && is_page( $reveal ) );
	}
}
