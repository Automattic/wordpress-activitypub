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
		$this->assertEmpty( $delete_items, 'Public inherit-status attachments must not be soft-deleted by triage().' );

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
	 * A federated post reverting to `future` (e.g. a content edit on a
	 * future-dated published post) must not fan out a soft-delete. The Delete
	 * would remotely tombstone the object id, after which the republish Create
	 * is ignored and the post can never re-federate.
	 *
	 * @covers ::triage
	 */
	public function test_future_transition_does_not_soft_delete_federated_post() {
		\wp_set_current_user( self::$user_id );

		// Publish and federate the post.
		$post_id        = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		$create_item    = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotNull( $create_item, 'Publishing should queue a Create.' );
		$this->assertSame( 'Create', \get_post_meta( $create_item->ID, '_activitypub_activity_type', true ) );

		// Re-schedule it to the future (the state WordPress reverts to when a
		// future-dated published post is edited without resetting its date).
		\wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'future',
				'post_date'     => \gmdate( 'Y-m-d H:i:s', \time() + DAY_IN_SECONDS ),
				'post_date_gmt' => \gmdate( 'Y-m-d H:i:s', \time() + DAY_IN_SECONDS ),
			)
		);

		$deletes = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'any',
				'numberposts' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
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
		$this->assertEmpty( $deletes, 'A federated post reverting to `future` must not queue a tombstoning Delete.' );
	}

	/**
	 * A federated post that is re-scheduled to `future` and later re-published
	 * must send an Update (its content may have changed while scheduled and the
	 * remote copy still exists), not a Create that the "already federated" guard
	 * silently drops, which would leave followers with stale content.
	 *
	 * @covers ::triage
	 */
	public function test_future_republish_sends_update_for_federated_post() {
		\wp_set_current_user( self::$user_id );

		// Publish and federate the post.
		$post_id        = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		$create_item    = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotNull( $create_item, 'Publishing should queue a Create.' );
		$this->assertSame( 'Create', \get_post_meta( $create_item->ID, '_activitypub_activity_type', true ) );

		// Re-schedule it to the future, then publish it again.
		\wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'future',
				'post_date'     => \gmdate( 'Y-m-d H:i:s', \time() + DAY_IN_SECONDS ),
				'post_date_gmt' => \gmdate( 'Y-m-d H:i:s', \time() + DAY_IN_SECONDS ),
			)
		);
		\wp_publish_post( $post_id );

		$latest = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotNull( $latest, 'Re-publishing a federated post must queue an activity for its followers.' );
		$this->assertSame(
			'Update',
			\get_post_meta( $latest->ID, '_activitypub_activity_type', true ),
			'A re-scheduled federated post returning to publish should send an Update.'
		);
	}

	/**
	 * Re-scheduling a post to `future` while a Create/Update is still pending must
	 * cancel that queued activity, so it is not dispatched after the post is no
	 * longer public (which would federate it before its publish date).
	 *
	 * @covers ::triage
	 */
	public function test_reschedule_to_future_cancels_pending_activity() {
		\wp_set_current_user( self::$user_id );

		// Publish the post; its Create is queued but not yet dispatched.
		$post_id        = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		$this->assertNotNull( $this->get_latest_outbox_item( $activitypub_id ), 'Publishing should queue a pending Create.' );

		// Re-schedule it to the future before the Create dispatches.
		\wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'future',
				'post_date'     => \gmdate( 'Y-m-d H:i:s', \time() + DAY_IN_SECONDS ),
				'post_date_gmt' => \gmdate( 'Y-m-d H:i:s', \time() + DAY_IN_SECONDS ),
			)
		);

		$this->assertNull(
			$this->get_latest_outbox_item( $activitypub_id ),
			'A pending activity must be cancelled when the post is re-scheduled to the future.'
		);
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
	 * Test that applying a password to a federated post emits a Delete.
	 *
	 * The post stays in `publish` status, so the switch arm produces an Update.
	 * The downgrade check at the bottom of triage() must catch this via
	 * `is_post_publicly_queryable()` and rewrite it to Delete — otherwise the
	 * Update broadcasts a (now-redacted) snapshot while remote followers keep
	 * the previously-federated content.
	 *
	 * @covers ::triage
	 */
	public function test_password_added_to_federated_post_creates_delete_activity() {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		// Verify the post was federated.
		$create_item = $this->get_latest_outbox_item( $activitypub_id );
		$this->assertNotNull( $create_item );
		$this->assertSame( 'Create', \get_post_meta( $create_item->ID, '_activitypub_activity_type', true ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		\wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'fed-secret-pass',
				'post_content'  => 'FEDERATION-SECRET-AFTER-PASSWORD',
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

		$this->assertCount( 1, $delete_items, 'Applying a password to a federated post must emit a Delete, not an Update.' );

		$update_items = \get_posts(
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
						'value' => 'Update',
					),
				),
			)
		);

		$this->assertCount( 0, $update_items, 'Must not also emit an Update activity for the password transition.' );
	}

	/**
	 * Test that moving a federated post to a CUSTOM non-public status emits a Delete.
	 *
	 * The switch's `default` arm catches custom statuses registered with
	 * `register_post_status()`, so a plugin-defined non-public status follows
	 * the same soft-delete pattern as draft/pending/private/trash.
	 *
	 * @covers ::triage
	 */
	public function test_custom_non_public_status_creates_delete_activity_for_federated_post() {
		\register_post_status(
			'archived_test',
			array(
				'label'  => 'Archived',
				'public' => false,
			)
		);

		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'archived_test',
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

		$this->assertCount( 1, $delete_items, 'Custom non-public status must hit the default switch arm and emit Delete.' );
	}

	/**
	 * Helper: count pending outbox items of a given activity type for an object.
	 *
	 * @param string $activitypub_id The ActivityPub object ID (URL).
	 * @param string $activity_type  The activity type ('Create', 'Update', 'Delete', etc.).
	 *
	 * @return int
	 */
	private function count_pending_outbox_items( $activitypub_id, $activity_type ) {
		return count(
			\get_posts(
				array(
					'post_type'   => 'ap_outbox',
					'post_status' => 'pending',
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_object_id',
							'value' => $activitypub_id,
						),
						array(
							'key'   => '_activitypub_activity_type',
							'value' => $activity_type,
						),
					),
				)
			)
		);
	}

	/**
	 * Test that re-publishing a soft-deleted post before its Delete fires
	 * invalidates the pending Delete and queues a Create.
	 *
	 * @dataProvider data_non_public_status_transitions
	 *
	 * @covers ::triage
	 *
	 * @param string $hide_status Non-public status to transition through.
	 */
	public function test_unpublish_then_republish_cancels_pending_delete( $hide_status ) {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		// Step 1: hide → Delete queued.
		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $hide_status,
			)
		);

		$this->assertSame( 1, $this->count_pending_outbox_items( $activitypub_id, 'Delete' ), "publish -> {$hide_status} must queue a Delete." );

		// Step 2: re-publish before the Delete fires.
		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 0, $this->count_pending_outbox_items( $activitypub_id, 'Delete' ), 'Pending Delete must be invalidated by the re-publish.' );
		$this->assertGreaterThanOrEqual( 1, $this->count_pending_outbox_items( $activitypub_id, 'Create' ), 'Re-publishing must queue a Create.' );
	}

	/**
	 * Test the password lock/unlock cycle on a federated post.
	 *
	 * Apply password → Delete queued. Remove password → Delete invalidated,
	 * Create queued.
	 *
	 * @covers ::triage
	 */
	public function test_password_lock_then_unlock_cycles_correctly() {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		// Lock.
		\wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'fed-secret-pass',
			)
		);

		$this->assertSame( 1, $this->count_pending_outbox_items( $activitypub_id, 'Delete' ), 'Applying a password must queue a Delete.' );

		// Unlock.
		\wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => '',
			)
		);

		$this->assertSame( 0, $this->count_pending_outbox_items( $activitypub_id, 'Delete' ), 'Pending Delete must be invalidated when the password is removed.' );
		$this->assertGreaterThanOrEqual( 1, $this->count_pending_outbox_items( $activitypub_id, 'Create' ), 'Removing the password must queue a Create.' );
	}

	/**
	 * Test that re-publishing AFTER the Delete has already been sent emits a
	 * fresh Create and does not retroactively cancel the sent Delete.
	 *
	 * @covers ::triage
	 */
	public function test_republish_after_delete_sent_emits_fresh_create() {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$delete_items = \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'numberposts' => -1,
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

		$this->assertCount( 1, $delete_items, 'publish -> draft must queue a Delete.' );

		// Simulate the Delete being sent (status flips to 'publish' for sent outbox items).
		\wp_update_post(
			array(
				'ID'          => $delete_items[0]->ID,
				'post_status' => 'publish',
			)
		);

		// Re-publish.
		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertGreaterThanOrEqual( 1, $this->count_pending_outbox_items( $activitypub_id, 'Create' ), 'Re-publishing after the Delete was sent must queue a fresh Create.' );

		// Sent Delete should still exist (we don't retroactively cancel sent activities;
		// only an explicit new Delete would wipe sent history via the supersession logic).
		$this->assertEquals( 'publish', \get_post_status( $delete_items[0]->ID ), 'Sent Delete activity must not be retroactively cancelled.' );
	}

	/**
	 * Test that re-saving a soft-deleted post in the same non-public state
	 * does not re-emit activities or flip the object state back to federated.
	 *
	 * Guards against the oscillation we saw earlier where every save in the
	 * locked state queued a new Update and toggled the state.
	 *
	 * @dataProvider data_non_public_status_transitions
	 *
	 * @covers ::triage
	 *
	 * @param string $hide_status Non-public status to dwell in.
	 */
	public function test_resave_in_soft_deleted_state_does_not_re_emit( $hide_status ) {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		// Hide → Delete queued, state flips to DELETED on outbox insert.
		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $hide_status,
			)
		);

		$this->assertSame( 1, $this->count_pending_outbox_items( $activitypub_id, 'Delete' ) );
		$this->assertSame( ACTIVITYPUB_OBJECT_STATE_DELETED, \get_post_meta( $post_id, 'activitypub_status', true ) );

		// Resave in the same non-public state — should be a no-op.
		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'EDITED-WHILE-HIDDEN',
			)
		);

		$this->assertSame( 1, $this->count_pending_outbox_items( $activitypub_id, 'Delete' ), 'Resave in soft-deleted state must not queue an additional Delete.' );
		$this->assertSame( 0, $this->count_pending_outbox_items( $activitypub_id, 'Update' ), 'Resave in soft-deleted state must not queue an Update.' );
		$this->assertSame( ACTIVITYPUB_OBJECT_STATE_DELETED, \get_post_meta( $post_id, 'activitypub_status', true ), 'Object state must remain deleted after resave.' );
	}

	/**
	 * Test multiple unpublish/publish cycles on a federated post.
	 *
	 * Each transition out emits Delete, each transition back invalidates the
	 * pending Delete and emits Create. After N cycles the pending queue
	 * holds exactly one Create.
	 *
	 * @covers ::triage
	 */
	public function test_multiple_unpublish_republish_cycles_settle_to_single_create() {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		for ( $i = 0; $i < 3; $i++ ) {
			\wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
			\wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
		}

		$this->assertSame( 0, $this->count_pending_outbox_items( $activitypub_id, 'Delete' ), 'No pending Delete should survive three publish/draft/publish cycles.' );
		$this->assertSame( 1, $this->count_pending_outbox_items( $activitypub_id, 'Create' ), 'Exactly one pending Create should remain after the cycles.' );
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

	/**
	 * Data provider: every way a post can be non-public, with how to hide and unhide it.
	 *
	 * Each case is a single array with `hide` and `unhide` specs; each spec may
	 * carry `post` (fields for wp_insert_post / wp_update_post) and/or `meta`
	 * (post meta to set). This drives the full transition matrix below.
	 *
	 * @return array[]
	 */
	public function data_hidden_states() {
		return array(
			'draft status'       => array(
				array(
					'hide'   => array( 'post' => array( 'post_status' => 'draft' ) ),
					'unhide' => array( 'post' => array( 'post_status' => 'publish' ) ),
				),
			),
			'pending status'     => array(
				array(
					'hide'   => array( 'post' => array( 'post_status' => 'pending' ) ),
					'unhide' => array( 'post' => array( 'post_status' => 'publish' ) ),
				),
			),
			'private status'     => array(
				array(
					'hide'   => array( 'post' => array( 'post_status' => 'private' ) ),
					'unhide' => array( 'post' => array( 'post_status' => 'publish' ) ),
				),
			),
			'password'           => array(
				array(
					'hide'   => array( 'post' => array( 'post_password' => 'fed-secret-pass' ) ),
					'unhide' => array( 'post' => array( 'post_password' => '' ) ),
				),
			),
			'visibility local'   => array(
				array(
					'hide'   => array( 'meta' => array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ) ),
					'unhide' => array( 'meta' => array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ) ),
				),
			),
			'visibility private' => array(
				array(
					'hide'   => array( 'meta' => array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE ) ),
					'unhide' => array( 'meta' => array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ) ),
				),
			),
		);
	}

	/**
	 * A post created directly in a non-public state must never federate.
	 *
	 * @dataProvider data_hidden_states
	 *
	 * @covers ::triage
	 *
	 * @param array $spec A row from data_hidden_states().
	 */
	public function test_initial_hidden_post_does_not_federate( $spec ) {
		$args = \array_merge(
			array(
				'post_author'  => self::$user_id,
				'post_content' => 'Should not federate.',
				'post_status'  => 'publish',
			),
			$spec['hide']['post'] ?? array()
		);

		if ( ! empty( $spec['hide']['meta'] ) ) {
			$args['meta_input'] = $spec['hide']['meta'];
		}

		$post_id        = self::factory()->post->create( $args );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$this->assertCount( 0, $this->get_outbox_items_for( $activitypub_id ), 'A post created in a non-public state must not federate.' );
	}

	/**
	 * Hiding a federated post emits a Delete whose object is a content-free Tombstone.
	 *
	 * @dataProvider data_hidden_states
	 *
	 * @covers ::triage
	 *
	 * @param array $spec A row from data_hidden_states().
	 */
	public function test_federated_post_hidden_emits_tombstone_delete( $spec ) {
		$post_id        = self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_content' => 'SECRET-BODY @bob@remote.example',
			)
		);
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$this->assertCount( 1, $this->get_outbox_items_for( $activitypub_id, 'Create' ), 'A public post should federate a Create.' );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$this->apply_post_state( $post_id, $spec['hide'] );

		$deletes = $this->get_outbox_items_for( $activitypub_id, 'Delete' );
		$this->assertCount( 1, $deletes, 'Hiding a federated post must emit exactly one Delete.' );

		// The Delete must carry a content-free Tombstone, not the post body.
		$activity = \json_decode( \get_post( $deletes[0]->ID )->post_content, true );
		$this->assertSame( 'Tombstone', $activity['object']['type'] ?? null, 'The Delete object must be a Tombstone.' );
		$this->assertArrayNotHasKey( 'content', (array) ( $activity['object'] ?? array() ), 'The Delete must not serialize post content.' );

		// It is addressed publicly so the teardown broadcasts to every server that held the post.
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', (array) ( $activity['to'] ?? array() ), 'The soft-delete Delete must be public so it fans out.' );
	}

	/**
	 * Making a soft-deleted post public again emits a fresh Create.
	 *
	 * @dataProvider data_hidden_states
	 *
	 * @covers ::triage
	 *
	 * @param array $spec A row from data_hidden_states().
	 */
	public function test_hidden_post_made_public_emits_create( $spec ) {
		$post_id        = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$activitypub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		// Hide it: emits a Delete and marks the object deleted.
		$this->apply_post_state( $post_id, $spec['hide'] );
		$this->assertCount( 1, $this->get_outbox_items_for( $activitypub_id, 'Delete' ), 'Hiding should emit a Delete.' );

		// Make it public again: must re-introduce the post as a Create.
		$this->apply_post_state( $post_id, $spec['unhide'] );

		$latest = $this->get_latest_outbox_item();
		$this->assertSame(
			'Create',
			\get_post_meta( $latest->ID, '_activitypub_activity_type', true ),
			'Making a soft-deleted post public again must emit a Create.'
		);
	}

	/**
	 * Apply a hide/unhide state spec to a post and trigger triage().
	 *
	 * @param int   $post_id The post ID.
	 * @param array $state   A `hide`/`unhide` spec with optional `post` and `meta`.
	 */
	private function apply_post_state( $post_id, $state ) {
		foreach ( $state['meta'] ?? array() as $key => $value ) {
			\update_post_meta( $post_id, $key, $value );
		}

		\wp_update_post( \array_merge( array( 'ID' => $post_id ), $state['post'] ?? array() ) );
	}

	/**
	 * Get pending outbox items for an object, optionally filtered by activity type.
	 *
	 * @param string      $activitypub_id The object ID.
	 * @param string|null $type           Optional. Activity type to filter by.
	 * @return \WP_Post[] The matching outbox items.
	 */
	private function get_outbox_items_for( $activitypub_id, $type = null ) {
		$meta_query = array(
			array(
				'key'   => '_activitypub_object_id',
				'value' => $activitypub_id,
			),
		);

		if ( $type ) {
			$meta_query[] = array(
				'key'   => '_activitypub_activity_type',
				'value' => $type,
			);
		}

		return \get_posts(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'pending',
				'numberposts' => -1,
				'meta_query'  => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
	}
}
