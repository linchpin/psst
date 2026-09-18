<?php
/**
 * Plugin settings: schema, defaults, sanitizing, and derived values.
 *
 * One option, every key in the schema. A key missing from the schema makes
 * the REST settings endpoint return the whole option as null.
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
final class Settings {

	public const OPTION = 'psst_settings';

	/**
	 * The Turnstile secret lives in its own option, never in the REST-readable one.
	 */
	public const OPTION_TURNSTILE_SECRET = 'psst_turnstile_secret';

	public const OPTION_FLUSH = 'psst_flush_rewrite_rules';

	public const OPTION_DB_VERSION = 'psst_db_version';

	/**
	 * Legacy option names 1.x wrote, removed on upgrade.
	 */
	public const LEGACY_OPTIONS = [ 'psst_options', 'psst_version', 'psst_activation' ];

	/**
	 * Cached settings for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'ttl_options'                       => array_keys( Ttl::catalog() ),
			'ttl_default'                       => Ttl::DEFAULT_MINUTES,
			'max_plaintext_bytes'               => 32768,
			'rate_limit_create_per_hour'        => 10,
			'rate_limit_create_global_per_hour' => 300,
			'rate_limit_reveal_per_hour'        => 60,
			'turnstile_site_key'                => '',
			'trusted_proxy_header'              => '',
			'delete_on_uninstall'               => true,
			'create_page_id'                    => 0,
			'reveal_page_id'                    => 0,
		];
	}

	/**
	 * The REST schema, one entry per key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		return [
			'ttl_options'                       => [
				'type'  => 'array',
				'items' => [ 'type' => 'integer' ],
			],
			'ttl_default'                       => [ 'type' => 'integer' ],
			'max_plaintext_bytes'               => [
				'type'    => 'integer',
				'minimum' => 1024,
				'maximum' => 262144,
			],
			'rate_limit_create_per_hour'        => [
				'type'    => 'integer',
				'minimum' => 0,
			],
			'rate_limit_create_global_per_hour' => [
				'type'    => 'integer',
				'minimum' => 0,
			],
			'rate_limit_reveal_per_hour'        => [
				'type'    => 'integer',
				'minimum' => 0,
			],
			'turnstile_site_key'                => [ 'type' => 'string' ],
			'trusted_proxy_header'              => [
				'type' => 'string',
				'enum' => [ '', 'cloudflare' ],
			],
			'delete_on_uninstall'               => [ 'type' => 'boolean' ],
			'create_page_id'                    => [ 'type' => 'integer' ],
			'reveal_page_id'                    => [ 'type' => 'integer' ],
		];
	}

	/**
	 * Sanitize an incoming settings array against the schema.
	 *
	 * Unknown keys are dropped; missing keys keep their stored value so a
	 * partial save never wipes the rest.
	 *
	 * @param mixed $value The incoming value.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $value ): array {
		$current  = self::all();
		$defaults = self::defaults();
		$schema   = self::schema();
		$incoming = is_array( $value ) ? $value : [];
		$clean    = [];

		foreach ( $schema as $key => $rules ) {
			if ( ! array_key_exists( $key, $incoming ) ) {
				$clean[ $key ] = $current[ $key ] ?? $defaults[ $key ];
				continue;
			}

			$raw = $incoming[ $key ];

			switch ( $rules['type'] ) {
				case 'boolean':
					$clean[ $key ] = rest_sanitize_boolean( $raw );
					break;

				case 'integer':
					$int = (int) $raw;

					if ( isset( $rules['minimum'] ) ) {
						$int = max( (int) $rules['minimum'], $int );
					}

					if ( isset( $rules['maximum'] ) ) {
						$int = min( (int) $rules['maximum'], $int );
					}

					$clean[ $key ] = $int;
					break;

				case 'array':
					$clean[ $key ] = array_values( array_unique( array_map( 'intval', array_filter( (array) $raw, 'is_numeric' ) ) ) );
					break;

				default:
					$string = sanitize_text_field( (string) $raw );

					if ( isset( $rules['enum'] ) && ! in_array( $string, $rules['enum'], true ) ) {
						$string = (string) $defaults[ $key ];
					}

					$clean[ $key ] = $string;
			}
		}

		// The enabled ttl set must be a non-empty subset of the catalog.
		$clean['ttl_options'] = array_values( array_intersect( $clean['ttl_options'], array_keys( Ttl::catalog() ) ) );

		if ( empty( $clean['ttl_options'] ) ) {
			$clean['ttl_options'] = $defaults['ttl_options'];
		}

		$clean['ttl_default'] = Ttl::default_minutes( (int) $clean['ttl_default'], $clean['ttl_options'] );

		if ( (int) ( $current['create_page_id'] ?? 0 ) !== $clean['create_page_id'] || (int) ( $current['reveal_page_id'] ?? 0 ) !== $clean['reveal_page_id'] ) {
			update_option( self::OPTION_FLUSH, 1 );
		}

		self::$cache = null;

		return $clean;
	}

	/**
	 * Register the option with WordPress. Runs on init.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_setting(
			'psst',
			self::OPTION,
			[
				'type'              => 'object',
				'description'       => __( 'Psst settings.', 'psst' ),
				'sanitize_callback' => [ self::class, 'sanitize' ],
				'default'           => self::defaults(),
				'show_in_rest'      => [
					'schema' => [
						'type'       => 'object',
						'properties' => self::schema(),
					],
				],
			]
		);
	}

	/**
	 * Every setting, defaults filled in.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, [] );

		self::$cache = array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::schema() ) : [] );

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key The key.
	 *
	 * @return mixed
	 */
	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? self::defaults()[ $key ] ?? null;
	}

	/**
	 * Save a full settings array.
	 *
	 * @param array<string, mixed> $settings The settings.
	 *
	 * @return array<string, mixed> What was stored.
	 */
	public static function save( array $settings ): array {
		$clean = self::sanitize( $settings );

		update_option( self::OPTION, $clean, true );

		self::$cache = null;

		return self::all();
	}

	/**
	 * Write the defaults if nothing is stored yet.
	 *
	 * @return void
	 */
	public static function seed_defaults(): void {
		add_option( self::OPTION, self::defaults(), '', true );
		self::$cache = null;
	}

	/**
	 * Forget the request cache.
	 *
	 * @return void
	 */
	public static function forget(): void {
		self::$cache = null;
	}

	/**
	 * The ttl choices offered to a sender.
	 *
	 * @return array<int, string>
	 */
	public static function ttl_options(): array {
		return Ttl::options( (array) self::get( 'ttl_options' ) );
	}

	/**
	 * The default ttl, guaranteed to be an offered choice.
	 *
	 * @return int
	 */
	public static function ttl_default(): int {
		return Ttl::default_minutes( (int) self::get( 'ttl_default' ), (array) self::get( 'ttl_options' ) );
	}

	/**
	 * The plaintext size cap.
	 *
	 * @return int
	 */
	public static function max_plaintext_bytes(): int {
		/**
		 * Filters the maximum plaintext size in bytes.
		 *
		 * @param int $bytes The configured cap.
		 */
		return max( 1024, (int) apply_filters( 'psst_max_plaintext_bytes', (int) self::get( 'max_plaintext_bytes' ) ) );
	}

	/**
	 * The URL of the page that creates secrets.
	 *
	 * @return string
	 */
	public static function get_create_url(): string {
		$page_id = (int) self::get( 'create_page_id' );
		$url     = $page_id > 0 ? get_permalink( $page_id ) : false;

		if ( ! is_string( $url ) || '' === $url ) {
			$url = home_url( '/' );
		}

		/**
		 * Filters the URL a visitor is sent to when they want to create a secret.
		 *
		 * @param string $url The URL.
		 */
		return (string) apply_filters( 'psst_create_page_url', $url );
	}

	/**
	 * The page that hosts the viewer block.
	 *
	 * @return int
	 */
	public static function reveal_page_id(): int {
		return (int) self::get( 'reveal_page_id' );
	}

	/**
	 * The Turnstile secret key: the constant wins, then the write-only option.
	 *
	 * @return string
	 */
	public static function turnstile_secret(): string {
		if ( defined( 'PSST_TURNSTILE_SECRET_KEY' ) && is_string( PSST_TURNSTILE_SECRET_KEY ) ) {
			return PSST_TURNSTILE_SECRET_KEY;
		}

		return (string) get_option( self::OPTION_TURNSTILE_SECRET, '' );
	}

	/**
	 * Whether Turnstile is fully configured.
	 *
	 * @return bool
	 */
	public static function turnstile_enabled(): bool {
		return '' !== (string) self::get( 'turnstile_site_key' ) && '' !== self::turnstile_secret();
	}

	/**
	 * What the browser needs: the same array the REST config route returns and
	 * the blocks receive through wp_interactivity_config().
	 *
	 * @return array<string, mixed>
	 */
	public static function client_config(): array {
		$config = [
			'restUrl'          => esc_url_raw( rest_url( 'psst/v1/' ) ),
			'createUrl'        => self::get_create_url(),
			'ttlOptions'       => self::ttl_options(),
			'ttlDefault'       => self::ttl_default(),
			'maxPlaintext'     => self::max_plaintext_bytes(),
			'kdfIterations'    => Envelope::MIN_KDF_ITERATIONS,
			'turnstileSiteKey' => self::turnstile_enabled() ? (string) self::get( 'turnstile_site_key' ) : '',
			'locale'           => str_replace( '_', '-', get_locale() ),
		];

		/**
		 * Filters the configuration handed to the blocks and the /config route.
		 *
		 * @param array<string, mixed> $config Client configuration.
		 */
		return (array) apply_filters( 'psst_client_config', $config );
	}
}
