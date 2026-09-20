<?php
/**
 * Share_Email tests.
 *
 * These guard the parts of email delivery that keep it from being worse than
 * it already is: that only a key-shaped fragment is accepted, that the URL is
 * composed here rather than taken from a caller, and that the message says out
 * loud what it is carrying.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Model;

use Linchpin\Psst\Model\Share_Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class ShareEmailTest
 */
final class ShareEmailTest extends TestCase {

	protected function tearDown(): void {
		psst_test_reset_filters();
	}

	public function test_a_real_key_fragment_is_accepted(): void {
		// 43 base64url characters: a 256-bit key as the browser encodes it.
		$this->assertTrue( Share_Email::is_key_fragment( str_repeat( 'a', 43 ) ) );
		$this->assertTrue( Share_Email::is_key_fragment( 'aB3-_' . str_repeat( 'x', 38 ) ) );
	}

	/**
	 * Refuse a fragment that is not key-shaped.
	 *
	 * @param mixed $fragment The candidate.
	 */
	#[DataProvider( 'provide_bad_fragments' )]
	public function test_anything_else_is_refused( mixed $fragment ): void {
		$this->assertFalse( Share_Email::is_key_fragment( $fragment ) );
	}

	/**
	 * Fragments that must never reach the URL builder.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function provide_bad_fragments(): array {
		return [
			'empty'              => [ '' ],
			'too short'          => [ 'abc' ],
			'too long'           => [ str_repeat( 'a', 129 ) ],
			'not a string'       => [ 12345 ],
			'null'               => [ null ],
			'array'              => [ [ 'a' ] ],

			/*
			 * The ones that matter. A fragment is concatenated into a URL, so
			 * anything that could end the fragment and start something else —
			 * a slash, a colon, a query string, whitespace — has to be refused.
			 */
			'contains a slash'   => [ str_repeat( 'a', 20 ) . '/evil.example' ],
			'contains a colon'   => [ 'https://evil.example/' . str_repeat( 'a', 20 ) ],
			'contains a query'   => [ str_repeat( 'a', 20 ) . '?next=evil' ],
			'contains a space'   => [ str_repeat( 'a', 20 ) . ' evil' ],
			'contains a newline' => [ str_repeat( 'a', 20 ) . "\nBcc: evil@example" ],
			'contains a hash'    => [ str_repeat( 'a', 20 ) . '#again' ],
		];
	}

	public function test_the_url_is_built_from_the_id_not_from_the_caller(): void {
		$fragment = str_repeat( 'k', 43 );
		$url      = Share_Email::share_url( str_repeat( 'i', 22 ), $fragment );

		$this->assertStringStartsWith( 'https://example.test/s/', $url );
		$this->assertStringEndsWith( '#' . $fragment, $url );
	}

	public function test_a_note_loses_its_markup_and_its_excess(): void {
		$this->assertSame( 'hello', Share_Email::sanitize_note( '  <b>hello</b>  ' ) );
		$this->assertSame( '', Share_Email::sanitize_note( '<script>alert(1)</script>' ) );

		$long = str_repeat( 'x', Share_Email::MAX_NOTE_LENGTH + 100 );

		$this->assertSame( Share_Email::MAX_NOTE_LENGTH, mb_strlen( Share_Email::sanitize_note( $long ) ) );
	}

	public function test_the_body_carries_the_link_and_says_what_it_costs(): void {
		$url  = 'https://example.test/s/' . str_repeat( 'i', 22 ) . '/#' . str_repeat( 'k', 43 );
		$body = Share_Email::body( $url, 'Ada', 'for the staging box', 0 );

		$this->assertStringContainsString( $url, $body );
		$this->assertStringContainsString( 'Ada', $body );
		$this->assertStringContainsString( 'for the staging box', $body );

		// The recipient is told that the email itself is now sensitive.
		$this->assertStringContainsString( 'anyone who can read this email can read the secret', $body );
		$this->assertStringContainsString( 'works once', $body );
	}

	public function test_an_anonymous_sender_is_not_given_a_name(): void {
		$this->assertStringContainsString( 'Someone sent you a secret', Share_Email::subject( '' ) );
		$this->assertStringContainsString( 'Ada sent you a secret', Share_Email::subject( 'Ada' ) );
	}

	public function test_the_body_filter_can_rewrite_the_message(): void {
		add_filter( 'psst_share_email_body', static fn() => 'replaced' );

		$this->assertSame( 'replaced', Share_Email::body( 'https://example.test/s/x#y', '', '', 0 ) );
	}
}
