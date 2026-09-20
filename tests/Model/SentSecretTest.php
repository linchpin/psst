<?php
/**
 * Sent_Secret tests.
 *
 * @package Linchpin\Psst\Tests
 */

namespace Linchpin\Psst\Tests\Model;

use Linchpin\Psst\Model\Post_Type\Sent_Secret;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class SentSecretTest
 */
final class SentSecretTest extends TestCase {

	protected function tearDown(): void {
		psst_test_reset_filters();
	}

	/**
	 * Map a destruction reason onto a recorded state.
	 *
	 * @param string $reason   What psst_secret_destroyed reported.
	 * @param string $expected The state the history should record.
	 */
	#[DataProvider( 'provide_reasons' )]
	public function test_every_ending_maps_to_a_state( string $reason, string $expected ): void {
		$this->assertSame( $expected, Sent_Secret::state_for_reason( $reason ) );
	}

	/**
	 * Every reason Secret_Repository::destroy() is called with.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_reasons(): array {
		return [
			'read by the recipient' => [ 'revealed', Sent_Secret::STATE_REVEALED ],
			'ran out of time'       => [ 'expired', Sent_Secret::STATE_EXPIRED ],
			'sender destroyed it'   => [ 'shredded', Sent_Secret::STATE_SHREDDED ],

			// An admin shredding it and the sweep tidying up are both "gone before it was read".
			'admin destroyed it'    => [ 'admin', Sent_Secret::STATE_SHREDDED ],
			'swept up'              => [ 'sweep', Sent_Secret::STATE_SHREDDED ],

			// An unknown reason must still land somewhere, and never on "read".
			'something new'         => [ 'whatever-comes-next', Sent_Secret::STATE_SHREDDED ],
		];
	}

	public function test_an_unknown_reason_never_claims_the_secret_was_read(): void {
		$this->assertNotSame( Sent_Secret::STATE_REVEALED, Sent_Secret::state_for_reason( 'unrecognised' ) );
	}

	public function test_every_state_has_a_label(): void {
		foreach ( Sent_Secret::states() as $state ) {
			$this->assertNotSame( '', Sent_Secret::state_label( $state ) );
		}
	}

	public function test_the_post_type_is_invisible_everywhere(): void {
		$args = Sent_Secret::args();

		foreach ( [ 'public', 'publicly_queryable', 'show_ui', 'show_in_menu', 'show_in_rest', 'has_archive', 'can_export' ] as $flag ) {
			$this->assertFalse( $args[ $flag ], "{$flag} must be false" );
		}

		$this->assertTrue( $args['exclude_from_search'] );

		// History is about its author and has no reason to outlive them.
		$this->assertTrue( $args['delete_with_user'] );

		// Nothing may create one through the posts API.
		$this->assertSame( 'do_not_allow', $args['capabilities']['create_posts'] );
	}

	public function test_the_post_type_name_fits_wordpress_limit(): void {
		$this->assertLessThanOrEqual( 20, strlen( Sent_Secret::POST_TYPE ) );
	}
}
