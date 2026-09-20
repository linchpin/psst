<?php
/**
 * A sender's record of the secrets they have sent.
 *
 * Registers the history post type, records a row when a logged-in sender
 * creates a secret, follows that secret to whatever end it comes to, and prunes
 * rows once they are older than the retention window.
 *
 * The history is metadata. It knows a secret existed, when, for how long, for
 * whom, and what became of it. It does not know, and cannot be made to know,
 * what the secret said.
 *
 * @package Linchpin\Psst\Controller\Account
 */

namespace Linchpin\Psst\Controller\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\Controller_Interface;
use Linchpin\Psst\Core\Scheduler;
use Linchpin\Psst\Model\Created_Secret;
use Linchpin\Psst\Model\Envelope;
use Linchpin\Psst\Model\Post_Type\Sent_Secret;
use Linchpin\Psst\Model\Sent_Secret_Repository;
use Linchpin\Psst\Model\Settings;

/**
 * Class History
 */
class History implements Controller_Interface {

	/**
	 * The longest recipient label worth keeping.
	 */
	public const MAX_RECIPIENT_LENGTH = 200;

	/**
	 * Hooks.
	 *
	 * The post type registers unconditionally. Registering it only when history
	 * is enabled would orphan every existing row the moment somebody turned the
	 * setting off, leaving data nothing could read or delete.
	 *
	 * @return void
	 */
	public function register_actions(): void {
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'psst_secret_destroyed', [ $this, 'follow_destruction' ], 10, 3 );
		add_action( Scheduler::HOOK_SWEEP, [ $this, 'prune' ] );
		add_filter( 'the_content', [ $this, 'never_output' ], 0 );
	}

	/**
	 * Register the history post type.
	 *
	 * @return void
	 */
	public function register(): void {
		register_post_type( Sent_Secret::POST_TYPE, Sent_Secret::args() );
	}

	/**
	 * Record that the current user sent a secret.
	 *
	 * Called straight from the create route rather than hung off
	 * `psst_secret_created`, because the recipient is a property of the request
	 * and not of the secret, and threading it through an action would mean
	 * either changing that hook's contract or stashing request state somewhere
	 * global.
	 *
	 * @param Created_Secret $created   What the repository handed back.
	 * @param Envelope       $envelope  The stored envelope, read for its size only.
	 * @param string         $recipient What the sender typed, or ''.
	 * @param int|null       $user_id   The sender; defaults to the current user.
	 *
	 * @return int The history row id, or 0 when nothing was recorded.
	 */
	public static function record( Created_Secret $created, Envelope $envelope, string $recipient = '', ?int $user_id = null ): int {
		if ( ! Settings::history_enabled() ) {
			return 0;
		}

		$user_id = $user_id ?? get_current_user_id();

		if ( $user_id <= 0 ) {
			return 0;
		}

		return ( new Sent_Secret_Repository() )->record(
			$user_id,
			$created->public_id,
			[
				'expires_at'     => $created->expires_at,
				'ttl_minutes'    => $created->ttl_minutes,
				'size'           => $envelope->size(),
				'has_passphrase' => $envelope->has_passphrase,
			],
			self::sanitize_recipient( $recipient )
		);
	}

	/**
	 * Whatever happened to a secret, write it down.
	 *
	 * Every ending routes through `psst_secret_destroyed`, so this one listener
	 * covers reveal, expiry, the sender shredding it, an administrator shredding
	 * it and the sweep tidying up after a missed schedule.
	 *
	 * Typed as mixed because these arrive through an action, and an action's
	 * arguments are whatever the caller passed — including a third-party caller
	 * firing `psst_secret_destroyed` itself.
	 *
	 * @param mixed $post_id   The secret post being deleted.
	 * @param mixed $reason    Why.
	 * @param mixed $public_id The secret's identifier.
	 *
	 * @return void
	 */
	public function follow_destruction( $post_id, $reason, $public_id ): void {
		unset( $post_id );

		if ( ! is_string( $public_id ) || '' === $public_id ) {
			return;
		}

		( new Sent_Secret_Repository() )->mark_state(
			$public_id,
			Sent_Secret::state_for_reason( (string) $reason )
		);
	}

	/**
	 * Drop history rows past their retention window.
	 *
	 * @return void
	 */
	public function prune(): void {
		( new Sent_Secret_Repository() )->prune();
	}

	/**
	 * A history row has no content, but nothing is going to render one anyway.
	 *
	 * The same belt-and-braces filter the secret post type carries, for the same
	 * reason: whatever asks, it gets nothing.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	public function never_output( $content ) {
		return Sent_Secret::POST_TYPE === get_post_type() ? '' : $content;
	}

	/**
	 * Clean up whatever the sender typed in the recipient field.
	 *
	 * Free text on purpose: plenty of senders will write a name rather than an
	 * address, and a history entry saying "Dave, infra" is more use to them than
	 * a rejected form. Anything that is a valid address is normalised as one.
	 *
	 * @param string $recipient The raw value.
	 *
	 * @return string
	 */
	public static function sanitize_recipient( string $recipient ): string {
		$recipient = trim( sanitize_text_field( $recipient ) );

		if ( '' === $recipient ) {
			return '';
		}

		if ( is_email( $recipient ) ) {
			$recipient = sanitize_email( $recipient );
		}

		if ( mb_strlen( $recipient ) > self::MAX_RECIPIENT_LENGTH ) {
			$recipient = mb_substr( $recipient, 0, self::MAX_RECIPIENT_LENGTH );
		}

		return $recipient;
	}
}
