<?php
/**
 * The email that carries a share link to a recipient.
 *
 * ---------------------------------------------------------------------------
 * Read this before changing anything in this file.
 *
 * Everywhere else in Psst, the decryption key is the one thing the server never
 * has. It is generated in the sender's browser, it travels in the link's
 * `#fragment`, and browsers do not send fragments to servers. The create route
 * rejects a request outright if it so much as carries a field named `key`.
 *
 * This feature is the deliberate exception, and it is off by default. For Psst
 * to email a link the recipient can actually open, the key has to reach the
 * server, because the server is what sends the mail. That means:
 *
 *   1. The key is in a request body for the lifetime of one request. Anything
 *      logging request bodies — a debug plugin, an application firewall, a
 *      misconfigured proxy — will capture it.
 *   2. The key then sits in the recipient's mailbox, and in whatever their
 *      provider does with mail, for as long as that message exists.
 *
 * Neither is acceptable silently, so both are disclosed at the point of use in
 * the form, in the documentation and in readme.txt. What this file guarantees
 * is the part it can: the fragment is used to compose one message and is never
 * written to the database, never passed to a hook and never logged.
 * ---------------------------------------------------------------------------
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Linchpin\Psst\Controller\REST\Secrets;

/**
 * Class Share_Email
 */
final class Share_Email {

	/**
	 * The longest note a sender may attach.
	 */
	public const MAX_NOTE_LENGTH = 500;

	/**
	 * Whether a string could be the key fragment from a share link.
	 *
	 * The fragment is the base64url encoding of a 256-bit key, so 43 characters
	 * at the current protocol version. The bound is deliberately loose at the
	 * top to survive a future key size, and strict about the alphabet, because
	 * this value is concatenated into a URL.
	 *
	 * @param mixed $fragment The candidate.
	 *
	 * @return bool
	 */
	public static function is_key_fragment( mixed $fragment ): bool {
		return is_string( $fragment ) && 1 === preg_match( '/^[A-Za-z0-9_-]{16,128}$/', $fragment );
	}

	/**
	 * Build the share URL the recipient will open.
	 *
	 * The URL is composed here from the public id rather than taken from the
	 * request. A caller that could hand us a whole URL to mail out would have
	 * turned this site into a way of sending arbitrary links from its own
	 * domain, which is a phishing kit, not a feature.
	 *
	 * @param string $public_id The secret's identifier.
	 * @param string $fragment  The key fragment, already validated.
	 *
	 * @return string
	 */
	public static function share_url( string $public_id, string $fragment ): string {
		return Secrets::secret_url( $public_id ) . '#' . $fragment;
	}

	/**
	 * Clean up a sender's note.
	 *
	 * @param string $note The raw note.
	 *
	 * @return string
	 */
	public static function sanitize_note( string $note ): string {
		$note = trim( wp_strip_all_tags( $note ) );

		if ( mb_strlen( $note ) > self::MAX_NOTE_LENGTH ) {
			$note = mb_substr( $note, 0, self::MAX_NOTE_LENGTH );
		}

		return $note;
	}

	/**
	 * The subject line.
	 *
	 * @param string $sender_name What to call the sender.
	 *
	 * @return string
	 */
	public static function subject( string $sender_name ): string {
		$subject = '' !== $sender_name
			? sprintf(
				/* translators: %s: the sender's name. */
				__( '%s sent you a secret', 'psst' ),
				$sender_name
			)
			: __( 'Someone sent you a secret', 'psst' );

		/**
		 * Filters the subject of a share email.
		 *
		 * @param string $subject     The subject.
		 * @param string $sender_name The sender's display name, or ''.
		 */
		return (string) apply_filters( 'psst_share_email_subject', $subject, $sender_name );
	}

	/**
	 * The plain-text body.
	 *
	 * Plain text on purpose. An HTML mail invites the client to rewrite links
	 * for click tracking, and a rewritten link drops the fragment — which would
	 * strip the key and deliver a message the recipient cannot open. It also
	 * keeps link previewers away from the URL, though a GET cannot burn a
	 * secret in any case.
	 *
	 * @param string $url         The full share URL, key fragment and all.
	 * @param string $sender_name The sender's display name, or ''.
	 * @param string $note        The sender's note, or ''.
	 * @param int    $expires_at  When the secret expires.
	 *
	 * @return string
	 */
	public static function body( string $url, string $sender_name, string $note, int $expires_at ): string {
		$lines = [];

		$lines[] = '' !== $sender_name
			? sprintf(
				/* translators: %s: the sender's name. */
				__( '%s has sent you a secret through this site.', 'psst' ),
				$sender_name
			)
			: __( 'Someone has sent you a secret through this site.', 'psst' );

		if ( '' !== $note ) {
			$lines[] = '';
			$lines[] = __( 'Their note:', 'psst' );
			$lines[] = $note;
		}

		$lines[] = '';
		$lines[] = __( 'Open it here:', 'psst' );
		$lines[] = $url;
		$lines[] = '';
		$lines[] = __( 'This link works once. Opening the secret destroys it, so copy what you need before you close the page.', 'psst' );

		if ( $expires_at > 0 ) {
			$lines[] = sprintf(
				/* translators: %s: a formatted date and time. */
				__( 'If nobody opens it, it is deleted on %s.', 'psst' ),
				Secrets::format_expiry( $expires_at )
			);
		}

		$lines[] = '';
		$lines[] = __( 'Keep this message to yourself. The link contains the key that unlocks the secret, so anyone who can read this email can read the secret.', 'psst' );
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: the site name. */
			__( 'Sent from %s', 'psst' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body = implode( "\n", $lines );

		/**
		 * Filters the body of a share email.
		 *
		 * The `$url` passed here contains the decryption key. A callback that
		 * logs or stores it defeats the point of the rest of this plugin.
		 *
		 * @param string $body        The message body.
		 * @param string $url         The full share URL.
		 * @param string $sender_name The sender's display name, or ''.
		 * @param string $note        The sender's note, or ''.
		 */
		return (string) apply_filters( 'psst_share_email_body', $body, $url, $sender_name, $note );
	}

	/**
	 * Send the share email.
	 *
	 * @param string $recipient   The address.
	 * @param string $public_id   The secret's identifier.
	 * @param string $fragment    The key fragment.
	 * @param string $sender_name The sender's display name, or ''.
	 * @param string $note        The sender's note, or ''.
	 * @param int    $expires_at  When the secret expires.
	 *
	 * @return bool Whether the mail was handed to the mailer.
	 */
	public static function send( string $recipient, string $public_id, string $fragment, string $sender_name, string $note, int $expires_at ): bool {
		$url = self::share_url( $public_id, $fragment );

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		/**
		 * Filters the headers of a share email.
		 *
		 * @param string[] $headers   The headers.
		 * @param string   $recipient The address.
		 */
		$headers = (array) apply_filters( 'psst_share_email_headers', $headers, $recipient );

		$sent = wp_mail(
			$recipient,
			self::subject( $sender_name ),
			self::body( $url, $sender_name, $note, $expires_at ),
			$headers
		);

		/*
		 * $url dies with this function. It is not returned, not stored and not
		 * passed to the action below, which — like every other hook in this
		 * plugin — receives identifiers only.
		 */
		unset( $url );

		/**
		 * Fires after a share link was emailed.
		 *
		 * @param string $public_id The secret's identifier.
		 * @param string $recipient The address it went to.
		 * @param bool   $sent      Whether the mailer accepted it.
		 */
		do_action( 'psst_share_email_sent', $public_id, $recipient, $sent );

		return (bool) $sent;
	}
}
