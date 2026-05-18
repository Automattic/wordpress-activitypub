<?php
/**
 * Test Post scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Actors;
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
		$post_id        = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
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
	 * @covers ::triage
	 */
	public function test_triage_regular_post() {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$post = $this->get_latest_outbox_item( $activitypub_id );
		$id   = \get_post_meta( $post->ID, '_activitypub_object_id', true );
		$this->assertSame( $activitypub_id, $id );
	}

	/**
	 * Test that unfederated posts do not trigger Delete activity when trashed.
	 *
	 * @covers ::triage
	 */
	public function test_triage_skip_delete_for_unfederated_post() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );

		// Trash the post.
		\wp_delete_post( $post_id );

		$this->assertNull( $this->get_latest_outbox_item( $activitypub_id ) );
	}

	/**
	 * Test that publishing a post schedules a Create activity.
	 *
	 * @ticket https://github.com/Automattic/wordpress-activitypub/pull/1408
	 * @covers ::triage
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
	}

	/**
	 * Test post activity scheduling during bulk edits.
	 *
	 * @covers ::triage
	 */
	public function test_triage_bulk_edit() {
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
	}

	/**
	 * Test that sticking a post creates an Add activity for the featured collection.
	 *
	 * @covers ::schedule_featured_add
	 * @covers ::schedule_featured_update
	 */
	public function test_sticky_post_creates_add_activity() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$actor   = Actors::get_by_id( $user_id );

		$post_id        = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$activitypub_id = \Activitypub\get_post_id( $post_id );

		\stick_post( $post_id );

		// Query for the Add activity by object ID and activity type.
		$outbox_items = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Add',
					),
				),
			)
		);

		$this->assertCount( 1, $outbox_items );

		$last_item = $outbox_items[0];

		// Verify the activity content.
		$activity = \json_decode( $last_item->post_content, true );
		$this->assertEquals( 'Add', $activity['type'] );
		$this->assertEquals( $actor->get_id(), $activity['actor'] );
		$this->assertEquals( $activitypub_id, $activity['object'] );
		$this->assertEquals( $actor->get_featured(), $activity['target'] );
	}

	/**
	 * Test that unsticking a post creates a Remove activity for the featured collection.
	 *
	 * @covers ::schedule_featured_remove
	 * @covers ::schedule_featured_update
	 */
	public function test_unsticky_post_creates_remove_activity() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$actor   = Actors::get_by_id( $user_id );

		$post_id        = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$activitypub_id = \Activitypub\get_post_id( $post_id );

		// First stick, then unstick.
		\stick_post( $post_id );
		\unstick_post( $post_id );

		// Query for the Remove activity by object ID and activity type.
		$outbox_items = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Remove',
					),
				),
			)
		);

		$this->assertCount( 1, $outbox_items );

		$last_item = $outbox_items[0];

		// Verify the activity content.
		$activity = \json_decode( $last_item->post_content, true );
		$this->assertEquals( 'Remove', $activity['type'] );
		$this->assertEquals( $actor->get_id(), $activity['actor'] );
		$this->assertEquals( $activitypub_id, $activity['object'] );
		$this->assertEquals( $actor->get_featured(), $activity['target'] );
	}

	/**
	 * Test that changing visibility to local creates a Delete activity for federated posts.
	 *
	 * @covers ::triage
	 */
	public function test_visibility_change_to_local_creates_delete_activity() {
		// Create a post (will be federated).
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		// Verify the post was federated (Create activity exists).
		$create_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotNull( $create_item );
		$this->assertSame( 'Create', \get_post_meta( $create_item->ID, '_activitypub_activity_type', true ) );

		// Simulate the post being marked as federated (normally done by dispatcher).
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		// Change visibility to local and trigger triage via post update.
		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		\wp_update_post( array( 'ID' => $post_id ) );

		// Query for the Delete activity.
		$outbox_items = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Delete',
					),
				),
			)
		);

		$this->assertCount( 1, $outbox_items, 'Should create a Delete activity when visibility changes to local' );
	}

	/**
	 * Test that changing visibility to private creates a Delete activity for federated posts.
	 *
	 * @covers ::triage
	 */
	public function test_visibility_change_to_private_creates_delete_activity() {
		// Create a post (will be federated).
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		// Simulate the post being marked as federated.
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		// Change visibility to private and trigger triage via post update.
		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
		\wp_update_post( array( 'ID' => $post_id ) );

		// Query for the Delete activity.
		$outbox_items = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Delete',
					),
				),
			)
		);

		$this->assertCount( 1, $outbox_items, 'Should create a Delete activity when visibility changes to private' );
	}

	/**
	 * Test that moving a federated post to a non-public status emits Delete.
	 *
	 * @dataProvider data_non_public_status_transitions
	 *
	 * @covers ::triage
	 *
	 * @param string $new_status Target post status (draft, pending, or private).
	 */
	public function test_status_change_creates_delete_activity_for_federated_post( $new_status ) {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			)
		);

		$delete_items = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Delete',
					),
				),
			)
		);

		$this->assertCount( 1, $delete_items, "publish -> {$new_status} should emit Delete for a federated post." );
	}

	/**
	 * Data provider: non-public post statuses that should emit Delete on transition.
	 *
	 * @return array[]
	 */
	public function data_non_public_status_transitions() {
		return array(
			'draft'   => array( 'draft' ),
			'pending' => array( 'pending' ),
			'private' => array( 'private' ),
		);
	}

	/**
	 * Test that changing visibility does not create Delete activity for unfederated posts.
	 *
	 * @covers ::triage
	 */
	public function test_visibility_change_no_delete_for_unfederated_post() {
		// Create a post without federating it.
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );

		// Ensure the post has no federated status.
		\delete_post_meta( $post_id, 'activitypub_status' );

		// Change visibility to local and trigger triage via post update.
		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		\wp_update_post( array( 'ID' => $post_id ) );

		// Query for any Delete activity.
		$outbox_items = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Delete',
					),
				),
			)
		);

		$this->assertEmpty( $outbox_items, 'Should not create a Delete activity for unfederated posts' );
	}

	/**
	 * Test that changing visibility to public does not create Delete activity.
	 *
	 * @covers ::triage
	 */
	public function test_visibility_change_to_public_no_delete_activity() {
		// Create a post (will be federated).
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		// Simulate the post being marked as federated.
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		// Change visibility to public (empty string).
		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		// Query for any Delete activity.
		$outbox_items = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_activitypub_object_id',
						'value' => $activitypub_id,
					),
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Delete',
					),
				),
			)
		);

		$this->assertEmpty( $outbox_items, 'Should not create a Delete activity when visibility changes to public' );
	}

	/**
	 * Test that re-saving a soft-deleted post does not create a new outbox activity.
	 *
	 * When a post has already been soft-deleted (state=deleted, visibility=local/private),
	 * a subsequent wp_update_post (e.g. from a plugin re-saving) should not
	 * create a spurious Update activity that undoes the tombstone.
	 *
	 * @covers ::triage
	 */
	public function test_resave_soft_deleted_post_no_new_activity() {
		// Create a post (will be federated).
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		// Verify the post was federated (Create activity exists).
		$create_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotNull( $create_item );
		$this->assertSame( 'Create', \get_post_meta( $create_item->ID, '_activitypub_activity_type', true ) );

		// Simulate the post being soft-deleted: state=deleted, visibility=local.
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_DELETED );
		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );

		// Count existing outbox items before re-save.
		$before_count = count(
			\get_posts(
				array(
					'post_type'   => 'ap_outbox',
					'post_status' => 'pending',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_object_id',
							'value' => $activitypub_id,
						),
					),
					'numberposts' => -1,
				)
			)
		);

		$this->assertGreaterThan( 0, $before_count, 'Sanity check: post should have at least one outbox item before re-save.' );

		// Re-save the post (simulates a plugin re-saving during save_post).
		\wp_update_post( array( 'ID' => $post_id ) );

		// Count outbox items after re-save.
		$after_count = count(
			\get_posts(
				array(
					'post_type'   => 'ap_outbox',
					'post_status' => 'pending',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_object_id',
							'value' => $activitypub_id,
						),
					),
					'numberposts' => -1,
				)
			)
		);

		$this->assertSame( $before_count, $after_count, 'Re-saving a soft-deleted post should not create any new outbox activities.' );
	}
}
