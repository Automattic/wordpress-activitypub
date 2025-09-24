<?php
/**
 * Unit tests for the Activitypub Reject handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Reject;

/**
 * Class Test_Reject
 *
 * @coversDefaultClass \Activitypub\Handler\Reject
 */
class Test_Reject extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		\wp_delete_user( self::$user_id );
	}

	/**
	 * Test validate_object returns false if type is missing.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_missing_type() {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn( array() );
		$this->assertFalse( Reject::validate_object( true, 'param', $request ) );
	}

	/**
	 * Test validate_object returns true if type is not Reject.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_type_not_accept() {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn( array( 'type' => 'Follow' ) );
		$this->assertTrue( Reject::validate_object( true, 'param', $request ) );
	}

	/**
	 * Test validate_object returns false if required fields are missing.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_missing_required_fields() {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn(
			array(
				'type'  => 'Reject',
				'actor' => 'foo',
			)
		);
		$this->assertFalse( Reject::validate_object( true, 'param', $request ) );
	}

	/**
	 * Test validate_object returns true if all checks pass.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_success() {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn(
			array(
				'type'   => 'Reject',
				'actor'  => 'foo',
				'object' => array(
					'id'     => 'bar',
					'actor'  => 'foo',
					'type'   => 'Follow',
					'object' => 'foo',
				),
			)
		);
		$this->assertTrue( Reject::validate_object( true, 'param', $request ) );
	}

	/**
	 * Functional test: handle_reject keeps user in pending and does not move to following meta.
	 */
	public function test_handle_reject_keeps_user_in_pending() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/123';
		$outbox_guid = 'https://example.com/outbox/123';

		$outbox_post_id = $this->factory->post->create(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $outbox_guid,
			)
		);

		\add_post_meta( $outbox_post_id, '_activitypub_activity_type', 'Follow' );

		// Create remote actor post.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $object_guid,
			)
		);

		// Add user to pending.
		\add_post_meta( $post_id, Following::PENDING_META_KEY, (string) $user_id );

		// Confirm precondition.
		$pending = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $user_id, $pending );

		// Prepare reject array as expected by handle_reject, using the real outbox guid.
		$reject = array(
			'type'   => 'Reject',
			'actor'  => 'https://example.net/actor/123',
			'object' => array(
				'id'     => $outbox_guid,
				'actor'  => 'https://example.com/actor/123',
				'type'   => 'Follow',
				'object' => $object_guid,
			),
		);

		// Call the handler.
		Reject::handle_reject( $reject, $user_id );

		\clean_post_cache( $post_id );

		// Assert: user_id is NOT in _activitypub_followed_by.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertNotContains( (string) $user_id, $following );

		// Assert: user_id is STILL in _activitypub_followed_by_pending.
		$pending = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertNotContains( (string) $user_id, $pending );
	}
}
