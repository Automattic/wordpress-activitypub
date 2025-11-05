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

		// Test _activitypub_user_id meta.
		$user_id_meta = \get_post_meta( $inbox_id, '_activitypub_user_id', true );
		$this->assertEquals( $user_id, $user_id_meta );

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

		// Verify user_id meta for blog user.
		$user_id_meta = \get_post_meta( $inbox_id, '_activitypub_user_id', true );
		$this->assertEquals( 0, $user_id_meta );
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

	/**
	 * Test adding the same activity for multiple users.
	 *
	 * @covers ::add
	 */
	public function test_add_activity_for_multiple_users() {
		// Create a test activity that will be received by multiple users.
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/multi-user-test' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/multi-user-test' );
		$object->set_type( 'Note' );
		$object->set_content( 'Test content for multiple users' );
		$activity->set_object( $object );

		// Add activity for first user.
		$inbox_id_1 = Inbox::add( $activity, 1 );
		$this->assertIsInt( $inbox_id_1 );
		$this->assertGreaterThan( 0, $inbox_id_1 );

		// Verify first user is in metadata.
		$user_ids = \get_post_meta( $inbox_id_1, '_activitypub_user_id', false );
		$this->assertIsArray( $user_ids );
		$this->assertContains( '1', $user_ids );
		$this->assertCount( 1, $user_ids );

		// Add the same activity for second user.
		$inbox_id_2 = Inbox::add( $activity, 2 );
		$this->assertEquals( $inbox_id_1, $inbox_id_2, 'Should return the same inbox item ID' );

		// Verify both users are now in metadata.
		$user_ids = \get_post_meta( $inbox_id_1, '_activitypub_user_id', false );
		$this->assertIsArray( $user_ids );
		$this->assertCount( 2, $user_ids );
		$this->assertContains( '1', $user_ids );
		$this->assertContains( '2', $user_ids );

		// Add the same activity for third user.
		$inbox_id_3 = Inbox::add( $activity, 3 );
		$this->assertEquals( $inbox_id_1, $inbox_id_3, 'Should return the same inbox item ID' );

		// Verify all three users are in metadata.
		$user_ids = \get_post_meta( $inbox_id_1, '_activitypub_user_id', false );
		$this->assertIsArray( $user_ids );
		$this->assertCount( 3, $user_ids );
		$this->assertContains( '1', $user_ids );
		$this->assertContains( '2', $user_ids );
		$this->assertContains( '3', $user_ids );

		// Try adding for user 1 again (should not duplicate).
		$inbox_id_4 = Inbox::add( $activity, 1 );
		$this->assertEquals( $inbox_id_1, $inbox_id_4, 'Should return the same inbox item ID' );

		// Verify still only three unique users.
		$user_ids = \get_post_meta( $inbox_id_1, '_activitypub_user_id', false );
		$this->assertCount( 3, $user_ids, 'Should not duplicate user_id' );
	}

	/**
	 * Test adding activity with array of recipients.
	 *
	 * @covers ::add
	 */
	public function test_add_activity_with_array_of_recipients() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/array-recipients' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/array-recipients' );
		$object->set_type( 'Note' );
		$object->set_content( 'Test content for array of recipients' );
		$activity->set_object( $object );

		// Add activity with multiple recipients at once.
		$inbox_id = Inbox::add( $activity, array( 1, 2, 3, 0 ) );
		$this->assertIsInt( $inbox_id );

		// Verify all recipients are stored.
		$recipients = Inbox::get_recipients( $inbox_id );
		$this->assertIsArray( $recipients );
		$this->assertCount( 4, $recipients );
		$this->assertContains( 0, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
	}

	/**
	 * Test get_recipients function.
	 *
	 * @covers ::get_recipients
	 */
	public function test_get_recipients() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/get-recipients' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/get-recipients' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		// Add with multiple recipients.
		$inbox_id = Inbox::add( $activity, array( 1, 2, 0 ) );

		// Test get_recipients.
		$recipients = Inbox::get_recipients( $inbox_id );
		$this->assertIsArray( $recipients );
		$this->assertCount( 3, $recipients );
		$this->assertContains( 0, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
	}

	/**
	 * Test has_recipient function.
	 *
	 * @covers ::has_recipient
	 */
	public function test_has_recipient() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/has-recipient' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/has-recipient' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, array( 1, 2 ) );

		// Test has_recipient for existing recipients.
		$this->assertTrue( Inbox::has_recipient( $inbox_id, 1 ) );
		$this->assertTrue( Inbox::has_recipient( $inbox_id, 2 ) );

		// Test has_recipient for non-existing recipient.
		$this->assertFalse( Inbox::has_recipient( $inbox_id, 3 ) );
		$this->assertFalse( Inbox::has_recipient( $inbox_id, 0 ) );
	}

	/**
	 * Test add_recipient function.
	 *
	 * @covers ::add_recipient
	 */
	public function test_add_recipient() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/add-recipient' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/add-recipient' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, 1 );

		// Add new recipient.
		$result = Inbox::add_recipient( $inbox_id, 2 );
		$this->assertTrue( $result );
		$this->assertTrue( Inbox::has_recipient( $inbox_id, 2 ) );

		// Add blog user (ID 0).
		$result = Inbox::add_recipient( $inbox_id, 0 );
		$this->assertTrue( $result );
		$this->assertTrue( Inbox::has_recipient( $inbox_id, 0 ) );

		// Try adding duplicate recipient.
		$result = Inbox::add_recipient( $inbox_id, 1 );
		$this->assertTrue( $result, 'Should return true for duplicate (no-op)' );

		// Verify total count.
		$recipients = Inbox::get_recipients( $inbox_id );
		$this->assertCount( 3, $recipients );

		// Test invalid user ID (negative).
		$result = Inbox::add_recipient( $inbox_id, -1 );
		$this->assertFalse( $result, 'Should reject negative user ID' );
	}

	/**
	 * Test remove_recipient function.
	 *
	 * @covers ::remove_recipient
	 */
	public function test_remove_recipient() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/remove-recipient' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/remove-recipient' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, array( 0, 1, 2, 3 ) );

		// Remove a recipient.
		$result = Inbox::remove_recipient( $inbox_id, 2 );
		$this->assertTrue( $result );
		$this->assertFalse( Inbox::has_recipient( $inbox_id, 2 ) );

		// Remove blog user (ID 0).
		$result = Inbox::remove_recipient( $inbox_id, 0 );
		$this->assertTrue( $result );
		$this->assertFalse( Inbox::has_recipient( $inbox_id, 0 ) );

		// Verify remaining recipients.
		$recipients = Inbox::get_recipients( $inbox_id );
		$this->assertCount( 2, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 3, $recipients );

		// Test removing non-existent recipient.
		$result = Inbox::remove_recipient( $inbox_id, 99 );
		$this->assertFalse( $result, 'Should return false when removing non-existent recipient' );

		// Test invalid user ID (negative).
		$result = Inbox::remove_recipient( $inbox_id, -1 );
		$this->assertFalse( $result, 'Should reject negative user ID' );
	}

	/**
	 * Test get_by_guid_and_recipient function.
	 *
	 * @covers ::get_by_guid_and_recipient
	 */
	public function test_get_by_guid_and_recipient() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/guid-recipient' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/guid-recipient' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, array( 1, 2 ) );

		// Test with valid recipient.
		$post = Inbox::get_by_guid_and_recipient( 'https://remote.example.com/activities/guid-recipient', 1 );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( $inbox_id, $post->ID );

		// Test with another valid recipient.
		$post = Inbox::get_by_guid_and_recipient( 'https://remote.example.com/activities/guid-recipient', 2 );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( $inbox_id, $post->ID );

		// Test with non-recipient.
		$result = Inbox::get_by_guid_and_recipient( 'https://remote.example.com/activities/guid-recipient', 3 );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'activitypub_inbox_not_recipient', $result->get_error_code() );

		// Test with non-existent GUID.
		$result = Inbox::get_by_guid_and_recipient( 'https://remote.example.com/activities/non-existent', 1 );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'activitypub_inbox_item_not_found', $result->get_error_code() );
	}

	/**
	 * Test adding activity with empty recipients array.
	 *
	 * @covers ::add
	 */
	public function test_add_activity_with_empty_recipients() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/empty-recipients' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/empty-recipients' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$result = Inbox::add( $activity, array() );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'activitypub_inbox_no_recipients', $result->get_error_code() );
	}

	/**
	 * Test adding activity with duplicate recipients in array.
	 *
	 * @covers ::add
	 */
	public function test_add_activity_with_duplicate_recipients() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/dup-array' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/dup-array' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		// Add with duplicate recipients in array.
		$inbox_id = Inbox::add( $activity, array( 1, 2, 1, 3, 2 ) );
		$this->assertIsInt( $inbox_id );

		// Verify recipients are deduplicated.
		$recipients = Inbox::get_recipients( $inbox_id );
		$this->assertCount( 3, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
	}

	/**
	 * Test add_recipients function.
	 *
	 * @covers ::add_recipients
	 */
	public function test_add_recipients() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/add-recipients' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/add-recipients' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, 1 );

		// Add multiple recipients at once.
		Inbox::add_recipients( $inbox_id, array( 2, 3, 4 ) );

		// Verify all recipients were added.
		$recipients = Inbox::get_recipients( $inbox_id );
		$this->assertCount( 4, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
		$this->assertContains( 4, $recipients );
	}

	/**
	 * Test deduplicate function with no duplicates.
	 *
	 * @covers ::deduplicate
	 */
	public function test_deduplicate_no_duplicates() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/single-item' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/single-item' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		$inbox_id = Inbox::add( $activity, 1 );

		// Deduplicate should return the same post.
		$result = Inbox::deduplicate( 'https://remote.example.com/activities/single-item' );
		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertEquals( $inbox_id, $result->ID );
	}

	/**
	 * Test deduplicate function with duplicates.
	 *
	 * @covers ::deduplicate
	 */
	public function test_deduplicate_with_duplicates() {
		$activity = new Activity();
		$activity->set_id( 'https://remote.example.com/activities/duplicate-guid' );
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://remote.example.com/users/testuser' );

		$object = new Base_Object();
		$object->set_id( 'https://remote.example.com/objects/duplicate-guid' );
		$object->set_type( 'Note' );
		$activity->set_object( $object );

		// Manually create duplicate inbox posts with same GUID.
		$inbox_id_1 = \wp_insert_post(
			array(
				'post_type'    => Inbox::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => \wp_json_encode( $activity->to_array() ),
				'guid'         => 'https://remote.example.com/activities/duplicate-guid',
			)
		);
		\add_post_meta( $inbox_id_1, '_activitypub_user_id', 1 );
		\add_post_meta( $inbox_id_1, '_activitypub_user_id', 2 );

		$inbox_id_2 = \wp_insert_post(
			array(
				'post_type'    => Inbox::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => \wp_json_encode( $activity->to_array() ),
				'guid'         => 'https://remote.example.com/activities/duplicate-guid',
			)
		);
		\add_post_meta( $inbox_id_2, '_activitypub_user_id', 3 );
		\add_post_meta( $inbox_id_2, '_activitypub_user_id', 4 );

		$inbox_id_3 = \wp_insert_post(
			array(
				'post_type'    => Inbox::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => \wp_json_encode( $activity->to_array() ),
				'guid'         => 'https://remote.example.com/activities/duplicate-guid',
			)
		);
		\add_post_meta( $inbox_id_3, '_activitypub_user_id', 5 );

		// Run deduplication.
		$result = Inbox::deduplicate( 'https://remote.example.com/activities/duplicate-guid' );

		// Should return the first post.
		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertEquals( $inbox_id_1, $result->ID );

		// Verify all recipients were merged.
		$recipients = Inbox::get_recipients( $inbox_id_1 );
		$this->assertCount( 5, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );
		$this->assertContains( 3, $recipients );
		$this->assertContains( 4, $recipients );
		$this->assertContains( 5, $recipients );

		// Verify duplicates were deleted.
		$this->assertNull( \get_post( $inbox_id_2 ) );
		$this->assertNull( \get_post( $inbox_id_3 ) );

		// Verify only one post exists with this GUID.
		$posts = \get_posts(
			array(
				'post_type'      => Inbox::POST_TYPE,
				'guid'           => 'https://remote.example.com/activities/duplicate-guid',
				'posts_per_page' => -1,
			)
		);
		$this->assertCount( 1, $posts );
	}

	/**
	 * Test deduplicate function with non-existent GUID.
	 *
	 * @covers ::deduplicate
	 */
	public function test_deduplicate_non_existent() {
		$result = Inbox::deduplicate( 'https://remote.example.com/activities/non-existent' );
		$this->assertFalse( $result );
	}
}
