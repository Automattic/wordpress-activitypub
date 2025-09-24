<?php
/**
 * Test file for Undo Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Comment;
use Activitypub\Handler\Undo;

/**
 * Test class for Undo Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Undo
 */
class Test_Undo extends \WP_UnitTestCase {

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
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Remove any HTTP mocking filters.
		\remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		parent::tear_down();
	}

	/**
	 * Test handle_undo with follow activity.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_follow() {
		$actor = 'https://example.com/test-actor';

		// Mock HTTP requests for actor metadata.
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor ) {
				return array(
					'id'                => $actor,
					'type'              => 'Person',
					'name'              => 'Test Actor',
					'preferredUsername' => 'testactor',
					'inbox'             => $actor . '/inbox',
					'outbox'            => $actor . '/outbox',
					'url'               => $actor,
				);
			}
		);

		// Add follower first.
		$add_result = Followers::add_follower( self::$user_id, $actor );
		$this->assertIsInt( $add_result, 'Adding follower should return post ID' );

		// Verify follower was added.
		$followers = Followers::get_followers( self::$user_id );
		$this->assertNotEmpty( $followers, 'Should have followers after adding one' );

		$user_actor     = Actors::get_by_id( self::$user_id );
		$user_actor_url = $user_actor->get_id();

		// Debug: Check what the user actor URL looks like.
		$this->assertNotEmpty( $user_actor_url, 'User actor URL should not be empty' );

		// Create undo follow activity.
		$activity = array(
			'type'   => 'Undo',
			'actor'  => $actor,
			'object' => array(
				'type'   => 'Follow',
				'actor'  => $actor,
				'object' => $user_actor_url,
			),
		);

		// Process the undo.
		Undo::handle_undo( $activity, self::$user_id );

		// Verify follower was removed.
		$followers_after = Followers::get_followers( self::$user_id );
		$this->assertEmpty( $followers_after, 'Should have no followers after undo' );
	}

	/**
	 * Test handle_undo with like activity.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_like() {
		// Verify the constant is set to false for interactions to work.
		$this->assertFalse( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS, 'Interactions should be enabled for this test' );

		// Create a post to like.
		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);

		// Create a like comment.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => '👍',
			)
		);
		\add_comment_meta( $comment_id, 'source_id', 'https://example.com/like/123', true );
		\add_comment_meta( $comment_id, 'protocol', 'activitypub', true );

		// Verify comment exists.
		$comment = \get_comment( $comment_id );
		$this->assertNotNull( $comment );

		// Create undo like activity.
		$activity = array(
			'type'   => 'Undo',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'type' => 'Like',
				'id'   => 'https://example.com/like/123',
			),
		);

		// Verify the comment can be found by source_id before processing.
		$found_comment = Comment::object_id_to_comment( 'https://example.com/like/123' );
		$this->assertNotFalse( $found_comment, 'Comment should be found by source_id before undo' );

		// Debug: Check if constant is actually false.
		$this->assertFalse( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS, 'Constant should be false' );

		// Debug: Test what the handler will actually receive.
		$object_id  = \Activitypub\object_to_uri( $activity['object'] );
		$escaped_id = \esc_url_raw( $object_id );
		$this->assertEquals( 'https://example.com/like/123', $object_id, 'object_to_uri should extract ID' );
		$this->assertEquals( 'https://example.com/like/123', $escaped_id, 'esc_url_raw should not change the ID' );

		// Debug: Test that the handler can find the comment with the same logic.
		$handler_comment = Comment::object_id_to_comment( $escaped_id );
		$this->assertNotFalse( $handler_comment, 'Handler should find comment with escaped URL' );

		// Debug: Test manual comment deletion to see if it works.
		$manual_delete_result = \wp_delete_comment( $handler_comment, true );
		$this->assertNotFalse( $manual_delete_result, 'Manual comment deletion should work' );

		// Recreate the comment since we just deleted it.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => '👍',
			)
		);
		\add_comment_meta( $comment_id, 'source_id', 'https://example.com/like/123', true );
		\add_comment_meta( $comment_id, 'protocol', 'activitypub', true );

		// Process the undo.
		Undo::handle_undo( $activity, self::$user_id );

		// Debug: Check if comment can still be found after undo.
		$found_comment_after = Comment::object_id_to_comment( 'https://example.com/like/123' );
		$this->assertFalse( $found_comment_after, 'Comment should not be found after undo' );

		// Verify comment was deleted.
		$comment_after = \get_comment( $comment_id );
		$this->assertNull( $comment_after );
	}

	/**
	 * Test handle_undo with create activity.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_create() {
		// Create a post to comment on.
		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);

		// Create a comment.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'Test comment',
			)
		);
		\add_comment_meta( $comment_id, 'source_id', 'https://example.com/note/123', true );
		\add_comment_meta( $comment_id, 'protocol', 'activitypub', true );

		// Verify comment exists.
		$comment = \get_comment( $comment_id );
		$this->assertNotNull( $comment );

		// Create undo create activity.
		$activity = array(
			'type'   => 'Undo',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'type' => 'Create',
				'id'   => 'https://example.com/note/123',
			),
		);

		// Verify the comment can be found by source_id before processing.
		$found_comment = Comment::object_id_to_comment( 'https://example.com/note/123' );
		$this->assertNotFalse( $found_comment, 'Comment should be found by source_id before undo' );

		// Process the undo.
		Undo::handle_undo( $activity, self::$user_id );

		// Verify comment was deleted.
		$comment_after = \get_comment( $comment_id );
		$this->assertNull( $comment_after );
	}

	/**
	 * Test handle_undo with announce activity.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_announce() {
		// Create a post to announce.
		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);

		// Create an announce comment.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'Shared a post',
			)
		);
		\add_comment_meta( $comment_id, 'source_id', 'https://example.com/announce/456', true );
		\add_comment_meta( $comment_id, 'protocol', 'activitypub', true );

		// Verify comment exists.
		$comment = \get_comment( $comment_id );
		$this->assertNotNull( $comment );

		// Create undo announce activity.
		$activity = array(
			'type'   => 'Undo',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'type' => 'Announce',
				'id'   => 'https://example.com/announce/456',
			),
		);

		// Verify the comment can be found by source_id before processing.
		$found_comment = Comment::object_id_to_comment( 'https://example.com/announce/456' );
		$this->assertNotFalse( $found_comment, 'Comment should be found by source_id before undo' );

		// Process the undo.
		Undo::handle_undo( $activity, self::$user_id );

		// Verify comment was deleted.
		$comment_after = \get_comment( $comment_id );
		$this->assertNull( $comment_after );
	}

	/**
	 * Test handle_undo action hook is fired.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_action_hook() {
		$action_fired  = false;
		$activity_data = null;
		$user_id_data  = null;
		$state_data    = null;

		\add_action(
			'activitypub_handled_undo',
			function ( $activity, $user_id, $state ) use ( &$action_fired, &$activity_data, &$user_id_data, &$state_data ) {
				$action_fired  = true;
				$activity_data = $activity;
				$user_id_data  = $user_id;
				$state_data    = $state;
			},
			10,
			3
		);

		// Test with a valid follow activity that should fire the hook.
		$actor = 'https://example.com/test-actor';

		// Mock HTTP requests for actor metadata.
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor ) {
				return array(
					'id'                => $actor,
					'type'              => 'Person',
					'name'              => 'Test Actor',
					'preferredUsername' => 'testactor',
					'inbox'             => $actor . '/inbox',
					'outbox'            => $actor . '/outbox',
					'url'               => $actor,
				);
			}
		);

		Followers::add_follower( self::$user_id, $actor );

		$user_actor     = Actors::get_by_id( self::$user_id );
		$user_actor_url = $user_actor->get_id();

		$activity = array(
			'type'   => 'Undo',
			'actor'  => $actor,
			'object' => array(
				'type'   => 'Follow',
				'actor'  => $actor,
				'object' => $user_actor_url,
			),
		);

		Undo::handle_undo( $activity, self::$user_id );

		$this->assertTrue( $action_fired );
		$this->assertEquals( $activity, $activity_data );
		$this->assertEquals( self::$user_id, $user_id_data );
		// State can be false if follower removal fails, but action should still fire.
		$this->assertTrue( isset( $state_data ) );
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
		$request = $this->create_mock_request( $request_data );
		$result  = Undo::validate_object( $input_valid, 'object', $request );

		$this->assertEquals( $expected_result, $result, $description );
	}

	/**
	 * Data provider for validate_object tests.
	 *
	 * @return array Test cases with request data, input valid state, expected result, and description.
	 */
	public function validate_object_provider() {
		$valid_undo_activity = array(
			'type'   => 'Undo',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'id'     => 'https://example.com/activity/123',
				'type'   => 'Follow',
				'actor'  => 'https://example.com/actor',
				'object' => 'https://example.com/target',
			),
		);

		return array(
			// Valid cases.
			'valid_undo_activity'               => array(
				$valid_undo_activity,
				true,
				true,
				'Valid Undo activity should pass validation',
			),

			// Non-Undo activities should preserve original state.
			'non_undo_activity_preserves_true'  => array(
				array(
					'type'   => 'Create',
					'actor'  => 'https://example.com/actor',
					'object' => array(
						'type'    => 'Note',
						'content' => 'Hello world',
					),
				),
				true,
				true,
				'Non-Undo activity should preserve original valid state (true)',
			),
			'non_undo_activity_preserves_false' => array(
				array(
					'type'   => 'Create',
					'actor'  => 'https://example.com/actor',
					'object' => array(
						'type'    => 'Note',
						'content' => 'Hello world',
					),
				),
				false,
				false,
				'Non-Undo activity should preserve original valid state (false)',
			),

			// Invalid cases - missing top-level fields.
			'empty_json_params'                 => array(
				array(),
				true,
				false,
				'Empty JSON params should fail validation',
			),
			'missing_type'                      => array(
				array(
					'actor'  => 'https://example.com/actor',
					'object' => array(
						'id'     => 'https://example.com/activity/123',
						'type'   => 'Follow',
						'actor'  => 'https://example.com/actor',
						'object' => 'https://example.com/target',
					),
				),
				true,
				false,
				'Missing type should fail validation',
			),
			'missing_actor'                     => array(
				array(
					'type'   => 'Undo',
					'object' => array(
						'id'     => 'https://example.com/activity/123',
						'type'   => 'Follow',
						'actor'  => 'https://example.com/actor',
						'object' => 'https://example.com/target',
					),
				),
				true,
				false,
				'Missing actor should fail validation',
			),
			'missing_object'                    => array(
				array(
					'type'  => 'Undo',
					'actor' => 'https://example.com/actor',
				),
				true,
				false,
				'Missing object should fail validation',
			),

			// Invalid cases - missing object fields.
			'missing_object_id'                 => array(
				array(
					'type'   => 'Undo',
					'actor'  => 'https://example.com/actor',
					'object' => array(
						'type'   => 'Follow',
						'actor'  => 'https://example.com/actor',
						'object' => 'https://example.com/target',
					),
				),
				true,
				false,
				'Missing object.id should fail validation',
			),
			'missing_object_type'               => array(
				array(
					'type'   => 'Undo',
					'actor'  => 'https://example.com/actor',
					'object' => array(
						'id'     => 'https://example.com/activity/123',
						'actor'  => 'https://example.com/actor',
						'object' => 'https://example.com/target',
					),
				),
				true,
				false,
				'Missing object.type should fail validation',
			),
			'missing_object_actor'              => array(
				array(
					'type'   => 'Undo',
					'actor'  => 'https://example.com/actor',
					'object' => array(
						'id'     => 'https://example.com/activity/123',
						'type'   => 'Follow',
						'object' => 'https://example.com/target',
					),
				),
				true,
				false,
				'Missing object.actor should fail validation',
			),
			'missing_object_object'             => array(
				array(
					'type'   => 'Undo',
					'actor'  => 'https://example.com/actor',
					'object' => array(
						'id'    => 'https://example.com/activity/123',
						'type'  => 'Follow',
						'actor' => 'https://example.com/actor',
					),
				),
				true,
				false,
				'Missing object.object should fail validation',
			),
		);
	}

	/**
	 * Create a mock WP_REST_Request object for testing.
	 *
	 * @param array $json_params The JSON parameters to return.
	 * @return \WP_REST_Request Mock request object.
	 */
	private function create_mock_request( $json_params ) {
		$request = $this->createMock( \WP_REST_Request::class );
		$request->method( 'get_json_params' )->willReturn( $json_params );
		return $request;
	}
}
