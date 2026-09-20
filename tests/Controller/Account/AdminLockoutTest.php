<?php
/**
 * Admin_Lockout URL tests.
 *
 * The lockout's own decision needs a user and a capability, so it is not
 * testable without WordPress. What is testable here is the URL classification
 * that decides where a user is sent after signing in — and, more importantly,
 * which entry points must never be treated as wp-admin.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Controller\Account;

use Linchpin\Psst\Controller\Account\Admin_Lockout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class AdminLockoutTest
 */
final class AdminLockoutTest extends TestCase {

	protected function tearDown(): void {
		psst_test_reset_filters();
	}

	/**
	 * Classify a URL as wp-admin or not.
	 *
	 * @param string $url      The URL.
	 * @param bool   $expected Whether it counts as wp-admin.
	 */
	#[DataProvider( 'provide_urls' )]
	public function test_admin_urls_are_recognised( string $url, bool $expected ): void {
		$this->assertSame( $expected, Admin_Lockout::is_admin_url( $url ) );
	}

	/**
	 * URLs on both sides of the line.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function provide_urls(): array {
		return [
			'the dashboard'      => [ 'https://example.test/wp-admin/', true ],
			'a profile screen'   => [ 'https://example.test/wp-admin/profile.php', true ],
			'a settings screen'  => [ 'https://example.test/wp-admin/options-general.php?page=psst', true ],

			/*
			 * These boot the admin but are how the front end talks to
			 * WordPress. Treating them as wp-admin would turn every front end
			 * form post and every fetch into a redirect the caller cannot use.
			 */
			'admin-ajax'         => [ 'https://example.test/wp-admin/admin-ajax.php', false ],
			'admin-post'         => [ 'https://example.test/wp-admin/admin-post.php?action=x', false ],

			'a front end page'   => [ 'https://example.test/share/', false ],
			'the account page'   => [ 'https://example.test/account/', false ],
			'the login screen'   => [ 'https://example.test/wp-login.php', false ],
			'nothing at all'     => [ '', false ],
			'a bare path'        => [ '/share/', false ],
		];
	}
}
