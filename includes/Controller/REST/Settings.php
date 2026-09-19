<?php
/**
 * Admin routes: settings, the secrets list, health.
 *
 * @package Linchpin\Psst\Controller\REST
 */

namespace Linchpin\Psst\Controller\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Install;
use Linchpin\Psst\Core\Scheduler;
use Linchpin\Psst\Model\Secret_Repository;
use Linchpin\Psst\Model\Settings as Settings_Model;
use Linchpin\Psst\Model\Ttl;

/**
 * Class Settings
 */
class Settings extends REST_Base {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		parent::register_actions();
		add_action( 'init', [ Settings_Model::class, 'register' ] );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->get_api_namespace(),
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'get_admin_permissions' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'get_admin_permissions' ],
					'args'                => [
						'settings'         => [
							'required'          => true,
							'type'              => 'object',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'turnstile_secret' => [
							'type'              => [ 'string', 'null' ],
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				'show_in_index' => false,
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			'/settings/reset',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reset_settings' ],
				'permission_callback' => [ $this, 'get_admin_permissions' ],
				'show_in_index'       => false,
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			'/settings/pages',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_page' ],
				'permission_callback' => [ $this, 'get_page_permissions' ],
				'show_in_index'       => false,
				'args'                => [
					'kind' => [
						'required'          => true,
						'type'              => 'string',
						'enum'              => [ Install::PAGE_CREATE, Install::PAGE_REVEAL ],
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			'/admin/secrets',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_secrets' ],
				'permission_callback' => [ $this, 'get_admin_permissions' ],
				'show_in_index'       => false,
				'args'                => [
					'page'     => [
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					],
					'orderby'  => [
						'type'              => 'string',
						'default'           => 'created',
						'enum'              => [ 'created', 'expires' ],
						'sanitize_callback' => 'sanitize_key',
					],
					'order'    => [
						'type'              => 'string',
						'default'           => 'desc',
						'enum'              => [ 'asc', 'desc' ],
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		register_rest_route(
			$this->get_api_namespace(),
			'/admin/health',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'health' ],
				'permission_callback' => [ $this, 'get_admin_permissions' ],
				'show_in_index'       => false,
			]
		);
	}

	/**
	 * GET /settings
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		return new \WP_REST_Response( $this->payload(), 200 );
	}

	/**
	 * POST /settings
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		Settings_Model::save( (array) $request->get_param( 'settings' ) );

		/*
		 * The Turnstile secret is write-only. An empty string clears it; null or
		 * absence leaves it alone. It is never echoed back.
		 */
		$secret = $request->get_param( 'turnstile_secret' );

		if ( is_string( $secret ) ) {
			if ( '' === $secret ) {
				delete_option( Settings_Model::OPTION_TURNSTILE_SECRET );
			} else {
				update_option( Settings_Model::OPTION_TURNSTILE_SECRET, $secret, false );
			}
		}

		return new \WP_REST_Response( $this->payload(), 200 );
	}

	/**
	 * Creating a page needs the page capability on top of the admin one.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool|\WP_Error
	 */
	public function get_page_permissions( \WP_REST_Request $request ): bool|\WP_Error {
		$admin = $this->get_admin_permissions( $request );

		if ( true !== $admin ) {
			return $admin;
		}

		if ( current_user_can( 'publish_pages' ) ) {
			return true;
		}

		return new \WP_Error(
			'psst_forbidden',
			__( 'You are not allowed to publish pages.', 'psst' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * POST /settings/pages
	 *
	 * A fresh published page holding the blocks the kind needs. The setting is
	 * not changed here: the screen puts the new page into its unsaved draft so
	 * one Save covers everything.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_page( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$page_id = Install::create_page( (string) $request['kind'], false );

		if ( $page_id <= 0 ) {
			return new \WP_Error( 'psst_page_not_created', __( 'The page could not be created.', 'psst' ), [ 'status' => 500 ] );
		}

		return new \WP_REST_Response(
			[
				'id'    => $page_id,
				'title' => get_the_title( $page_id ),
				'link'  => get_permalink( $page_id ),
			],
			201
		);
	}

	/**
	 * POST /settings/reset
	 *
	 * @return \WP_REST_Response
	 */
	public function reset_settings(): \WP_REST_Response {
		$defaults = Settings_Model::defaults();

		// Keep the pages: resetting the numbers should not orphan the routes.
		$defaults['create_page_id'] = (int) Settings_Model::get( 'create_page_id' );
		$defaults['reveal_page_id'] = (int) Settings_Model::get( 'reveal_page_id' );

		Settings_Model::save( $defaults );

		return new \WP_REST_Response( $this->payload(), 200 );
	}

	/**
	 * GET /admin/secrets
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_secrets( \WP_REST_Request $request ): \WP_REST_Response {
		$result = ( new Secret_Repository() )->list_for_admin(
			(int) $request['page'],
			(int) $request['per_page'],
			(string) $request['orderby'],
			(string) $request['order']
		);

		$response = new \WP_REST_Response( $result['items'], 200 );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / max( 1, (int) $request['per_page'] ) ) );

		return $response;
	}

	/**
	 * GET /admin/health
	 *
	 * @return \WP_REST_Response
	 */
	public function health(): \WP_REST_Response {
		$next = Scheduler::next_sweep();

		return new \WP_REST_Response(
			[
				'actionScheduler'  => Scheduler::has_action_scheduler(),
				'nextSweep'        => $next ? gmdate( 'c', $next ) : null,
				'activeSecrets'    => ( new Secret_Repository() )->count_active(),
				'cronDisabled'     => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'legacyRemaining'  => ( new Secret_Repository() )->has_legacy(),
				'turnstileEnabled' => Settings_Model::turnstile_enabled(),
				'version'          => PSST_VERSION,
				'createUrl'        => Settings_Model::get_create_url(),
				'revealUrl'        => Settings_Model::reveal_page_id() > 0 ? get_permalink( Settings_Model::reveal_page_id() ) : null,
			],
			200
		);
	}

	/**
	 * The settings payload the app renders from.
	 *
	 * @return array<string, mixed>
	 */
	private function payload(): array {
		return [
			'settings'            => Settings_Model::all(),
			'schema'              => Settings_Model::schema(),
			'ttlCatalog'          => Ttl::catalog(),
			'hasTurnstileSecret'  => '' !== Settings_Model::turnstile_secret(),
			'turnstile'           => [
				'siteKeyConstant'     => Settings_Model::CONSTANT_TURNSTILE_SITE_KEY,
				'secretKeyConstant'   => Settings_Model::CONSTANT_TURNSTILE_SECRET_KEY,
				'siteKeyByConstant'   => Settings_Model::turnstile_constant_defined( Settings_Model::CONSTANT_TURNSTILE_SITE_KEY ),
				'secretKeyByConstant' => Settings_Model::turnstile_constant_defined( Settings_Model::CONSTANT_TURNSTILE_SECRET_KEY ),
				// The site key is public by design; the secret is never sent.
				'siteKey'             => Settings_Model::turnstile_site_key(),
			],
		];
	}
}
