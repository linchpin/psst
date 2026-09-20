<?php
/**
 * The public secret routes: create, reveal, shred, config.
 *
 * @package Linchpin\Psst\Controller\REST
 */

namespace Linchpin\Psst\Controller\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Account\History;
use Linchpin\Psst\Core\Scheduler;
use Linchpin\Psst\Helper\Base64Url;
use Linchpin\Psst\Helper\Ids;
use Linchpin\Psst\Helper\Turnstile;
use Linchpin\Psst\Model\Envelope;
use Linchpin\Psst\Model\Secret_Repository;
use Linchpin\Psst\Model\Settings;
use Linchpin\Psst\Model\Validation_Error;

/**
 * Class Secrets
 */
class Secrets extends REST_Base {

	/**
	 * Request body cap, checked before JSON decoding.
	 */
	private const MAX_REQUEST_BYTES = 65536;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		parent::register_actions();
		Turnstile::register();
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$id_route = '/secrets/(?P<id>' . Ids::id_pattern() . ')';

		register_rest_route(
			$this->get_api_namespace(),
			'/secrets',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'permit_create' ],
				'show_in_index'       => false,
				'args'                => [
					'version'        => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'ciphertext'     => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => [ Base64Url::class, 'sanitize' ],
						'validate_callback' => [ Base64Url::class, 'is_valid' ],
					],
					'iv'             => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => [ Base64Url::class, 'sanitize' ],
						'validate_callback' => [ Base64Url::class, 'is_valid' ],
					],
					'check'          => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => [ Base64Url::class, 'sanitize' ],
						'validate_callback' => [ Base64Url::class, 'is_valid' ],
					],
					'has_passphrase' => [
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'salt'           => [
						'type'              => [ 'string', 'null' ],
						'sanitize_callback' => [ Base64Url::class, 'sanitize' ],
					],
					'kdf'            => [
						'type'              => [ 'object', 'null' ],
						'validate_callback' => [ $this, 'validate_kdf' ],
					],
					'ttl_minutes'    => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => [ $this, 'validate_ttl' ],
					],
					'hp'             => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'challenge'      => [
						'type'              => [ 'string', 'null' ],
						'sanitize_callback' => 'sanitize_text_field',
					],

					/*
					 * A label for the sender's own history: an address, or a
					 * name, or anything else that will mean something to them
					 * later. Recorded only for a logged-in sender with history
					 * switched on, and never part of the envelope.
					 */
					'recipient'      => [
						'type'              => [ 'string', 'null' ],
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			$id_route . '/reveal',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reveal' ],
				'permission_callback' => [ $this, 'permit_reveal' ],
				'show_in_index'       => false,
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ Ids::class, 'is_public_id' ],
					],
				],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			$id_route,
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'shred' ],
				'permission_callback' => [ $this, 'permit_shred' ],
				'show_in_index'       => false,
				'args'                => [
					'id'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ Ids::class, 'is_public_id' ],
					],
					'token' => [
						'type'              => [ 'string', 'null' ],
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			'/config',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'config' ],
				'permission_callback' => '__return_true',
				'show_in_index'       => false,
			]
		);
	}

	/**
	 * `kdf` is null or an object with the three expected members.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	public function validate_kdf( mixed $value ): bool {
		if ( null === $value ) {
			return true;
		}

		return is_array( $value ) && isset( $value['name'] ) && isset( $value['hash'] ) && isset( $value['iterations'] );
	}

	/**
	 * `ttl_minutes` is one of the offered choices.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	public function validate_ttl( mixed $value ): bool {
		return \Linchpin\Psst\Model\Ttl::is_allowed( $value, (array) Settings::get( 'ttl_options' ) );
	}

	/**
	 * Create: size cap, rate limits, honeypot, then the optional challenge.
	 *
	 * Nonce-less by design. Senders are anonymous, so a wp_rest nonce is
	 * unobtainable and would add nothing; CSRF is irrelevant to an
	 * unauthenticated write with no session behind it.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return true|\WP_Error
	 */
	public function permit_create( \WP_REST_Request $request ): true|\WP_Error {
		if ( strlen( (string) $request->get_body() ) > self::max_request_bytes() ) {
			return new \WP_Error( 'psst_payload_too_large', __( 'That secret is larger than this site allows.', 'psst' ), [ 'status' => 413 ] );
		}

		if ( Settings::require_login_to_create() && ! is_user_logged_in() ) {
			return new \WP_Error(
				'psst_login_required',
				__( 'You need to be signed in to create a secret.', 'psst' ),
				[
					'status'    => rest_authorization_required_code(),
					'login_url' => Settings::get_login_url(),
				]
			);
		}

		$limited = $this->limit( 'create', (int) Settings::get( 'rate_limit_create_per_hour' ) )
			?: $this->limit( 'create_global', (int) Settings::get( 'rate_limit_create_global_per_hour' ), 'all' );

		if ( $limited ) {
			return $limited;
		}

		if ( '' !== (string) $request->get_param( 'hp' ) ) {
			return new \WP_Error( 'psst_rejected', __( 'The request could not be accepted.', 'psst' ), [ 'status' => 400 ] );
		}

		/**
		 * Filters whether a create request passes the site's anti-abuse challenge.
		 *
		 * Return a WP_Error to refuse. Turnstile attaches here when configured.
		 *
		 * @param true|\WP_Error   $result  True to allow.
		 * @param \WP_REST_Request $request The request.
		 */
		$challenge = apply_filters( 'psst_create_challenge', true, $request );

		return is_wp_error( $challenge ) ? $challenge : true;
	}

	/**
	 * Reveal: rate limit and a JSON content type.
	 *
	 * Link unfurlers and mail scanners only GET, and an HTML form cannot send
	 * application/json, so the content-type requirement alone rules out every
	 * accidental consumer.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return true|\WP_Error
	 */
	public function permit_reveal( \WP_REST_Request $request ): true|\WP_Error {
		$limited = $this->limit( 'reveal', (int) Settings::get( 'rate_limit_reveal_per_hour' ) );

		if ( $limited ) {
			return $limited;
		}

		$content_type = $request->get_content_type();

		if ( ! is_array( $content_type ) || 'application/json' !== ( $content_type['value'] ?? '' ) ) {
			return new \WP_Error( 'psst_rejected', __( 'Reveal requests must be JSON.', 'psst' ), [ 'status' => 415 ] );
		}

		return true;
	}

	/**
	 * Shred: the sender's token, or an administrator.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return true|\WP_Error
	 */
	public function permit_shred( \WP_REST_Request $request ): true|\WP_Error {
		// Three shreds per create keeps token guessing pointless (it already is at 256 bits) while zero still disables.
		$limited = $this->limit( 'shred', 3 * (int) Settings::get( 'rate_limit_create_per_hour' ) );

		if ( $limited ) {
			return $limited;
		}

		$repository = new Secret_Repository();
		$post       = $repository->find_any( (string) $request['id'] );

		if ( ! $post ) {
			return $this->not_found();
		}

		if ( $repository->token_matches( $post, $this->presented_token( $request ) ) ) {
			return true;
		}

		if ( is_user_logged_in() && current_user_can( 'delete_post', $post->ID ) ) {
			return true;
		}

		return new \WP_Error( 'psst_bad_token', __( 'That token does not unlock this secret.', 'psst' ), [ 'status' => 403 ] );
	}

	/**
	 * POST /secrets
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body = (array) $request->get_json_params();

		if ( empty( $body ) ) {
			return new \WP_Error( 'psst_invalid_envelope', __( 'The request body must be JSON.', 'psst' ), [ 'status' => 400 ] );
		}

		$envelope = Envelope::from_request( $body, Settings::max_plaintext_bytes() );

		if ( $envelope instanceof Validation_Error ) {
			return new \WP_Error(
				$envelope->code,
				$envelope->message,
				[
					'status' => 'psst_payload_too_large' === $envelope->code ? 413 : 400,
					'field'  => $envelope->field,
				]
			);
		}

		$ttl     = (int) $request['ttl_minutes'];
		$created = ( new Secret_Repository() )->insert( $envelope, $ttl );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		Scheduler::schedule_expiry( $created->post_id, $created->expires_at );

		/*
		 * The sender's own record of what they sent. A no-op unless history is
		 * switched on and the sender is signed in, and metadata-only in any
		 * case — it is handed the envelope for its byte count and nothing else.
		 */
		History::record( $created, $envelope, (string) $request->get_param( 'recipient' ) );

		/**
		 * Fires after a secret is stored.
		 *
		 * @param int    $post_id   The post.
		 * @param string $public_id The URL identifier.
		 */
		do_action( 'psst_secret_created', $created->post_id, $created->public_id );

		$response = [
			'id'             => $created->public_id,
			'url'            => self::secret_url( $created->public_id ),
			'manage_token'   => $created->manage_token,
			'expires_at'     => gmdate( 'c', $created->expires_at ),
			'expires_in'     => max( 0, $created->expires_at - time() ),
			'expires_label'  => self::format_expiry( $created->expires_at ),
			'has_passphrase' => $envelope->has_passphrase,
			'ttl_minutes'    => $created->ttl_minutes,
		];

		/**
		 * Filters the create response.
		 *
		 * @param array<string, mixed> $response The response body.
		 * @param int                  $post_id  The post.
		 */
		$response = (array) apply_filters( 'psst_create_response', $response, $created->post_id );

		return new \WP_REST_Response( $response, 201 );
	}

	/**
	 * POST /secrets/{id}/reveal — the one consuming action.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reveal( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$repository = new Secret_Repository();
		$post       = $repository->find_active( (string) $request['id'] );

		if ( ! $post ) {
			return $this->not_found();
		}

		$envelope = $repository->claim_and_read( $post->ID );

		if ( ! $envelope ) {
			return $this->not_found();
		}

		/**
		 * Fires after a secret was revealed and destroyed.
		 *
		 * @param string $public_id The URL identifier.
		 */
		do_action( 'psst_secret_revealed', $post->post_name );

		return new \WP_REST_Response(
			[
				'id'          => $post->post_name,
				'envelope'    => $envelope->to_array(),
				'revealed_at' => gmdate( 'c' ),
			],
			200
		);
	}

	/**
	 * DELETE /secrets/{id}
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function shred( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$repository = new Secret_Repository();
		$post       = $repository->find_any( (string) $request['id'] );

		if ( ! $post ) {
			return $this->not_found();
		}

		$actor = $repository->token_matches( $post, $this->presented_token( $request ) ) ? 'sender' : 'admin';

		$repository->destroy( $post->ID, 'shredded' );

		/**
		 * Fires after a secret was shredded before being read.
		 *
		 * @param string $public_id The URL identifier.
		 * @param string $actor     sender or admin.
		 */
		do_action( 'psst_secret_shredded', $post->post_name, $actor );

		return new \WP_REST_Response(
			[
				'id'    => $post->post_name,
				'state' => 'shredded',
			],
			200
		);
	}

	/**
	 * GET /config
	 *
	 * @return \WP_REST_Response
	 */
	public function config(): \WP_REST_Response {
		$response = new \WP_REST_Response( Settings::client_config(), 200 );
		$response->header( 'Cache-Control', 'public, max-age=300' );

		return $response;
	}

	/**
	 * The URL a recipient opens, without the key fragment.
	 *
	 * @param string $public_id The identifier.
	 *
	 * @return string
	 */
	public static function secret_url( string $public_id ): string {
		return home_url( '/' . \Linchpin\Psst\Controller\Rewrites::base() . '/' . $public_id . '/' );
	}

	/**
	 * "Expires: date @ time" in the site's formats and timezone.
	 *
	 * @param int $timestamp Unix timestamp.
	 *
	 * @return string
	 */
	public static function format_expiry( int $timestamp ): string {
		/**
		 * Filters the format expiry dates are shown in.
		 *
		 * Kept from 1.x.
		 *
		 * @param string $format A PHP date format.
		 */
		$format = (string) apply_filters( 'psst_date_time_format', get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ) );

		return wp_date( $format, $timestamp );
	}

	/**
	 * The request body cap.
	 *
	 * @return int
	 */
	private static function max_request_bytes(): int {
		/**
		 * Filters the maximum accepted request body size in bytes.
		 *
		 * @param int $bytes Defaults to 65536.
		 */
		return max( 4096, (int) apply_filters( 'psst_max_request_bytes', self::MAX_REQUEST_BYTES ) );
	}
}
