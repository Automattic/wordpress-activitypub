<?php
/**
 * Test Actor scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Scheduler\Actor;

/**
 * Test Post scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Actor
 */
class Test_Actor extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {

	/**
	 * Set up test resources.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::factory()->user->update_object(
			self::$user_id,
			array(
				'display_name' => 'Test User',
				'meta_input'   => array(
					'activitypub_description'  => 'test description',
					'activitypub_header_image' => 'test header image',
					'description'              => 'test description',
					'user_url'                 => 'https://example.org',
					'display_name'             => 'Test Name',
				),
			)
		);
	}

	/**
	 * Data provider for user meta update scheduling.
	 *
	 * @return string[][]
	 */
	public function user_meta_provider() {
		return array(
			array( 'description' ),
			array( 'user_url' ),
			array( 'display_name' ),
		);
	}

	/**
	 * Test user meta update scheduling.
	 *
	 * @dataProvider user_meta_provider
	 * @covers ::user_meta_update
	 *
	 * @param string $meta_key Meta key to test.
	 */
	public function test_user_meta_update( $meta_key ) {
		\update_user_meta( self::$user_id, $meta_key, 'test value' );

		$activitpub_id = Actors::get_by_id( self::$user_id )->get_id();
		$post          = $this->get_latest_outbox_item( $activitpub_id );
		$id            = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitpub_id, $id );
	}

	/**
	 * Test user option update scheduling.
	 *
	 * @covers ::user_meta_update
	 */
	public function test_user_option_update() {
		$actor = Actors::get_by_id( self::$user_id );
		$post  = $this->get_latest_outbox_item( $actor->get_id() );
		if ( $post ) {
			\wp_delete_post( $post->ID, true );
		}

		$attachment_id = self::factory()->attachment->create_upload_object( dirname( __DIR__, 2 ) . '/assets/test.jpg' );

		// Update activitypub_description.
		$actor->update_summary( 'test summary' );

		$post = $this->get_latest_outbox_item( $actor->get_id() );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $actor->get_id(), $id );

		\wp_delete_post( $post->ID, true );

		// Update activitypub_icon.
		$actor->update_icon( $attachment_id );

		$post = $this->get_latest_outbox_item( $actor->get_id() );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $actor->get_id(), $id );

		\wp_delete_post( $post->ID, true );

		// Update activitypub_header_image.
		$actor->update_header( $attachment_id );

		$post = $this->get_latest_outbox_item( $actor->get_id() );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $actor->get_id(), $id );

		\wp_delete_post( $post->ID, true );
		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test user update scheduling.
	 *
	 * @covers ::user_update
	 */
	public function test_user_update() {
		self::factory()->user->update_object( self::$user_id, array( 'display_name' => 'Test Name' ) );

		$activitpub_id = Actors::get_by_id( self::$user_id )->get_id();
		$post          = $this->get_latest_outbox_item( $activitpub_id );
		$id            = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitpub_id, $id );
	}

	/**
	 * Test blog user update scheduling.
	 *
	 * @covers ::blog_user_update
	 */
	public function test_blog_user_update() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		$test_value = 'test value';
		$result     = \Activitypub\Scheduler\Actor::blog_user_update( $test_value );

		$activitpub_id = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();
		$post          = $this->get_latest_outbox_item( $activitpub_id );
		$id            = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitpub_id, $id );
		$this->assertSame( $test_value, $result );
	}

	/**
	 * Data provider for blog user image updates.
	 *
	 * @return string[][]
	 */
	public function blog_user_images_provider() {
		return array(
			array( 'image', 'activitypub_header_image' ),
			array( 'icon', 'site_icon' ),
		);
	}

	/**
	 * Test blog user image updates.
	 *
	 * @dataProvider blog_user_images_provider
	 * @covers ::blog_user_update
	 *
	 * @param string $field  Field to test.
	 * @param string $option Option to test.
	 */
	public function test_blog_user_image_updates( $field, $option ) {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		Actor::init();

		$attachment_id = self::factory()->attachment->create_upload_object( dirname( __DIR__, 2 ) . '/assets/test.jpg' );
		\update_option( $option, $attachment_id );

		$expected = array(
			'type' => 'Image',
			'url'  => \wp_get_attachment_url( $attachment_id ),
		);

		$activitpub_id = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();
		$post          = $this->get_latest_outbox_item( $activitpub_id );

		$activity = \json_decode( $post->post_content, true );
		$this->assertArrayHasKey( 'object', $activity );
		$this->assertArrayHasKey( $field, $activity['object'] );
		$this->assertSame( $expected, $activity['object'][ $field ] );
	}

	/**
	 * Data provider for blog user text updates.
	 *
	 * @return string[][]
	 */
	public function blog_user_text_provider() {
		return array(
			array( 'preferredUsername', 'activitypub_blog_identifier', 'blog' ),
			array( 'summary', 'activitypub_blog_description', 'blog description' ),
			array( 'name', 'blogname', 'test site' ),
		);
	}

	/**
	 * Test blog user image updates.
	 *
	 * @dataProvider blog_user_text_provider
	 * @covers ::blog_user_update
	 *
	 * @param string $field  Field to test.
	 * @param string $option Option to test.
	 * @param string $value  Value to test.
	 */
	public function test_blog_user_text_updates( $field, $option, $value ) {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		Actor::init();

		\update_option( $option, $value );

		$activitpub_id = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();
		$post          = $this->get_latest_outbox_item( $activitpub_id );

		$activity = \json_decode( $post->post_content, true );
		$this->assertArrayHasKey( 'object', $activity );
		$this->assertArrayHasKey( $field, $activity['object'] );
		$this->assertStringContainsString( $value, $activity['object'][ $field ] );
	}

	/**
	 * Test user update scheduling with non-publishing user.
	 *
	 * @covers ::user_update
	 */
	public function test_user_update_no_publish() {
		$activitpub_id = Actors::get_by_id( self::$user_id )->get_id();

		// Temporarily remove the activitypub capability.
		\get_user_by( 'id', self::$user_id )->remove_cap( 'activitypub' );
		self::factory()->user->update_object( self::$user_id, array( 'display_name' => 'Test Name No Publish' ) );

		$this->assertNull( $this->get_latest_outbox_item( $activitpub_id ) );

		// Restore the activitypub capability.
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Test user meta update scheduling with non-publishing user.
	 *
	 * @covers ::user_meta_update
	 */
	public function test_user_meta_update_no_publish() {
		$activitpub_id = Actors::get_by_id( self::$user_id )->get_id();

		// Temporarily remove the activitypub capability.
		\get_user_by( 'id', self::$user_id )->remove_cap( 'activitypub' );

		\update_user_meta( self::$user_id, 'description', 'test value' );

		$this->assertNull( $this->get_latest_outbox_item( $activitpub_id ) );

		// Restore the activitypub capability.
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Test post activity scheduling for ActivityPub extra fields.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_extra_fields() {
		$post_id       = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_type'   => Extra_Fields::USER_POST_TYPE,
			)
		);
		$activitpub_id = Actors::get_by_id( self::$user_id )->get_id();

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitpub_id, $id );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test post activity scheduling for ActivityPub extra fields.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_extra_field_blog() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		$blog_post_id  = self::factory()->post->create( array( 'post_type' => Extra_Fields::BLOG_POST_TYPE ) );
		$activitpub_id = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitpub_id, $id );

		// Clean up.
		\wp_delete_post( $blog_post_id, true );
	}

	/**
	 * Test that actor profile updates set the updated attribute.
	 *
	 * @covers ::schedule_profile_update
	 */
	public function test_actor_profile_update_sets_updated_attribute() {
		// Update the user's display name to trigger a profile update.
		self::factory()->user->update_object( self::$user_id, array( 'display_name' => 'Updated Display Name' ) );

		$activitpub_id = Actors::get_by_id( self::$user_id )->get_id();
		$post          = $this->get_latest_outbox_item( $activitpub_id );

		// Verify the activity type is Update.
		$this->assertEquals( 'Update', \get_post_meta( $post->ID, '_activitypub_activity_type', true ) );

		// Get the activity from the outbox.
		$activity = \json_decode( $post->post_content, true );

		// Verify the updated attribute is set and matches the post's modified date.
		$this->assertEqualsWithDelta( strtotime( $post->post_modified ), strtotime( $activity['updated'] ), 2, 'Updated attribute does not match post modified date.' );
	}

	/**
	 * Test that sticky posts are detected.
	 *
	 * @covers ::sticky_post_update
	 */
	public function test_sticky_post_update() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$last_item = $this->get_latest_outbox_item();

		$this->assertNull( $last_item );

		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );
		\stick_post( $post_id );

		$last_item_stick = $this->get_latest_outbox_item();

		$this->assertNotNull( $last_item_stick );

		\unstick_post( $post_id );

		$last_item_unstick = $this->get_latest_outbox_item();

		$this->assertNotEquals( $last_item_stick->ID, $last_item_unstick->ID );
		$this->assertEquals( $last_item_stick->post_author, $last_item_unstick->post_author );

		\wp_delete_post( $post_id );
		\wp_delete_user( $user_id );
	}

	/**
	 * Test that user deletion creates a Delete activity.
	 *
	 * @covers ::schedule_user_delete
	 */
	public function test_schedule_user_delete() {
		// Create a user with ActivityPub capability.
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Verify the user has ActivityPub capability.
		$this->assertTrue( \user_can( $user_id, 'activitypub' ) );

		// Get the actor before deletion.
		$actor = Actors::get_by_id( $user_id );
		$this->assertNotNull( $actor );
		$this->assertFalse( \is_wp_error( $actor ) );

		// Get the current outbox count.
		$outbox_before = $this->get_latest_outbox_item();
		$this->assertNull( $outbox_before );

		// Call the method directly to test it.
		Actor::schedule_user_delete( $user_id );

		// Check that a Delete activity was added to the outbox.
		$outbox_after = $this->get_latest_outbox_item();
		$this->assertNotNull( $outbox_after );

		// Verify it's a Delete activity.
		$activity_type = \get_post_meta( $outbox_after->ID, '_activitypub_activity_type', true );
		$this->assertEquals( 'Delete', $activity_type, 'Activity type should be Delete' );

		// Verify the activity content.
		$activity = \json_decode( $outbox_after->post_content, true );
		$this->assertIsArray( $activity, 'Activity content should be valid JSON' );
		$this->assertEquals( 'Delete', $activity['type'], 'Activity type in content should be Delete' );
		$this->assertEquals( $actor->get_id(), $activity['actor'], 'Actor should match' );
		$this->assertEquals( $actor->get_id(), $activity['object'], 'Object should be the actor being deleted' );

		// Clean up.
		\wp_delete_user( $user_id );
	}

	/**
	 * Test that user deletion is skipped for users without ActivityPub capability.
	 *
	 * @covers ::schedule_user_delete
	 */
	public function test_schedule_user_delete_skips_non_activitypub_users() {
		// Create a user without ActivityPub capability (subscriber role).
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Verify the user doesn't have ActivityPub capability.
		$this->assertFalse( \user_can( $user_id, 'activitypub' ) );

		// Get the current total outbox items across all users.
		$total_before = \wp_count_posts( 'ap_outbox' )->publish;

		// Call the method directly to test it.
		Actor::schedule_user_delete( $user_id );

		// Check that no Delete activity was added.
		$total_after = \wp_count_posts( 'ap_outbox' )->publish;
		$this->assertEquals( $total_before, $total_after, 'No Delete activity should be added for non-ActivityPub users' );

		// Clean up.
		\wp_delete_user( $user_id );
	}

	/**
	 * Test that user deletion handles invalid actor gracefully.
	 *
	 * @covers ::schedule_user_delete
	 */
	public function test_schedule_user_delete_handles_invalid_actor() {
		// Create a user with ActivityPub capability.
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Verify the user has ActivityPub capability.
		$this->assertTrue( \user_can( $user_id, 'activitypub' ) );

		// We'll use a filter to mock the response instead since we can't easily mock static methods.
		// For this test, we'll delete the user first to make the actor invalid.
		\wp_delete_user( $user_id );

		// Get the current total outbox items.
		$total_before = \wp_count_posts( 'ap_outbox' )->publish;

		// Call the method with the deleted user ID.
		Actor::schedule_user_delete( $user_id );

		// Check that no Delete activity was added since the actor is invalid.
		$total_after = \wp_count_posts( 'ap_outbox' )->publish;
		$this->assertEquals( $total_before, $total_after, 'No Delete activity should be added for invalid actors' );
	}
}
