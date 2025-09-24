<?php
/**
 * Test Post scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Scheduler\Post;

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
		$post_id        = self::factory()->attachment->create_upload_object( dirname( __DIR__, 2 ) . '/assets/test.jpg' );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		$outbox_item    = $this->get_latest_outbox_item( $activitypub_id );

		$this->assertNotNull( $outbox_item );
		$this->assertSame( 'Create', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		// Update.
		self::factory()->attachment->update_object( $post_id, array( 'post_title' => 'Updated title' ) );

		$outbox_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertSame( 'Update', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		// Delete.
		\wp_delete_attachment( $post_id, true );

		$outbox_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertSame( 'Delete', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		remove_post_type_support( 'attachment', 'activitypub' );
	}

	/**
	 * Test post activity scheduling for regular posts.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_regular_post() {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$post = $this->get_latest_outbox_item( $activitypub_id );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitypub_id, $id );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test post activity scheduling for regular posts.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_not_schedule_delete_activity_unfederated_post() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'schedule_post_activity' ), 33 );
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		\add_action( 'wp_after_insert_post', array( Post::class, 'schedule_post_activity' ), 33, 4 );

		// Trash the post.
		\wp_delete_post( $post_id );

		$this->assertNull( $this->get_latest_outbox_item( $activitypub_id ) );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test that publishing a post schedules a Create activity.
	 *
	 * @ticket https://github.com/Automattic/wordpress-activitypub/pull/1408
	 * @covers ::schedule_post_activity
	 */
	public function test_activity_type_on_publish() {
		$post_id        = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'draft',
			)
		);
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\wp_publish_post( $post_id );

		$post = $this->get_latest_outbox_item( $activitypub_id );
		$type = \get_post_meta( $post->ID, '_activitypub_activity_type', true );
		$this->assertSame( 'Create', $type );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test post activity scheduling during bulk edits.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_bulk_edit() {
		wp_set_current_user( self::$user_id );
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		// Test bulk edit with missing post_author (should not generate PHP warnings).
		$_REQUEST['bulk_edit'] = 1;
		$_REQUEST['_status']   = -1;
		$_REQUEST['post']      = array( $post_id );

		bulk_edit_posts( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification

		$outbox_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotSame( 'Update', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		// Test bulk edit that should bail (no author or status change).
		$_REQUEST['bulk_edit']   = 1;
		$_REQUEST['post_author'] = -1;
		$_REQUEST['_status']     = -1;
		$_REQUEST['post']        = array( $post_id );

		bulk_edit_posts( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification

		$outbox_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotSame( 'Update', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		// Test bulk edit with author change (should not bail).
		$new_user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		get_userdata( $new_user_id )->add_cap( 'activitypub' );
		wp_set_current_user( $new_user_id );

		$_REQUEST['post_author'] = $new_user_id;

		bulk_edit_posts( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification

		$outbox_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotNull( $outbox_item );

		$this->assertSame( 'Update', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		// Test bulk edit with status change (should not bail).
		$_REQUEST['_status'] = 'trash';

		bulk_edit_posts( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification

		$outbox_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotNull( $outbox_item );
		$this->assertSame( 'Delete', \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true ) );

		// Clean up.
		unset( $_REQUEST['bulk_edit'], $_REQUEST['post_author'], $_REQUEST['_status'], $_REQUEST['post'] );
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Data provider for no activity tests.
	 *
	 * @return array[][] Test parameters.
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
		$post_id        = self::factory()->post->create( $args );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$this->assertNull( $this->get_latest_outbox_item( $activitypub_id ) );

		\wp_delete_post( $post_id, true );
	}
}
