<?php
/**
 * Test file for Undo Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Inbox as Inbox_Collection;
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
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		// Enable like and repost comment types for testing.
		\update_option( 'activitypub_allow_likes', '1' );
		\update_option( 'activitypub_allow_reposts', '1' );
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
		$mock_actor_metadata = function () use ( $actor_url ) {
			return array(
				'id'                => $actor_url,
				'type'              => 'Person',
				'name'              => 'Test Actor',
				'preferredUsername' => 'testactor',
				'inbox'             => $actor_url . '/inbox',
				'outbox'            => $actor_url . '/outbox',
				'url'               => $actor_url,
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );

		// Add follower first by simulating a Follow activity through the inbox.
		$user_actor     = Actors::get_by_id( self::$user_id );
		$user_actor_url = $user_actor->get_id();

		// Verify user actor URL exists.
		$this->assertNotEmpty( $user_actor_url, $description . ' - User actor URL should not be empty' );

		// Simulate Follow activity.
		$follow_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $actor_url . '/follow/' . time(),
			'type'     => 'Follow',
			'actor'    => $actor_url,
			'object'   => $user_actor_url,
		);

		// Add the Follow activity to the inbox first.
		$activity_object = Activity::init_from_array( $follow_activity );
		Inbox_Collection::add( $activity_object, self::$user_id );

		// Call the Follow handler directly to add the follower.
		\Activitypub\Handler\Follow::handle_follow( $follow_activity, self::$user_id );

		// Verify follower was added.
		$followers = Followers::get_many( self::$user_id );
		$this->assertNotEmpty( $followers, $description . ' - Should have followers after Follow activity' );

		// Create undo follow activity.
		$undo_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $actor_url . '/undo/' . time(),
			'type'     => 'Undo',
			'actor'    => $actor_url,
			'object'   => array(
				'id'     => $follow_activity['id'],
				'type'   => 'Follow',
				'actor'  => $actor_url,
				'object' => $user_actor_url,
			),
		);

		// Call the Undo handler directly.
		Undo::handle_undo( $undo_activity, self::$user_id );

		// Verify follower was removed.
		$followers_after = Followers::get_many( self::$user_id );
		$this->assertEmpty( $followers_after, $description . ' - Should have no followers after Undo activity' );

		// Clean up filter.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );
	}

	/**
	 * Test that an Undo removes a follower whose Follow is no longer in the inbox.
	 *
	 * @dataProvider undo_follow_without_inbox_item_provider
	 * @covers ::handle_undo
	 *
	 * @param string       $undo_actor  The actor of the Undo.
	 * @param array|string $undo_object The object of the Undo, the followed actor is filled in.
	 * @param bool         $removed     Whether the follower is removed.
	 * @param bool         $blog        Whether the blog actor is followed instead of the user.
	 * @param string|null  $inbox_actor The actor of a Follow with the same ID in the inbox, if any.
	 */
	public function test_handle_undo_follow_without_inbox_item( $undo_actor, $undo_object, $removed, $blog = false, $inbox_actor = null ) {
		$follower = 'https://example.com/users/follower';
		$other    = 'https://example.com/users/other';

		if ( $blog ) {
			\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		}
		$user_id = $blog ? Actors::BLOG_USER_ID : self::$user_id;

		$mock_actor_metadata = function ( $pre, $url ) {
			return array(
				'id'                => $url,
				'type'              => 'Person',
				'preferredUsername' => 'follower',
				'inbox'             => $url . '/inbox',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata, 10, 2 );

		// Followers without a Follow in the inbox, like after an inbox purge.
		Followers::add( $user_id, $follower );
		Followers::add( $user_id, $other );

		if ( \is_array( $undo_object ) && ! isset( $undo_object['object'] ) ) {
			$undo_object['object'] = Actors::get_by_id( $user_id )->get_id();
		}

		// A Follow with the same ID by another actor is in the inbox.
		if ( $inbox_actor ) {
			Inbox_Collection::add(
				Activity::init_from_array(
					array(
						'id'     => $undo_object['id'],
						'type'   => 'Follow',
						'actor'  => $inbox_actor,
						'object' => $undo_object['object'],
					)
				),
				$user_id
			);
		}

		Undo::handle_undo(
			array(
				'id'     => 'https://example.com/undo/1',
				'type'   => 'Undo',
				'actor'  => $undo_actor,
				'object' => $undo_object,
			),
			$user_id
		);

		$followers = \wp_list_pluck( Followers::get_many( $user_id ), 'guid' );
		$this->assertSame( ! $removed, \in_array( $follower, $followers, true ) );
		$this->assertContains( $other, $followers, 'Only the actor of the Undo ends its follow.' );

		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Data provider for Undo of a Follow that is not in the inbox.
	 *
	 * @return array[]
	 */
	public function undo_follow_without_inbox_item_provider() {
		$follower = 'https://example.com/users/follower';
		$other    = 'https://example.com/users/other';
		$follow   = array(
			'id'    => 'https://example.com/follows/1',
			'type'  => 'Follow',
			'actor' => $follower,
		);

		return array(
			'embedded follow'                => array( $follower, $follow, true ),
			'embedded follow of the blog'    => array( $follower, $follow, true, true ),
			'embedded follow, other actor'   => array( $other, $follow, false ),
			'other actor in embedded follow' => array( $follower, \array_merge( $follow, array( 'actor' => $other ) ), false ),
			'followed actor is not local'    => array( $follower, \array_merge( $follow, array( 'object' => 'https://example.net/users/someone' ) ), false ),
			'bare follow id'                 => array( $follower, 'https://example.com/follows/1', false ),
			'follow id of another actor'     => array( $follower, $follow, false, false, $other ),
		);
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
	 * Test handle_undo with comment-related activities (Like, Create, Announce).
	 *
	 * @dataProvider comment_activities_undo_provider
	 * @covers ::handle_undo
	 *
	 * @param string $actor_url     The actor URL to test with.
	 * @param string $activity_type The type of activity being undone.
	 * @param string $description   Description of the test case.
	 */
	public function test_handle_undo_comment_activities( $actor_url, $activity_type, $description ) {
		// Mock HTTP requests for actor metadata.
		$mock_actor_metadata = function () use ( $actor_url ) {
			return array(
				'id'                => $actor_url,
				'type'              => 'Person',
				'name'              => 'Test Actor',
				'preferredUsername' => 'testactor',
				'inbox'             => $actor_url . '/inbox',
				'outbox'            => $actor_url . '/outbox',
				'url'               => $actor_url,
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );

		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_title'  => 'Test Post for ' . $description,
			)
		);

		$post_url = \get_permalink( $post_id );

		// Create the activity that will become a comment.
		$activity_id     = $actor_url . '/activities/' . $activity_type . '/' . time();
		$create_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $activity_id,
			'type'     => $activity_type,
			'actor'    => $actor_url,
			'object'   => $post_url,
		);

		// Add the activity to the inbox first.
		$activity_object = Activity::init_from_array( $create_activity );
		Inbox_Collection::add( $activity_object, self::$user_id );

		// Call the appropriate handler directly to create the comment.
		$handler_class  = '\\Activitypub\\Handler\\' . $activity_type;
		$handler_method = 'handle_' . strtolower( $activity_type );
		$handler_class::$handler_method( $create_activity, self::$user_id );

		// Find the comment that was created.
		$found_comment = Comment::object_id_to_comment( $activity_id );
		$this->assertNotFalse( $found_comment, $description . ' - Comment should be created by ' . $activity_type . ' activity' );

		$comment_id = $found_comment->comment_ID;
		$comment    = \get_comment( $comment_id );
		$this->assertNotNull( $comment, $description . ' - Comment should exist before undo' );

		// Create undo activity.
		$undo_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $actor_url . '/undo/' . time(),
			'type'     => 'Undo',
			'actor'    => $actor_url,
			'object'   => $create_activity,
		);

		// Call the Undo handler directly.
		Undo::handle_undo( $undo_activity, self::$user_id );

		// Verify comment was deleted.
		$comment_after = \get_comment( $comment_id );
		$this->assertNull( $comment_after, $description . ' - Comment should be deleted after Undo activity' );

		// Clean up filter.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );
	}

	/**
	 * Test that an Undo from a different actor cannot delete another actor's interaction.
	 *
	 * Regression: the Undo handler resolved the target purely by the activity id and
	 * force-deleted the comment behind it, without confirming the Undo sender created
	 * the original activity.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_comment_rejects_foreign_actor() {
		$owner_url    = 'https://example.com/owner-actor';
		$attacker_url = 'https://attacker.example/evil-actor';

		$mock_actor_metadata = function () use ( $owner_url ) {
			return array(
				'id'                => $owner_url,
				'type'              => 'Person',
				'name'              => 'Owner Actor',
				'preferredUsername' => 'owner',
				'inbox'             => $owner_url . '/inbox',
				'outbox'            => $owner_url . '/outbox',
				'url'               => $owner_url,
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );

		$post_id  = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$post_url = \get_permalink( $post_id );

		// The owner Likes the post, which creates a comment.
		$activity_id   = $owner_url . '/activities/Like/' . time();
		$like_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $activity_id,
			'type'     => 'Like',
			'actor'    => $owner_url,
			'object'   => $post_url,
		);
		Inbox_Collection::add( Activity::init_from_array( $like_activity ), self::$user_id );
		\Activitypub\Handler\Like::handle_like( $like_activity, self::$user_id );

		$found_comment = Comment::object_id_to_comment( $activity_id );
		$this->assertNotFalse( $found_comment, 'Owner Like should create a comment.' );
		$comment_id = $found_comment->comment_ID;

		// A different actor tries to undo the owner's Like.
		$undo_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $attacker_url . '/undo/' . time(),
			'type'     => 'Undo',
			'actor'    => $attacker_url,
			'object'   => $like_activity,
		);
		Undo::handle_undo( $undo_activity, self::$user_id );

		// The comment must survive: the attacker does not own the Like.
		$this->assertNotNull(
			\get_comment( $comment_id ),
			'A foreign actor must not be able to undo (delete) another actor\'s interaction.'
		);

		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );
	}

	/**
	 * Test that an Undo with a present-but-unparseable actor does not skip the ownership check.
	 *
	 * Regression: a malformed actor resolves to null via object_to_uri(), which Inbox::undo()
	 * would treat as "no actor" and skip ownership — re-opening the undo-by-id attack.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_comment_rejects_unparseable_actor() {
		$owner_url = 'https://example.com/owner-actor';

		$mock_actor_metadata = function () use ( $owner_url ) {
			return array(
				'id'                => $owner_url,
				'type'              => 'Person',
				'name'              => 'Owner Actor',
				'preferredUsername' => 'owner',
				'inbox'             => $owner_url . '/inbox',
				'outbox'            => $owner_url . '/outbox',
				'url'               => $owner_url,
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );

		$post_id  = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$post_url = \get_permalink( $post_id );

		$activity_id   = $owner_url . '/activities/Like/' . time();
		$like_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $activity_id,
			'type'     => 'Like',
			'actor'    => $owner_url,
			'object'   => $post_url,
		);
		Inbox_Collection::add( Activity::init_from_array( $like_activity ), self::$user_id );
		\Activitypub\Handler\Like::handle_like( $like_activity, self::$user_id );

		$found_comment = Comment::object_id_to_comment( $activity_id );
		$this->assertNotFalse( $found_comment, 'Owner Like should create a comment.' );
		$comment_id = $found_comment->comment_ID;

		// Undo with an actor object that has no id/url, so object_to_uri() returns null.
		$undo_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => 'https://attacker.example/undo/' . time(),
			'type'     => 'Undo',
			'actor'    => array( 'type' => 'Person' ),
			'object'   => $like_activity,
		);
		Undo::handle_undo( $undo_activity, self::$user_id );

		$this->assertNotNull(
			\get_comment( $comment_id ),
			'An unparseable actor must not skip the ownership check.'
		);

		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );
	}

	/**
	 * Data provider for comment-based undo tests.
	 *
	 * @return array Test cases with actor URL, activity type, and description.
	 */
	public function comment_activities_undo_provider() {
		return array(
			'undo_like' => array(
				'https://example.com/test-actor',
				'Like',
				'Undo Like activity should delete like comment',
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

		$action_hook_callback = function ( $activity, $user_id, $state ) use ( &$action_fired, &$activity_data, &$user_id_data, &$state_data ) {
			$action_fired  = true;
			$activity_data = $activity;
			$user_id_data  = $user_id;
			$state_data    = $state;
		};
		\add_action( 'activitypub_handled_undo', $action_hook_callback, 10, 3 );

		// Test with a valid follow activity that should fire the hook.
		$actor = 'https://example.com/test-actor';

		// Mock HTTP requests for actor metadata.
		$mock_actor_metadata = function () use ( $actor ) {
			return array(
				'id'                => $actor,
				'type'              => 'Person',
				'name'              => 'Test Actor',
				'preferredUsername' => 'testactor',
				'inbox'             => $actor . '/inbox',
				'outbox'            => $actor . '/outbox',
				'url'               => $actor,
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );

		$user_actor     = Actors::get_by_id( self::$user_id );
		$user_actor_url = $user_actor->get_id();

		// Simulate Follow activity first.
		$follow_activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $actor . '/follow/' . time(),
			'type'     => 'Follow',
			'actor'    => $actor,
			'object'   => $user_actor_url,
		);

		// Add the Follow activity to the inbox first.
		$activity_object = Activity::init_from_array( $follow_activity );
		Inbox_Collection::add( $activity_object, self::$user_id );

		\Activitypub\Handler\Follow::handle_follow( $follow_activity, self::$user_id );

		// Create Undo activity.
		$activity = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => $actor . '/undo/' . time(),
			'type'     => 'Undo',
			'actor'    => $actor,
			'object'   => array(
				'id'     => $follow_activity['id'],
				'type'   => 'Follow',
				'actor'  => $actor,
				'object' => $user_actor_url,
			),
		);

		// Call the Undo handler directly.
		Undo::handle_undo( $activity, self::$user_id );

		$this->assertTrue( $action_fired );
		$this->assertEquals( $activity, $activity_data );
		$this->assertIsArray( $user_id_data, 'User ID should be an array' );
		$this->assertContains( self::$user_id, $user_id_data, 'Array should contain user ID' );
		// State can be false if follower removal fails, but action should still fire.
		$this->assertTrue( isset( $state_data ) );

		// Clean up hooks.
		\remove_action( 'activitypub_handled_undo', $action_hook_callback );
		\remove_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );
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

	/**
	 * The deprecated check still refuses what it refused: it asks the schema of its type.
	 *
	 * @covers ::validate_object
	 */
	public function test_deprecated_validate_object_asks_the_schema() {
		$this->setExpectedDeprecated( 'Activitypub\\Handler\\Undo::validate_object' );

		$request = new \WP_REST_Request( 'POST' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body(
			\wp_json_encode(
				array(
					'type'  => 'Undo',
					'actor' => 'https://example.com/actor',
				)
			)
		);

		$this->assertFalse( Undo::validate_object( true, null, $request ), 'An invalid Undo is refused.' );

		$request->set_body( \wp_json_encode( array( 'type' => 'Like' ) ) );
		$this->assertTrue( Undo::validate_object( true, null, $request ), 'Another type keeps the result.' );
	}
}
