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

			/*
			 * The account layer. Every one of these is off, because the plugin
			 * ships to wordpress.org and an install that upgrades into a front
			 * end login, a locked wp-admin or a new data store it never asked
			 * for is an install that has been broken by an update.
			 */
			'accounts_enabled'                  => false,
			'allow_registration'                => false,
			'require_login_to_create'           => false,
			'block_admin_access'                => false,
			'history_enabled'                   => false,
			'history_retention_days'            => 30,
			'email_delivery_enabled'            => false,
			'login_page_id'                     => 0,
			'register_page_id'                  => 0,
			'account_page_id'                   => 0,
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
			'accounts_enabled'                  => [ 'type' => 'boolean' ],
			'allow_registration'                => [ 'type' => 'boolean' ],
			'require_login_to_create'           => [ 'type' => 'boolean' ],
			'block_admin_access'                => [ 'type' => 'boolean' ],
			'history_enabled'                   => [ 'type' => 'boolean' ],
			'history_retention_days'            => [
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 3650,
			],
			'email_delivery_enabled'            => [ 'type' => 'boolean' ],
			'login_page_id'                     => [ 'type' => 'integer' ],
			'register_page_id'                  => [ 'type' => 'integer' ],
			'account_page_id'                   => [ 'type' => 'integer' ],
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
	 * The master switch for everything account-related.
	 *
	 * Login, registration, the account area, the wp-admin lockout, the sent
	 * history and email delivery all check this first, so one setting turns the
	 * whole layer off and the plugin behaves exactly as it did before it existed.
	 *
	 * @return bool
	 */
	public static function accounts_enabled(): bool {
		/**
		 * Filters whether the front end account layer is active.
		 *
		 * @param bool $enabled The stored setting.
		 */
		return (bool) apply_filters( 'psst_accounts_enabled', (bool) self::get( 'accounts_enabled' ) );
	}

	/**
	 * Whether a visitor may create an account from the front end.
	 *
	 * WordPress's own switch still wins: a site (or, on multisite, a network)
	 * that has closed registration stays closed no matter what Psst is set to.
	 *
	 * @return bool
	 */
	public static function registration_open(): bool {
		if ( ! self::accounts_enabled() || ! (bool) self::get( 'allow_registration' ) ) {
			return false;
		}

		$core_allows = is_multisite()
			? in_array( (string) get_site_option( 'registration', 'none' ), [ 'user', 'all' ], true )
			: (bool) get_option( 'users_can_register' );

		/**
		 * Filters whether front end registration is offered.
		 *
		 * @param bool $open        Psst's setting and WordPress's, both satisfied.
		 * @param bool $core_allows What WordPress alone says.
		 */
		return (bool) apply_filters( 'psst_registration_open', $core_allows, $core_allows );
	}

	/**
	 * Whether creating a secret requires a logged-in user.
	 *
	 * @return bool
	 */
	public static function require_login_to_create(): bool {
		return self::accounts_enabled() && (bool) self::get( 'require_login_to_create' );
	}

	/**
	 * Whether sent-secret metadata is recorded for logged-in senders.
	 *
	 * @return bool
	 */
	public static function history_enabled(): bool {
		return self::accounts_enabled() && (bool) self::get( 'history_enabled' );
	}

	/**
	 * How long a history row is kept, in seconds.
	 *
	 * @return int
	 */
	public static function history_retention(): int {
		return max( 1, (int) self::get( 'history_retention_days' ) ) * DAY_IN_SECONDS;
	}

	/**
	 * Whether Psst may email a share link to a recipient.
	 *
	 * @return bool
	 */
	public static function email_delivery_enabled(): bool {
		return self::accounts_enabled() && (bool) self::get( 'email_delivery_enabled' );
	}

	/**
	 * The permalink of one of the account pages, or the empty string.
	 *
	 * @param string $key One of login_page_id, register_page_id, account_page_id.
	 *
	 * @return string
	 */
	public static function page_url( string $key ): string {
		$page_id = (int) self::get( $key );

		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * The front end login page, when one is configured and accounts are on.
	 *
	 * @return string Empty when the front end login is not in play, which every
	 *                caller reads as "leave core's wp-login.php alone".
	 */
	public static function get_login_url(): string {
		return self::accounts_enabled() ? self::page_url( 'login_page_id' ) : '';
	}

	/**
	 * The front end registration page.
	 *
	 * @return string
	 */
	public static function get_register_url(): string {
		return self::registration_open() ? self::page_url( 'register_page_id' ) : '';
	}

	/**
	 * The front end account page.
	 *
	 * @return string
	 */
	public static function get_account_url(): string {
		return self::accounts_enabled() ? self::page_url( 'account_page_id' ) : '';
	}

	/**
	 * The wp-config.php constants that override the two Turnstile keys.
	 *
	 * Either may be set on its own. A constant beats the stored value, and the
	 * admin screen locks the matching field while it is defined.
	 */
	public const CONSTANT_TURNSTILE_SITE_KEY   = 'PSST_TURNSTILE_SITE_KEY';
	public const CONSTANT_TURNSTILE_SECRET_KEY = 'PSST_TURNSTILE_SECRET_KEY';

	/**
	 * Whether a Turnstile key is defined by its constant.
	 *
	 * @param string $constant One of the CONSTANT_TURNSTILE_* names.
	 *
	 * @return bool
	 */
	public static function turnstile_constant_defined( string $constant ): bool {
		return defined( $constant ) && is_string( constant( $constant ) );
	}

	/**
	 * The Turnstile site key: the constant wins, then the stored setting.
	 *
	 * @return string
	 */
	public static function turnstile_site_key(): string {
		if ( self::turnstile_constant_defined( self::CONSTANT_TURNSTILE_SITE_KEY ) ) {
			return (string) constant( self::CONSTANT_TURNSTILE_SITE_KEY );
		}

		return (string) self::get( 'turnstile_site_key' );
	}

	/**
	 * The Turnstile secret key: the constant wins, then the write-only option.
	 *
	 * @return string
	 */
	public static function turnstile_secret(): string {
		if ( self::turnstile_constant_defined( self::CONSTANT_TURNSTILE_SECRET_KEY ) ) {
			return (string) constant( self::CONSTANT_TURNSTILE_SECRET_KEY );
		}

		return (string) get_option( self::OPTION_TURNSTILE_SECRET, '' );
	}

	/**
	 * Whether Turnstile is fully configured.
	 *
	 * @return bool
	 */
	public static function turnstile_enabled(): bool {
		return '' !== self::turnstile_site_key() && '' !== self::turnstile_secret();
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
			'turnstileSiteKey' => self::turnstile_enabled() ? self::turnstile_site_key() : '',
			'locale'           => str_replace( '_', '-', get_locale() ),

			/*
			 * Site-level only. The /config route is cached publicly for five
			 * minutes, so nothing that varies per user belongs in here — the
			 * blocks read the visitor's own state from their render context.
			 */
			'loginUrl'         => self::get_login_url(),
			'accountUrl'       => self::get_account_url(),
			'emailDelivery'    => self::email_delivery_enabled(),
		];

		/**
		 * Filters the configuration handed to the blocks and the /config route.
		 *
		 * @param array<string, mixed> $config Client configuration.
		 */
		return (array) apply_filters( 'psst_client_config', $config );
	}
}
