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
	 * Test handle_undo with follow activities.
	 *
	 * @dataProvider follow_undo_provider
	 * @covers ::handle_undo
	 *
	 * @param string $actor_url     The actor URL to test with.
	 * @param string $description   Description of the test case.
	 */
	public function test_handle_undo_follow( $actor_url, $description ) {
		// Mock HTTP requests for actor metadata.
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_url ) {
				return array(
					'id'                => $actor_url,
					'type'              => 'Person',
					'name'              => 'Test Actor',
					'preferredUsername' => 'testactor',
					'inbox'             => $actor_url . '/inbox',
					'outbox'            => $actor_url . '/outbox',
					'url'               => $actor_url,
				);
			}
		);

		// Add follower first.
		$add_result = Followers::add_follower( self::$user_id, $actor_url );
		$this->assertIsInt( $add_result, $description . ' - Adding follower should return post ID' );

		// Verify follower was added.
		$followers = Followers::get_followers( self::$user_id );
		$this->assertNotEmpty( $followers, $description . ' - Should have followers after adding one' );

		$user_actor     = Actors::get_by_id( self::$user_id );
		$user_actor_url = $user_actor->get_id();

		// Verify user actor URL exists.
		$this->assertNotEmpty( $user_actor_url, $description . ' - User actor URL should not be empty' );

		// Create undo follow activity.
		$activity = array(
			'type'   => 'Undo',
			'actor'  => $actor_url,
			'object' => array(
				'type'   => 'Follow',
				'actor'  => $actor_url,
				'object' => $user_actor_url,
			),
		);

		// Process the undo.
		Undo::handle_undo( $activity, self::$user_id );

		// Verify follower was removed.
		$followers_after = Followers::get_followers( self::$user_id );
		$this->assertEmpty( $followers_after, $description . ' - Should have no followers after undo' );
	}

	/**
	 * Data provider for follow undo tests.
	 *
	 * @return array Test cases with actor URLs and descriptions.
	 */
	public function follow_undo_provider() {
		return array(
			'basic_follow'          => array(
				'https://example.com/test-actor',
				'Basic follow undo should remove follower',
			),
			'follow_with_subdomain' => array(
				'https://social.example.com/users/testactor',
				'Follow undo with subdomain should work',
			),
			'follow_with_path'      => array(
				'https://example.com/users/testactor',
				'Follow undo with user path should work',
			),
		);
	}

	/**
	 * Test handle_undo with comment-based activities (Like, Create, Announce).
	 *
	 * @dataProvider comment_undo_provider
	 * @covers ::handle_undo
	 *
	 * @param string $activity_type  The type of activity to undo.
	 * @param string $comment_content The content for the comment.
	 * @param string $source_id      The source ID for the comment.
	 * @param string $description    Description of the test case.
	 */
	public function test_handle_undo_comment_activities( $activity_type, $comment_content, $source_id, $description ) {
		// Create a post for the comment.
		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);

		// Create the comment with metadata.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => $comment_content,
			)
		);
		\add_comment_meta( $comment_id, 'source_id', $source_id, true );
		\add_comment_meta( $comment_id, 'protocol', 'activitypub', true );

		// Verify comment exists.
		$comment = \get_comment( $comment_id );
		$this->assertNotNull( $comment, $description . ' - Comment should exist before undo' );

		// Create undo activity.
		$activity = array(
			'type'   => 'Undo',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'type' => $activity_type,
				'id'   => $source_id,
			),
		);

		// Verify the comment can be found by source_id before processing.
		$found_comment = Comment::object_id_to_comment( $source_id );
		$this->assertNotFalse( $found_comment, $description . ' - Comment should be found by source_id before undo' );

		// Process the undo.
		Undo::handle_undo( $activity, self::$user_id );

		// Verify comment was deleted.
		$comment_after = \get_comment( $comment_id );
		$this->assertNull( $comment_after, $description . ' - Comment should be deleted after undo' );
	}

	/**
	 * Data provider for comment-based undo tests.
	 *
	 * @return array Test cases with activity type, comment content, source ID, and description.
	 */
	public function comment_undo_provider() {
		return array(
			'undo_like'     => array(
				'Like',
				'👍',
				'https://example.com/like/123',
				'Undo Like activity should delete like comment',
			),
			'undo_create'   => array(
				'Create',
				'Test comment',
				'https://example.com/note/123',
				'Undo Create activity should delete created comment',
			),
			'undo_announce' => array(
				'Announce',
				'Shared a post',
				'https://example.com/announce/456',
				'Undo Announce activity should delete announce comment',
			),
		);
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
