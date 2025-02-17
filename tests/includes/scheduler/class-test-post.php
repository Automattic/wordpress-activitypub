<?php
/**
 * Test Post scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

/**
 * Test Post scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Post
 */
class Test_Post extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {

	/**
	 * Test post activity scheduling for attachments.
	 *
	 * @covers ::transition_attachment_status
	 */
	public function test_transition_attachment_status() {
		add_post_type_support( 'attachment', 'activitypub' );
		wp_set_current_user( self::$user_id );

		// Create.
		$post_id       = self::factory()->attachment->create_upload_object( dirname( __DIR__, 2 ) . '/assets/test.jpg' );
		$activitpub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		$outbox_item   = $this->get_latest_outbox_item( $activitpub_id );

		$this->assertNotNull( $outbox_item );
		$this->assertSame( 'Create', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		// Update.
		self::factory()->attachment->update_object( $post_id, array( 'post_title' => 'Updated title' ) );

		$outbox_item = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( 'Update', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		// Delete.
		\wp_delete_attachment( $post_id, true );

		$outbox_item = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( 'Delete', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		remove_post_type_support( 'attachment', 'activitypub' );
	}

	/**
	 * Test post activity scheduling for regular posts.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_regular_post() {
		$post_id       = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitpub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitpub_id, $id );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Data provider for no activity tests.
	 *
	 * @return array[] Test parameters.
	 */
	public function no_activity_post_provider() {
		return array(
			'password_protected'    => array(
				array( 'post_password' => 'test-password' ),
			),
			'unsupported_post_type' => array(
				array( 'post_type' => 'nav_menu_item' ),
			),
			'disabled_post'         => array(
				array(
					'meta_input' => array(
						'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
					),
				),
			),
		);
	}

	/**
	 * Test post activity scheduling under various conditions.
	 *
	 * @dataProvider no_activity_post_provider
	 *
	 * @param array $args Post data for creating the test post.
	 */
	public function test_no_activity_scheduled( $args ) {
		$post_id       = self::factory()->post->create( $args );
		$activitpub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$this->assertNull( $this->get_latest_outbox_item( $activitpub_id ) );

		\wp_delete_post( $post_id, true );
	}
}
