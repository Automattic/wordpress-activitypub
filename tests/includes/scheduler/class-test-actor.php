<?php
/**
 * Test Actor scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;

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
			array( 'activitypub_description' ),
			array( 'activitypub_header_image' ),
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
		$this->assertSame( $activitpub_id, $post->post_title );
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
		$this->assertSame( $activitpub_id, $post->post_title );
	}

	/**
	 * Test blog user update scheduling.
	 *
	 * @covers ::blog_user_update
	 */
	public function test_blog_user_update() {
		$test_value = 'test value';
		$result     = \Activitypub\Scheduler\Actor::blog_user_update( $test_value );

		$activitpub_id = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();
		$post          = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( $activitpub_id, $post->post_title );
		$this->assertSame( $test_value, $result );
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
		$this->assertSame( $activitpub_id, $post->post_title );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test post activity scheduling for ActivityPub extra fields.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_extra_field_blog() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		$blog_post_id  = self::factory()->post->create( array( 'post_type' => Extra_Fields::BLOG_POST_TYPE ) );
		$activitpub_id = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( $activitpub_id, $post->post_title );

		// Clean up.
		\wp_delete_post( $blog_post_id, true );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
	}
}
