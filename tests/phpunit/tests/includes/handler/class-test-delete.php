<?php
/**
 * Test file for Delete handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Handler\Delete;
use Activitypub\Tombstone;

/**
 * Test class for Delete handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Delete
 */
class Test_Delete extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Initialize Delete handler for all tests.
		Delete::init();
	}

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		\add_filter( 'pre_get_remote_metadata_by_actor', array( self::class, 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( self::class, 'get_remote_metadata_by_actor' ) );

		parent::tear_down();
	}

	/**
	 * Test delete interactions.
	 */
	public function test_delete_interactions() {
		self::factory()->comment->create_many(
			5,
			array(
				'author_url'   => get_author_posts_url( self::$user_id ),
				'comment_meta' => array( 'protocol' => 'activitypub' ),
			)
		);

		Delete::delete_interactions( get_author_posts_url( self::$user_id ) );

		$this->assertEmpty( get_comments( array( 'user_id' => self::$user_id ) ) );
	}

	/**
	 * Test delete_interactions action deletes comments from actor.
	 *
	 * @covers ::delete_interactions
	 */
	public function test_delete_actor_interactions() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);

		$actor_url = 'https://example.com/users/testactor';

		// Mock actor metadata.
		$http_get_filter = static function () use ( $actor_url ) {
			return array(
				'type'              => 'Person',
				'name'              => 'Test Actor',
				'preferredUsername' => 'testactor',
				'id'                => $actor_url,
				'url'               => 'https://example.com/@testactor',
				'inbox'             => $actor_url . '/inbox',
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $http_get_filter );

		$actor = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $actor_url );

		// Create test comments with ActivityPub protocol metadata.
		$comment_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$comment_id = self::factory()->comment->create(
				array(
					'comment_post_ID'    => $post_id,
					'comment_author'     => 'Test Actor',
					'comment_author_url' => $actor_url,
					'comment_content'    => "Test comment $i",
				)
			);
			// Add ActivityPub protocol metadata and remote actor reference.
			\add_comment_meta( $comment_id, 'protocol', 'activitypub' );
			\add_comment_meta( $comment_id, '_activitypub_remote_actor_id', $actor->ID );
			$comment_ids[] = $comment_id;
		}

		// Create a non-ActivityPub comment that should not be deleted.
		$other_comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_author'  => 'Other User',
				'comment_content' => 'Other comment',
			)
		);

		// Verify comments exist.
		foreach ( $comment_ids as $comment_id ) {
			$this->assertNotNull( \get_comment( $comment_id ), "Comment $comment_id should exist" );
		}
		$this->assertNotNull( \get_comment( $other_comment_id ), 'Other comment should exist' );

		// Trigger the delete_interactions action with remote actor ID.
		\do_action( 'activitypub_delete_remote_actor_interactions', $actor->ID );

		// Verify ActivityPub comments were deleted.
		foreach ( $comment_ids as $comment_id ) {
			$this->assertNull( \get_comment( $comment_id ), "Comment $comment_id should be deleted" );
		}

		// Verify non-ActivityPub comment still exists.
		$this->assertNotNull( \get_comment( $other_comment_id ), 'Other comment should not be deleted' );

		// Clean up.
		\remove_filter( 'activitypub_pre_http_get_remote_object', $http_get_filter );
	}

	/**
	 * Test delete_interactions with no comments returns false.
	 *
	 * @covers ::delete_interactions
	 */
	public function test_delete_actor_interactions_no_comments() {
		$nonexistent_actor_id = 999999;

		// Mock the return value to capture it.
		$result                     = null;
		$delete_interactions_action = static function ( $actor_id ) use ( &$result ) {
			$result = Delete::delete_interactions( $actor_id );
		};
		\add_action( 'activitypub_delete_remote_actor_interactions', $delete_interactions_action, 5 );

		\do_action( 'activitypub_delete_remote_actor_interactions', $nonexistent_actor_id );

		// Verify it returns false when no comments exist.
		$this->assertFalse( $result, 'Should return false when no comments exist' );

		\remove_action( 'activitypub_delete_remote_actor_interactions', $delete_interactions_action, 5 );
	}

	/**
	 * Test delete_posts action deletes posts from actor.
	 *
	 * @covers ::delete_posts
	 */
	public function test_delete_actor_posts() {
		$actor_url = 'https://example.com/users/testactor';

		// Mock actor metadata.
		$http_get_filter = static function () use ( $actor_url ) {
			return array(
				'type'              => 'Person',
				'name'              => 'Test Actor',
				'preferredUsername' => 'testactor',
				'id'                => $actor_url,
				'url'               => 'https://example.com/@testactor',
				'inbox'             => $actor_url . '/inbox',
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $http_get_filter );

		$actor = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $actor_url );

		// Create test posts attributed to the actor.
		$post_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$post_id = self::factory()->post->create(
				array(
					'post_type'   => \Activitypub\Collection\Posts::POST_TYPE,
					'post_author' => $actor->ID,
					'post_title'  => "Test Post $i",
					'post_status' => 'publish',
				)
			);
			// Add the remote actor ID meta that Posts::get_by_remote_actor() looks for.
			\add_post_meta( $post_id, '_activitypub_remote_actor_id', $actor->ID );
			$post_ids[] = $post_id;
		}

		// Verify posts exist.
		foreach ( $post_ids as $post_id ) {
			$this->assertNotNull( \get_post( $post_id ), "Post $post_id should exist" );
		}

		// Trigger the delete_posts action with remote actor ID.
		\do_action( 'activitypub_delete_remote_actor_posts', $actor->ID );

		// Verify posts were deleted.
		foreach ( $post_ids as $post_id ) {
			$this->assertNull( \get_post( $post_id ), "Post $post_id should be deleted" );
		}

		// Clean up.
		\remove_filter( 'activitypub_pre_http_get_remote_object', $http_get_filter );
	}

	/**
	 * Test delete_posts with no posts returns false.
	 *
	 * @covers ::delete_posts
	 */
	public function test_delete_actor_posts_no_posts() {
		$nonexistent_actor_id = 999999;

		// Mock the return value to capture it.
		$result              = null;
		$delete_posts_action = static function ( $actor_id ) use ( &$result ) {
			$result = Delete::delete_posts( $actor_id );
		};
		\add_action( 'activitypub_delete_remote_actor_posts', $delete_posts_action, 5 );

		\do_action( 'activitypub_delete_remote_actor_posts', $nonexistent_actor_id );

		// Verify it returns false when no posts exist.
		$this->assertFalse( $result, 'Should return false when no posts exist' );

		\remove_action( 'activitypub_delete_remote_actor_posts', $delete_posts_action, 5 );
	}

	/**
	 * Test delete_object with Tombstone type object having only an id.
	 *
	 * This tests the scenario where a Delete activity contains a Tombstone object
	 * with only an 'id' field, which is common in ActivityPub implementations.
	 *
	 * @covers ::delete_object
	 * @covers ::maybe_delete_interaction
	 * @covers ::maybe_delete_post
	 */
	public function test_delete_object_with_tombstone_id_only() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);

		$actor_url  = 'https://example.com/users/testactor';
		$object_url = 'https://example.com/objects/123';

		// Create a comment (interaction) that will be deleted.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => $post_id,
				'comment_type'       => 'like',
				'comment_author'     => 'Test Actor',
				'comment_author_url' => $actor_url,
				'comment_meta'       => array(
					'protocol'  => 'activitypub',
					'source_id' => $object_url,
				),
			)
		);

		// Create a Delete activity with Tombstone type and only an id.
		$activity = array(
			'type'   => 'Delete',
			'actor'  => $actor_url,
			'object' => array(
				'type' => 'Tombstone',
				'id'   => $object_url,
			),
		);

		// Mock HTTP request to return 410 Gone for the tombstone check.
		$filter = function ( $preempt, $args, $url ) use ( $object_url ) {
			if ( $url === $object_url ) {
				return array(
					'body'     => '',
					'response' => array( 'code' => 410 ),
				);
			}
			return $preempt;
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		// Verify comment exists before delete.
		$this->assertInstanceOf( 'WP_Comment', \get_comment( $comment_id ), 'Comment should exist before delete' );

		// Call delete_object.
		Delete::delete_object( $activity, array( self::$user_id ) );

		// Verify comment was deleted.
		$this->assertNull( \get_comment( $comment_id ), 'Comment should be deleted after delete_object' );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test delete_object with Tombstone type for post deletion.
	 *
	 * Tests deleting a post from the Posts collection using a Tombstone object.
	 *
	 * @covers ::delete_object
	 * @covers ::maybe_delete_post
	 */
	public function test_delete_object_tombstone_deletes_post() {
		$object_url = 'https://example.com/notes/456';
		$actor_url  = 'https://example.com/users/testactor';

		// Create a post in the Posts collection.
		$post_id = \wp_insert_post(
			array(
				'post_type'    => \Activitypub\Collection\Posts::POST_TYPE,
				'post_title'   => 'Test Note',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'guid'         => $object_url,
			)
		);

		// Create Delete activity with Tombstone.
		$activity = array(
			'type'   => 'Delete',
			'actor'  => $actor_url,
			'object' => array(
				'type' => 'Tombstone',
				'id'   => $object_url,
			),
		);

		// Mock HTTP request to return 410 Gone for the tombstone check.
		$filter = function ( $preempt, $args, $url ) use ( $object_url ) {
			if ( $url === $object_url ) {
				return array(
					'body'     => '',
					'response' => array( 'code' => 410 ),
				);
			}
			return $preempt;
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		// Verify post exists before delete.
		$this->assertInstanceOf( 'WP_Post', \get_post( $post_id ), 'Post should exist before delete' );

		// Call delete_object.
		Delete::delete_object( $activity, array( self::$user_id ) );

		// Verify post was deleted.
		$this->assertNull( \get_post( $post_id ), 'Post should be deleted after delete_object' );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test delete_object with Tombstone but no matching content.
	 *
	 * Verifies that delete_object handles gracefully when there's nothing to delete.
	 *
	 * @covers ::delete_object
	 * @covers ::maybe_delete_interaction
	 * @covers ::maybe_delete_post
	 */
	public function test_delete_object_tombstone_no_matching_content() {
		$object_url = 'https://example.com/nonexistent/789';
		$actor_url  = 'https://example.com/users/testactor';

		// Create Delete activity with Tombstone for non-existent content.
		$activity = array(
			'type'   => 'Delete',
			'actor'  => $actor_url,
			'object' => array(
				'type' => 'Tombstone',
				'id'   => $object_url,
			),
		);

		// Mock HTTP request to return 410 Gone for the tombstone check.
		$filter = function ( $preempt, $args, $url ) use ( $object_url ) {
			if ( $url === $object_url ) {
				return array(
					'body'     => '',
					'response' => array( 'code' => 410 ),
				);
			}
			return $preempt;
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		// Track if the action was fired.
		$action_fired   = false;
		$action_success = null;

		$handled_delete_action = static function ( $act, $users, $success ) use ( &$action_fired, &$action_success ) {
			$action_fired   = true;
			$action_success = $success;
		};
		\add_action( 'activitypub_handled_delete', $handled_delete_action, 10, 3 );

		// Call delete_object - should not throw errors.
		Delete::delete_object( $activity, array( self::$user_id ) );

		// Verify action was fired but success is false (nothing to delete).
		$this->assertTrue( $action_fired, 'activitypub_handled_delete action should fire' );
		$this->assertFalse( $action_success, 'Success should be false when nothing was deleted' );

		\remove_filter( 'pre_http_request', $filter );
		\remove_action( 'activitypub_handled_delete', $handled_delete_action, 10 );
	}

	/**
	 * Test delete_object with Tombstone as string ID.
	 *
	 * Tests the case where the object is just a string URL (without type field).
	 *
	 * @covers ::delete_object
	 * @covers ::maybe_delete_interaction
	 */
	public function test_delete_object_with_tombstone_string_id() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
			)
		);

		$actor_url  = 'https://example.com/users/testactor';
		$object_url = 'https://example.com/objects/string-test';

		// Create a comment.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => $post_id,
				'comment_type'       => 'announce',
				'comment_author'     => 'Test Actor',
				'comment_author_url' => $actor_url,
				'comment_meta'       => array(
					'protocol'  => 'activitypub',
					'source_id' => $object_url,
				),
			)
		);

		// Create Delete activity with object as string (common pattern).
		$activity = array(
			'type'   => 'Delete',
			'actor'  => $actor_url,
			'object' => $object_url,
		);

		// Mock HTTP request to return 404 Not Found for the tombstone check.
		$filter = function ( $preempt, $args, $url ) use ( $object_url ) {
			if ( $url === $object_url ) {
				return array(
					'body'     => '',
					'response' => array( 'code' => 404 ),
				);
			}
			return $preempt;
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		// Verify comment exists.
		$this->assertInstanceOf( 'WP_Comment', \get_comment( $comment_id ), 'Comment should exist before delete' );

		// Call delete_object.
		Delete::delete_object( $activity, array( self::$user_id ) );

		// Verify comment was deleted.
		$this->assertNull( \get_comment( $comment_id ), 'Comment should be deleted when object is string URL' );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Get remote metadata by actor.
	 *
	 * @param string $value Value.
	 * @param string $actor Actor.
	 * @return array
	 */
	public static function get_remote_metadata_by_actor( $value, $actor ) {
		return array(
			'name' => 'Test Actor',
			'icon' => array(
				'url' => 'https://example.com/icon',
			),
			'url'  => $actor,
			'id'   => $actor,
		);
	}

	/**
	 * Test maybe_bury adds URL to tombstone registry for Delete activity with object.
	 *
	 * @covers ::maybe_bury
	 */
	public function test_maybe_bury_adds_url_for_delete_activity() {
		$object_url = 'https://example.com/posts/bury-test-' . time();

		// Create a mock activity object.
		$object = new Base_Object();
		$object->set_id( $object_url );
		$object->set_url( $object_url );
		$object->set_type( 'Note' );

		$activity = new Activity();
		$activity->set_type( 'Delete' );
		$activity->set_object( $object );

		// Verify URL is not in tombstone registry.
		$this->assertFalse( Tombstone::exists_local( $object_url ) );

		// Trigger maybe_bury.
		Delete::maybe_bury( 1, $activity );

		// Verify URL was added to tombstone registry.
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		// Clean up.
		\delete_option( 'activitypub_tombstone_urls' );
	}

	/**
	 * Test maybe_bury handles Delete activity with string object.
	 *
	 * @covers ::maybe_bury
	 */
	public function test_maybe_bury_handles_string_object() {
		$object_url = 'https://example.com/posts/string-object-' . time();

		$activity = new Activity();
		$activity->set_type( 'Delete' );
		$activity->set_object( $object_url );

		// Verify URL is not in tombstone registry.
		$this->assertFalse( Tombstone::exists_local( $object_url ) );

		// Trigger maybe_bury.
		Delete::maybe_bury( 1, $activity );

		// Verify URL was added to tombstone registry.
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		// Clean up.
		\delete_option( 'activitypub_tombstone_urls' );
	}

	/**
	 * Test maybe_bury ignores non-Delete activities.
	 *
	 * @covers ::maybe_bury
	 */
	public function test_maybe_bury_ignores_non_delete_activities() {
		$object_url = 'https://example.com/posts/non-delete-' . time();

		$object = new Base_Object();
		$object->set_id( $object_url );
		$object->set_url( $object_url );
		$object->set_type( 'Note' );

		// Test with Create activity.
		$activity = new Activity();
		$activity->set_type( 'Create' );
		$activity->set_object( $object );

		Delete::maybe_bury( 1, $activity );

		// URL should NOT be in tombstone registry.
		$this->assertFalse( Tombstone::exists_local( $object_url ) );

		// Test with Update activity.
		$activity->set_type( 'Update' );
		Delete::maybe_bury( 1, $activity );

		// URL should still NOT be in tombstone registry.
		$this->assertFalse( Tombstone::exists_local( $object_url ) );
	}

	/**
	 * Test maybe_bury handles Delete activity with null object.
	 *
	 * @covers ::maybe_bury
	 */
	public function test_maybe_bury_handles_null_object() {
		$activity = new Activity();
		$activity->set_type( 'Delete' );
		// Object is null/not set.

		// This should not throw any errors.
		Delete::maybe_bury( 1, $activity );

		// Just verify no exception was thrown.
		$this->assertTrue( true );
	}

	/**
	 * Test maybe_bury buries both ID and URL when they differ.
	 *
	 * @covers ::maybe_bury
	 */
	public function test_maybe_bury_buries_both_id_and_url() {
		$object_id  = 'https://example.com/posts/id-' . time();
		$object_url = 'https://example.com/@user/posts/url-' . time();

		$object = new Base_Object();
		$object->set_id( $object_id );
		$object->set_url( $object_url );
		$object->set_type( 'Note' );

		$activity = new Activity();
		$activity->set_type( 'Delete' );
		$activity->set_object( $object );

		Delete::maybe_bury( 1, $activity );

		// Both ID and URL should be in tombstone registry.
		$this->assertTrue( Tombstone::exists_local( $object_id ) );
		$this->assertTrue( Tombstone::exists_local( $object_url ) );

		// Clean up.
		\delete_option( 'activitypub_tombstone_urls' );
	}
}
