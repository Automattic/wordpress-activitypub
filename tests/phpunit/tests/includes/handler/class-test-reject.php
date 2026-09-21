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
use Activitypub\Tests\Quote_Post_Fixtures;
use Activitypub\Transformer\Post;

/**
 * Class Test_Reject
 *
 * @coversDefaultClass \Activitypub\Handler\Reject
 */
class Test_Reject extends \WP_UnitTestCase {
	use Quote_Post_Fixtures;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Mock the remote objects the quote tests fetch.
	 */
	public function set_up() {
		parent::set_up();

		$this->add_quoted_object_mock();
	}

	/**
	 * Remove the remote object mock.
	 */
	public function tear_down() {
		$this->remove_quoted_object_mock();
		parent::tear_down();
	}

	/**
	 * Test validate_object with various scenarios.
	 *
	 * @dataProvider validate_object_provider
	 * @covers ::validate_object
	 *
	 * @param array  $request_data     The request data to test.
	 * @param bool   $input_valid      The input valid state.
	 * @param bool   $expected_result  The expected validation result.
	 * @param string $description      Description of the test case.
	 */
	public function test_validate_object( $request_data, $input_valid, $expected_result, $description ) {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn( $request_data );

		$result = Reject::validate_object( $input_valid, 'param', $request );

		$this->assertEquals( $expected_result, $result, $description );
	}

	/**
	 * Data provider for validate_object tests.
	 *
	 * @return array Test cases with request data, input valid state, expected result, and description.
	 */
	public function validate_object_provider() {
		return array(
			// Invalid cases.
			'missing_type'            => array(
				array(),
				true,
				false,
				'Should return false when type is missing',
			),
			'missing_required_fields' => array(
				array(
					'type'  => 'Reject',
					'actor' => 'foo',
				),
				true,
				false,
				'Should return false when required fields are missing',
			),
			// Valid cases - non-Reject type should pass through.
			'type_not_reject'         => array(
				array( 'type' => 'Follow' ),
				true,
				true,
				'Should return true when type is not Reject',
			),
			// Valid Reject activity.
			'valid_reject_activity'   => array(
				array(
					'type'   => 'Reject',
					'actor'  => 'foo',
					'object' => array(
						'id'     => 'bar',
						'actor'  => 'foo',
						'type'   => 'Follow',
						'object' => 'foo',
					),
				),
				true,
				true,
				'Should return true for valid Reject activity',
			),
			// Test with input_valid false.
			'input_valid_false'       => array(
				array( 'type' => 'Follow' ),
				false,
				false,
				'Should preserve input_valid when type is not Reject',
			),
		);
	}

	/**
	 * Functional test: handle_reject keeps user in pending and does not move to following meta.
	 */
	public function test_handle_reject_keeps_user_in_pending() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/123';
		$outbox_guid = 'https://example.com/outbox/123';

		$outbox_post_id = self::factory()->post->create(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $outbox_guid,
			)
		);

		\add_post_meta( $outbox_post_id, '_activitypub_activity_type', 'Follow' );

		// Create remote actor post.
		$post_id = self::factory()->post->create(
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
		// The sender (top-level actor) must be the actor that was followed.
		$reject = array(
			'type'   => 'Reject',
			'actor'  => $object_guid,
			'object' => array(
				'id'     => $outbox_guid,
				'actor'  => 'https://example.com/follower/123',
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

	/**
	 * A Reject whose sender is not the followed actor must be ignored.
	 *
	 * Guards against any peer cancelling a user's Follow of an unrelated actor
	 * by referencing that pending Follow's outbox GUID.
	 */
	public function test_handle_reject_rejects_actor_object_mismatch() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/123';
		$outbox_guid = 'https://example.com/outbox/123';

		$outbox_post_id = self::factory()->post->create(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $outbox_guid,
			)
		);

		\add_post_meta( $outbox_post_id, '_activitypub_activity_type', 'Follow' );

		// Create remote actor post the local user is following.
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $object_guid,
			)
		);

		\add_post_meta( $post_id, Following::FOLLOWING_META_KEY, (string) $user_id );

		// Reject signed by an unrelated actor, pointing at the victim's Follow GUID
		// and naming the followed actor so it would be unfollowed if unguarded.
		$reject = array(
			'type'   => 'Reject',
			'actor'  => 'https://evil.example/actor/999',
			'object' => array(
				'id'     => $outbox_guid,
				'actor'  => $object_guid,
				'type'   => 'Follow',
				'object' => $object_guid,
			),
		);

		Reject::handle_reject( $reject, $user_id );

		\clean_post_cache( $post_id );

		// Assert: the follow relationship is untouched.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $user_id, $following );
	}

	/*
	 * ------------------------------------------------------------------
	 * Rejects of our own QuoteRequests (FEP-044f).
	 * ------------------------------------------------------------------
	 */

	/**
	 * A Reject marks the quote declined, drops any stamp and queues an Update.
	 *
	 * @covers ::reject_quote_request
	 */
	public function test_reject_marks_declined_and_updates() {
		$post_id = $this->create_quote_post();
		\update_post_meta( $post_id, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );
		$before = $this->count_updates( $post_id );

		Reject::handle_reject( $this->build_reject( $post_id ), self::$user_id );

		$this->assertSame( '1', \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( $before + 1, $this->count_updates( $post_id ) );

		$array = Post::transform( \get_post( $post_id ) )->to_object()->to_array();
		$this->assertArrayNotHasKey( 'quote', $array );
	}

	/**
	 * A Reject for a request the post has since superseded is ignored.
	 *
	 * @covers ::reject_quote_request
	 */
	public function test_reject_for_superseded_url_ignored() {
		$post_id = $this->create_quote_post();
		$reject  = $this->build_reject( $post_id );
		\update_post_meta( $post_id, '_activitypub_quote_request', 'https://remote.example/notes/2' );
		$before = $this->count_updates( $post_id );

		Reject::handle_reject( $reject, self::$user_id );

		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
		$this->assertSame( 'https://remote.example/notes/2', \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
		$this->assertSame( $before, $this->count_updates( $post_id ) );
	}

	/**
	 * A Reject from someone other than the quoted author is ignored.
	 *
	 * @covers ::reject_quote_request
	 */
	public function test_reject_from_wrong_actor_ignored() {
		$post_id = $this->create_quote_post();

		Reject::handle_reject( $this->build_reject( $post_id, 'https://remote.example/users/mallory' ), self::$user_id );

		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
	}
}
