<?php
/**
 * Server data for the psst/secret-form block.
 *
 * @package Linchpin\Psst\Model\Blocks
 */

namespace Linchpin\Psst\Model\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Model\Settings;
use Linchpin\Psst\Model\Ttl;

/**
 * Class Secret_Form
 */
final class Secret_Form {

	/**
	 * Attribute defaults, mirrored from block.json.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return array<string, mixed>
	 */
	public static function attributes( array $attributes ): array {
		return [
			'defaultExpiry'  => (int) ( $attributes['defaultExpiry'] ?? 0 ),
			'showPassphrase' => (bool) ( $attributes['showPassphrase'] ?? true ),
			'showTip'        => (bool) ( $attributes['showTip'] ?? true ),
			'tipHeading'     => (string) ( $attributes['tipHeading'] ?? __( 'Quick Tip!', 'psst' ) ),
			'tipText'        => (string) ( $attributes['tipText'] ?? __( 'If you would like to secure your secret even further you can provide a pass phrase.', 'psst' ) ),
		];
	}

	/**
	 * The ttl choices with the block's default applied.
	 *
	 * @param int $default_expiry The block's default, or 0 for the site default.
	 *
	 * @return array{options: array<int, string>, selected: int}
	 */
	public static function expiry( int $default_expiry ): array {
		$options  = Settings::ttl_options();
		$selected = $default_expiry > 0 && array_key_exists( $default_expiry, $options )
			? $default_expiry
			: Ttl::default_minutes( Settings::ttl_default(), array_keys( $options ) );

		return [
			'options'  => $options,
			'selected' => $selected,
		];
	}

	/**
	 * The initial Interactivity context.
	 *
	 * The key and the management token never enter this array: view.js holds
	 * them in module scope. `shareUrl` is the only key-bearing value.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return array<string, mixed>
	 */
	public static function context( array $attributes ): array {
		$attributes = self::attributes( $attributes );
		$expiry     = self::expiry( $attributes['defaultExpiry'] );

		return [
			'status'         => 'idle',
			'message'        => '',
			'passphrase'     => '',
			'expiry'         => $expiry['selected'],
			'error'          => '',
			'shareUrl'       => '',
			'expiresLabel'   => '',
			'copied'         => false,
			'tipDismissed'   => false,
			'hp'             => '',
			'showPassphrase' => $attributes['showPassphrase'],
			'maxLength'      => Settings::max_plaintext_bytes(),
			'turnstile'      => Settings::turnstile_enabled(),
		];
	}
}
