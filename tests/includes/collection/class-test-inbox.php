<?php
/**
 * Test file for Inbox collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Inbox;
use Activitypub\Post_Types;

/**
 * Test class for Inbox collection.
 *
 * @coversDefaultClass \Activitypub\Collection\Inbox
 */
class Test_Inbox extends \WP_UnitTestCase {
	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
	}

	/**
	 * Test adding an activity to the inbox and verify post meta is set correctly.
	 *
	 * @covers ::add
	 */
	public function test_add_activity_with_post_meta() {
		// Create a test activity.
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/123' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/456' );
		$object->set_type( 'Note' );
		$object->set_content( 'Test content for inbox' );
		$activity->set_object( $object );

		$user_id = 1;

		// Add activity to inbox.
		$inbox_id = Inbox::add( $activity, $user_id );

		$this->assertIsInt( $inbox_id );
		$this->assertGreaterThan( 0, $inbox_id );

		// Verify the post was created.
		$post = \get_post( $inbox_id );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( Inbox::POST_TYPE, $post->post_type );
		$this->assertEquals( 'publish', $post->post_status );

		// Test _activitypub_object_id meta.
		$object_id_meta = \get_post_meta( $inbox_id, '_activitypub_object_id', true );
		$this->assertEquals( 'https://remote.example.com/objects/456', $object_id_meta );

		// Test _activitypub_activity_type meta.
		$activity_type_meta = \get_post_meta( $inbox_id, '_activitypub_activity_type', true );
		$this->assertEquals( 'Create', $activity_type_meta );

		// Test _activitypub_activity_actor meta.
		$activity_actor_meta = \get_post_meta( $inbox_id, '_activitypub_activity_actor', true );
		$expected_actor_type = \user_can( $user_id, 'activitypub' ) ? 'user' : 'blog';
		$this->assertEquals( $expected_actor_type, $activity_actor_meta );

		// Test _activitypub_activity_remote_actor meta.
		$remote_actor_meta = \get_post_meta( $inbox_id, '_activitypub_activity_remote_actor', true );
		$this->assertEquals( 'https://remote.example.com/users/testuser', $remote_actor_meta );

		// Test activitypub_content_visibility meta.
		$visibility_meta = \get_post_meta( $inbox_id, 'activitypub_content_visibility', true );
		$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, $visibility_meta );
	}

	/**
	 * Test adding a private activity to the inbox.
	 *
	 * @covers ::add
	 */
	public function test_add_private_activity() {
		// Create a private activity (no 'to' or 'cc' with public collection).
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/private123' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );
		$activity->set_to( array( 'https://example.com/users/1' ) );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/private456' );
		$object->set_type( 'Note' );
		$object->set_content( 'Private test content' );
		$activity->set_object( $object );

		$user_id = 1;

		// Add activity to inbox.
		$inbox_id = Inbox::add( $activity, $user_id );

		$this->assertIsInt( $inbox_id );

		// Test visibility is set to private.
		$visibility_meta = \get_post_meta( $inbox_id, 'activitypub_content_visibility', true );
		$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, $visibility_meta );
	}

	/**
	 * Test adding different activity types to verify meta validation.
	 *
	 * @covers ::add
	 * @dataProvider activity_type_provider
	 *
	 * @param string $activity_type The activity type to test.
	 */
	public function test_add_different_activity_types( $activity_type ) {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/' . strtolower( $activity_type ) );
		$activity->set_type( $activity_type );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/test' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, 1 );

		$this->assertIsInt( $inbox_id );

		// Verify activity type meta is set correctly.
		$activity_type_meta = \get_post_meta( $inbox_id, '_activitypub_activity_type', true );
		$this->assertEquals( $activity_type, $activity_type_meta );
	}

	/**
	 * Data provider for different activity types.
	 *
	 * @return array
	 */
	public function activity_type_provider() {
		return array(
			array( 'Create' ),
			array( 'Update' ),
			array( 'Delete' ),
			array( 'Follow' ),
			array( 'Accept' ),
			array( 'Reject' ),
			array( 'Undo' ),
			array( 'Like' ),
			array( 'Announce' ),
		);
	}

	/**
	 * Test adding activity with different user types.
	 *
	 * @covers ::add
	 */
	public function test_add_activity_with_different_user_types() {
		// Test with blog user (user ID 0).
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/blog-test' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/blog-test' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, 0 );

		$this->assertIsInt( $inbox_id );

		// Verify actor type meta for blog.
		$activity_actor_meta = \get_post_meta( $inbox_id, '_activitypub_activity_actor', true );
		$this->assertEquals( 'blog', $activity_actor_meta );
	}

	/**
	 * Test duplicate activity prevention.
	 *
	 * @covers ::add
	 */
	public function test_duplicate_activity_prevention() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/duplicate-test' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/duplicate-test' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		// Add activity first time.
		$inbox_id1 = Inbox::add( $activity, 1 );
		$this->assertIsInt( $inbox_id1 );

		// Try to add the same activity again.
		$inbox_id2 = Inbox::add( $activity, 1 );
		$this->assertEquals( $inbox_id1, $inbox_id2 );
	}

	/**
	 * Test post meta registration exists.
	 */
	public function test_post_meta_registration() {
		Post_Types::register_inbox_post_type();

		// Verify that post meta is registered for inbox post type.
		$registered_meta = \get_registered_meta_keys( 'post', Inbox::POST_TYPE );

		$this->assertArrayHasKey( '_activitypub_object_id', $registered_meta );
		$this->assertArrayHasKey( '_activitypub_activity_type', $registered_meta );
		$this->assertArrayHasKey( '_activitypub_activity_actor', $registered_meta );
		$this->assertArrayHasKey( '_activitypub_activity_remote_actor', $registered_meta );
		$this->assertArrayHasKey( 'activitypub_content_visibility', $registered_meta );

		// Verify meta field properties.
		$object_id_meta = $registered_meta['_activitypub_object_id'];
		$this->assertEquals( 'string', $object_id_meta['type'] );
		$this->assertTrue( $object_id_meta['single'] );

		$activity_type_meta = $registered_meta['_activitypub_activity_type'];
		$this->assertEquals( 'string', $activity_type_meta['type'] );
		$this->assertTrue( $activity_type_meta['single'] );
		$this->assertTrue( $activity_type_meta['show_in_rest'] );

		$visibility_meta = $registered_meta['activitypub_content_visibility'];
		$this->assertEquals( 'string', $visibility_meta['type'] );
		$this->assertTrue( $visibility_meta['single'] );
		$this->assertTrue( $visibility_meta['show_in_rest'] );
	}

	/**
	 * Test meta sanitization callbacks.
	 */
	public function test_meta_sanitization() {
		// Test activity type sanitization.
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/sanitize-test' );
		$activity->set_type( 'create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/sanitize-test' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, 1 );

		// Verify activity type is properly capitalized.
		$activity_type_meta = \get_post_meta( $inbox_id, '_activitypub_activity_type', true );
		$this->assertEquals( 'Create', $activity_type_meta );
	}
}
