<?php
/**
 * History tests.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Controller\Account;

use Linchpin\Psst\Controller\Account\History;
use PHPUnit\Framework\TestCase;

/**
 * Class HistoryTest
 */
final class HistoryTest extends TestCase {

	protected function tearDown(): void {
		psst_test_reset_filters();
	}

	public function test_an_address_is_kept_as_an_address(): void {
		$this->assertSame( 'ops@example.test', History::sanitize_recipient( '  ops@example.test ' ) );
	}

	public function test_a_name_is_kept_too(): void {
		// Free text on purpose: a lot of senders will write who rather than where.
		$this->assertSame( 'Dave on the infra team', History::sanitize_recipient( 'Dave on the infra team' ) );
	}

	public function test_markup_does_not_survive(): void {
		$this->assertSame( 'ops', History::sanitize_recipient( '<script>alert(1)</script>ops' ) );
		$this->assertSame( 'bold', History::sanitize_recipient( '<b>bold</b>' ) );
	}

	public function test_nothing_in_means_nothing_stored(): void {
		$this->assertSame( '', History::sanitize_recipient( '' ) );
		$this->assertSame( '', History::sanitize_recipient( '     ' ) );
	}

	public function test_an_overlong_label_is_cut_to_the_limit(): void {
		$recipient = History::sanitize_recipient( str_repeat( 'x', History::MAX_RECIPIENT_LENGTH + 50 ) );

		$this->assertSame( History::MAX_RECIPIENT_LENGTH, mb_strlen( $recipient ) );
	}

	public function test_multibyte_labels_are_cut_by_character_not_byte(): void {
		$recipient = History::sanitize_recipient( str_repeat( 'é', History::MAX_RECIPIENT_LENGTH + 10 ) );

		$this->assertSame( History::MAX_RECIPIENT_LENGTH, mb_strlen( $recipient ) );
	}
}
