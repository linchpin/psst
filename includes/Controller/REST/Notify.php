<?php
/**
 * The route that emails a share link to a recipient.
 *
 * Separate from the secret routes on purpose. Those routes are built so that a
 * key cannot reach the server; this one exists only because a key must, and
 * keeping it in its own file keeps that exception visible rather than buried
 * among the routes that hold the line.
 *
 * See Model\Share_Email for what this costs and why it is off by default.
 *
 * @package Linchpin\Psst\Controller\REST
 */

namespace Linchpin\Psst\Controller\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Helper\Ids;
use Linchpin\Psst\Model\Post_Type\Secret;
use Linchpin\Psst\Model\Secret_Repository;
use Linchpin\Psst\Model\Sent_Secret_Repository;
use Linchpin\Psst\Model\Settings;
use Linchpin\Psst\Model\Share_Email;

/**
 * Class Notify
 */
class Notify extends REST_Base {

	/**
	 * Share emails one address may trigger per hour.
	 */
	public const RATE_LIMIT = 20;

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->get_api_namespace(),
			'/secrets/(?P<id>' . Ids::id_pattern() . ')/notify',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'notify' ],
				'permission_callback' => [ $this, 'permit' ],
				'show_in_index'       => false,
				'args'                => [
					'id'        => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ Ids::class, 'is_public_id' ],
					],
					'recipient' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => 'is_email',
					],

					/*
					 * The key, and the only part of the link the caller supplies.
					 * The rest of the URL is rebuilt from the id server-side, so
					 * this route cannot be used to mail an arbitrary link from
					 * this site's domain.
					 */
					'fragment'  => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ Share_Email::class, 'is_key_fragment' ],
					],
					'note'      => [
						'type'              => [ 'string', 'null' ],
						'sanitize_callback' => [ Share_Email::class, 'sanitize_note' ],
					],
					'token'     => [
						'type'              => [ 'string', 'null' ],
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Only the sender of this secret, and only when the site has opted in.
	 *
	 * The management token is the proof. It is returned to the sender exactly
	 * once at creation and only its hash is stored, so holding it means having
	 * created this secret — the same proof the shred route accepts.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return true|\WP_Error
	 */
	public function permit( \WP_REST_Request $request ): true|\WP_Error {
		if ( ! Settings::email_delivery_enabled() ) {
			return new \WP_Error( 'psst_not_enabled', __( 'This site does not send secrets by email.', 'psst' ), [ 'status' => 404 ] );
		}

		$content_type = $request->get_content_type();

		if ( ! is_array( $content_type ) || 'application/json' !== ( $content_type['value'] ?? '' ) ) {
			return new \WP_Error( 'psst_rejected', __( 'Notify requests must be JSON.', 'psst' ), [ 'status' => 415 ] );
		}

		$limited = $this->limit( 'notify', self::RATE_LIMIT );

		if ( $limited ) {
			return $limited;
		}

		$post = ( new Secret_Repository() )->find_any( (string) $request['id'] );

		if ( ! $post ) {
			return $this->not_found();
		}

		if ( ! ( new Secret_Repository() )->token_matches( $post, $this->presented_token( $request ) ) ) {
			return new \WP_Error( 'psst_bad_token', __( 'That token does not unlock this secret.', 'psst' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * POST /secrets/{id}/notify
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function notify( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$repository = new Secret_Repository();
		$public_id  = (string) $request['id'];
		$post       = $repository->find_active( $public_id );

		if ( ! $post ) {
			return $this->not_found();
		}

		$recipient = (string) $request['recipient'];

		/*
		 * A note is a logged-in privilege. It is free text that leaves this site
		 * in an email carrying this site's name, and an anonymous sender is not
		 * someone worth handing that to.
		 */
		$note = is_user_logged_in() ? (string) $request->get_param( 'note' ) : '';

		$sent = Share_Email::send(
			$recipient,
			$public_id,
			(string) $request['fragment'],
			$this->sender_name(),
			$note,
			(int) get_post_meta( $post->ID, Secret::META_EXPIRES_AT, true )
		);

		if ( ! $sent ) {
			return new \WP_Error( 'psst_mail_failed', __( 'The email could not be sent. Share the link yourself instead.', 'psst' ), [ 'status' => 500 ] );
		}

		( new Sent_Secret_Repository() )->mark_notified( $public_id, $recipient );

		return new \WP_REST_Response(
			[
				'id'        => $public_id,
				'sent'      => true,
				'recipient' => $recipient,
			],
			200
		);
	}

	/**
	 * What to call the sender in the email.
	 *
	 * Anonymous senders stay anonymous. Putting "someone" in the subject is
	 * more honest than inventing a name, and there is nothing to invent one
	 * from.
	 *
	 * @return string
	 */
	private function sender_name(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user = wp_get_current_user();

		$name = '' !== trim( (string) $user->display_name )
			? (string) $user->display_name
			: (string) $user->user_email;

		/**
		 * Filters the sender name shown in a share email.
		 *
		 * @param string $name    The display name or address.
		 * @param int    $user_id The sender.
		 */
		return (string) apply_filters( 'psst_share_email_sender_name', $name, (int) $user->ID );
	}
}
