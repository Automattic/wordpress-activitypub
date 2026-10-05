<?php
/**
 * Test Post scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Scheduler\Post;

use function Activitypub\get_object_id;

/**
 * Test Post scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Post
 */
class Test_Post extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {
	/**
	 * Attachment permalink scenarios.
	 *
	 * @return array[] Test cases.
	 */
	public function data_attachment_permalinks() {
		return array(
			'legacy unattached' => array( true, false ),
			'legacy attached'   => array( true, true ),
			'modern unattached' => array( false, false ),
			'modern attached'   => array( false, true ),
		);
	}

	/**
	 * Inherited public attachments retain their identity during withdrawal.
	 *
	 * @dataProvider data_attachment_permalinks
	 * @covers ::save_canonical_url
	 * @covers ::transition_attachment_status
	 * @covers ::triage
	 *
	 * @param bool $legacy   Whether to use legacy permalink IDs.
	 * @param bool $attached Whether the attachment has a public parent.
	 */
	public function test_attachment_withdrawal_preserves_permalink( $legacy, $attached ) {
		$this->set_permalink_structure( '/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', $legacy ? PHP_INT_MAX : 0 );
		\add_post_type_support( 'attachment', 'activitypub' );
		\wp_set_current_user( self::$user_id );
		$parent_id = $attached ? self::factory()->post->create( array( 'post_author' => self::$user_id ) ) : 0;
		$post_id   = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg', $parent_id );
		$id        = get_object_id( \get_post( $post_id ) );
		$creates   = $this->get_outbox_items_for( $id, 'Create' );
		$this->assertSame( 'inherit', \get_post( $post_id )->post_status );
		$this->assertCount( 1, $creates );

		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
				'post_name'   => 'withdrawn-attachment',
			)
		);
		\remove_post_type_support( 'attachment', 'activitypub' );

		$this->assertSame( $id, get_object_id( \get_post( $post_id ) ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Delete' ) );
		$this->assertNull( \get_post( $creates[0]->ID ) );
		$this->assertCount( 0, $this->get_outbox_items_for( $id, 'Update' ) );
	}

	/**
	 * Public edits must not emit an intermediate Update with the saved URL.
	 *
	 * @covers ::save_canonical_url
	 * @covers ::transition_attachment_status
	 * @covers ::triage
	 */
	public function test_public_attachment_edit_uses_current_permalink() {
		$this->set_permalink_structure( '/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', PHP_INT_MAX );
		\add_post_type_support( 'attachment', 'activitypub' );
		\wp_set_current_user( self::$user_id );
		$post_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		$old_id  = get_object_id( \get_post( $post_id ) );

		\wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'renamed-attachment',
			)
		);
		$id = get_object_id( \get_post( $post_id ) );
		$this->assertNotSame( $old_id, $id );
		$this->assertSame( \get_permalink( $post_id ), $id );
		$this->assertSame( '', \get_post_meta( $post_id, '_activitypub_canonical_url', true ) );
		$this->assertCount( 0, $this->get_outbox_items_for( $old_id, 'Update' ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Update' ) );

		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		\remove_post_type_support( 'attachment', 'activitypub' );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Delete' ) );
	}

	/**
	 * Restore rewrite state after permalink tests.
	 */
	public function tear_down() {
		parent::tear_down();
		self::flush_cache();
		$GLOBALS['wp_rewrite']->init();
	}

	/**
	 * REST attachment visibility scenarios.
	 *
	 * @return array[] Test cases.
	 */
	public function data_rest_attachment_visibility() {
		return array(
			'public edit' => array( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ),
			'local edit'  => array( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL ),
		);
	}

	/**
	 * REST metadata must be applied before choosing the attachment identity.
	 *
	 * @dataProvider data_rest_attachment_visibility
	 * @covers ::save_canonical_url
	 * @covers ::transition_attachment_status
	 * @covers ::triage
	 *
	 * @param string $visibility Requested visibility.
	 */
	public function test_rest_attachment_edit_preserves_permalink( $visibility ) {
		$this->set_permalink_structure( '/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', PHP_INT_MAX );
		\add_post_type_support( 'attachment', 'activitypub' );
		\add_post_type_support( 'attachment', 'custom-fields' );
		\register_post_meta(
			'attachment',
			'activitypub_content_visibility',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
			)
		);
		\wp_set_current_user( self::$user_id );
		$post_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		$id      = get_object_id( \get_post( $post_id ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Create' ) );

		$request = new \WP_REST_Request( 'POST', '/wp/v2/media/' . $post_id );
		$request->set_param( 'slug', 'rest-updated-attachment' );
		$request->set_param( 'meta', array( 'activitypub_content_visibility' => $visibility ) );
		$response = \rest_get_server()->dispatch( $request );
		\unregister_post_meta( 'attachment', 'activitypub_content_visibility' );
		\remove_post_type_support( 'attachment', 'custom-fields' );
		\remove_post_type_support( 'attachment', 'activitypub' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $visibility, \get_post_meta( $post_id, 'activitypub_content_visibility', true ) );
		$this->assertCount( 0, $this->get_outbox_items_for( $id, 'Update' ), 'The early attachment hook must not emit an intermediate Update.' );
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL === $visibility ) {
			$this->assertSame( $id, get_object_id( \get_post( $post_id ) ) );
			$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Delete' ) );
			$this->assertCount( 0, $this->get_outbox_items_for( $id, 'Create' ) );
		} else {
			$new_id = get_object_id( \get_post( $post_id ) );
			$this->assertNotSame( $id, $new_id );
			$this->assertSame( \get_permalink( $post_id ), $new_id );
			$this->assertSame( '', \get_post_meta( $post_id, '_activitypub_canonical_url', true ) );
			$this->assertCount( 1, $this->get_outbox_items_for( $new_id, 'Update' ) );
		}
	}

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
	 * Custom REST saves must still run after an aborted core media request.
	 *
	 * @covers ::defer_attachment_update
	 * @covers ::clear_attachment_update
	 * @covers ::transition_attachment_status
	 */
	public function test_custom_rest_attachment_update_after_failed_media_request() {
		$this->set_permalink_structure( '/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', PHP_INT_MAX );
		\add_post_type_support( 'attachment', 'activitypub' );
		\wp_set_current_user( self::$user_id );
		$post_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		$id      = get_object_id( \get_post( $post_id ) );

		$reject = static function () {
			return new \WP_Error( 'test_rejected_attachment', 'Rejected for testing.', array( 'status' => 400 ) );
		};
		\add_filter( 'rest_pre_insert_attachment', $reject, 20 );
		$request  = new \WP_REST_Request( 'POST', '/wp/v2/media/' . $post_id );
		$response = \rest_get_server()->dispatch( $request );
		\remove_filter( 'rest_pre_insert_attachment', $reject, 20 );
		$this->assertSame( 400, $response->get_status() );

		\rest_get_server()->register_route(
			'activitypub-test/v1',
			'/activitypub-test/v1/attachment',
			array(
				array(
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'callback'            => static function () use ( $post_id ) {
						return \wp_update_post(
							array(
								'ID'          => $post_id,
								'post_status' => 'private',
								'post_name'   => 'custom-rest-attachment',
							)
						);
					},
				),
			)
		);
		$response = \rest_get_server()->dispatch( new \WP_REST_Request( 'POST', '/activitypub-test/v1/attachment' ) );
		\remove_post_type_support( 'attachment', 'activitypub' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $id, get_object_id( \get_post( $post_id ) ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Delete' ) );
		$this->assertCount( 0, $this->get_outbox_items_for( $id, 'Create' ) );
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
	 * Withdrawal transitions with legacy and modern IDs and both permalink modes.
	 *
	 * @return array[] Test cases.
	 */
	public function data_withdrawal_states() {
		$states = $this->data_hidden_states();
		foreach ( array( 'trash', 'auto-draft', 'inherit', 'withdrawn_test' ) as $status ) {
			$states[ $status ] = array( array( 'hide' => array( 'post' => array( 'post_status' => $status ) ) ) );
		}
		$states['disabled post type']        = array( array( 'hide' => array( 'disable' => true ) ) );
		$states['private with changed slug'] = array(
			array(
				'hide' => array(
					'post' => array(
						'post_status' => 'private',
						'post_name'   => 'changed-slug',
					),
				),
			),
		);

		$cases = array();
		foreach ( $states as $name => $state ) {
			foreach ( array( true, false ) as $legacy ) {
				foreach ( array( '/%postname%/', '/%category%/%postname%/', '' ) as $structure ) {
					$key           = $name . ( $legacy ? ' legacy ' : ' modern ' ) . $structure;
					$cases[ $key ] = array( $state[0]['hide'], $legacy, $structure );
				}
			}
		}
		return $cases;
	}

	/**
	 * Withdrawal must target the published ID and remove its complete outbox history.
	 *
	 * @dataProvider data_withdrawal_states
	 * @covers ::triage
	 *
	 * @param array  $state     The withdrawal state.
	 * @param bool   $legacy    Whether to use a legacy permalink ID.
	 * @param string $structure The permalink structure.
	 */
	public function test_withdrawal_preserves_object_id_and_removes_history( $state, $legacy, $structure ) {
		$this->set_permalink_structure( $structure );
		\update_option( 'activitypub_last_post_with_permalink_as_id', $legacy ? PHP_INT_MAX : 0 );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		\wp_set_current_user( 0 );
		\register_post_status( 'withdrawn_test', array( 'public' => false ) );
		$category = self::factory()->category->create( array( 'slug' => 'original-category' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_author'   => self::$user_id,
				'post_status'   => 'publish',
				'post_name'     => 'withdrawal-test',
				'post_content'  => 'Previously public content.',
				'post_category' => array( $category ),
			)
		);
		$id      = get_object_id( \get_post( $post_id ) );
		$creates = $this->get_outbox_items_for( $id, 'Create' );
		$this->assertCount( 1, $creates );
		\wp_update_post(
			array(
				'ID'          => $creates[0]->ID,
				'post_status' => 'publish',
			)
		);
		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Updated public content.',
			)
		);
		$updates = $this->get_outbox_items_for( $id, 'Update' );
		$this->assertCount( 1, $updates );
		$this->assertNotWPError( Outbox::maybe_get_activity( $creates[0] ) );

		$state['post']['post_category'] = array( self::factory()->category->create( array( 'slug' => 'changed-category' ) ) );

		if ( ! empty( $state['disable'] ) ) {
			\remove_post_type_support( 'post', 'activitypub' );
		}
		if ( 'trash' === ( $state['post']['post_status'] ?? '' ) ) {
			\wp_trash_post( $post_id );
		} else {
			$this->apply_post_state( $post_id, $state );
		}
		\add_post_type_support( 'post', 'activitypub' );
		unset( $GLOBALS['wp_post_statuses']['withdrawn_test'] );

		$deletes = $this->get_outbox_items_for( $id, 'Delete' );
		$this->assertCount( 1, $deletes, 'The Delete must target the original published ID.' );
		$stored = \json_decode( $deletes[0]->post_content, true );
		$this->assertSame( $id, $stored['object']['id'] );
		$this->assertSame( $stored['object']['id'], get_object_id( \get_post( $post_id ) ) );
		$this->assertSame( 'Tombstone', $stored['object']['type'] );
		$this->assertArrayNotHasKey( 'content', $stored['object'] );
		$this->assertNull( \get_post( $creates[0]->ID ), 'Already-sent snapshots must be removed.' );
		$this->assertNull( \get_post( $updates[0]->ID ), 'Pending snapshots must be removed.' );
		$this->assertWPError( Outbox::maybe_get_activity( $creates[0] ) );

		// Republishing must clear the saved URL, including before a later withdrawal.
		if ( 'trash' === \get_post_status( $post_id ) ) {
			\wp_untrash_post( $post_id );
		}
		$this->apply_post_state(
			$post_id,
			array(
				'post' => array(
					'post_status'   => 'publish',
					'post_password' => '',
					'post_name'     => 'republished-test',
				),
				'meta' => array( 'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ),
			)
		);
		$this->assertSame( '', \get_post_meta( $post_id, '_activitypub_canonical_url', true ) );
		$republished_id = get_object_id( \get_post( $post_id ) );
		$this->assertSame( $legacy ? \get_permalink( $post_id ) : $id, $republished_id );
		$this->assertCount( 1, $this->get_outbox_items_for( $republished_id, 'Create' ) );
		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$this->assertCount( 1, $this->get_outbox_items_for( $republished_id, 'Delete' ) );
	}

	/**
	 * REST updates apply terms after updating the post fields.
	 *
	 * @covers ::save_canonical_url
	 * @covers ::triage
	 */
	public function test_rest_withdrawal_preserves_category_permalink() {
		$this->set_permalink_structure( '/%category%/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', PHP_INT_MAX );
		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$old_category = self::factory()->category->create( array( 'slug' => 'old-category' ) );
		$new_category = self::factory()->category->create( array( 'slug' => 'new-category' ) );
		$post_id      = self::factory()->post->create(
			array(
				'post_author'   => self::$user_id,
				'post_status'   => 'publish',
				'post_category' => array( $old_category ),
			)
		);
		$id           = get_object_id( \get_post( $post_id ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Create' ) );

		$request = new \WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_param( 'status', 'private' );
		$request->set_param( 'categories', array( $new_category ) );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $new_category ), \wp_get_post_categories( $post_id ) );
		$this->assertSame( $id, get_object_id( \get_post( $post_id ) ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Delete' ) );
		$this->assertCount( 0, $this->get_outbox_items_for( $id, 'Create' ) );
	}

	/**
	 * Public edits must release the saved URL before the next withdrawal.
	 *
	 * @covers ::save_canonical_url
	 * @covers ::triage
	 */
	public function test_public_category_edit_does_not_keep_stale_canonical_url() {
		$this->set_permalink_structure( '/%category%/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', PHP_INT_MAX );
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$old_id  = get_object_id( \get_post( $post_id ) );
		\wp_update_post(
			array(
				'ID'            => $post_id,
				'post_category' => array( self::factory()->category->create( array( 'slug' => 'updated-category' ) ) ),
			)
		);

		$id = get_object_id( \get_post( $post_id ) );
		$this->assertNotSame( $old_id, $id );
		$this->assertSame( \get_permalink( $post_id ), $id );
		$this->assertSame( '', \get_post_meta( $post_id, '_activitypub_canonical_url', true ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Update' ) );

		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'pending',
			)
		);
		$this->assertSame( $id, get_object_id( \get_post( $post_id ) ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Delete' ) );
		$this->assertCount( 0, $this->get_outbox_items_for( $id, 'Update' ) );
	}

	/**
	 * Skipped public edits must not leave a saved URL behind.
	 *
	 * @covers ::save_canonical_url
	 */
	public function test_disabled_federation_does_not_save_canonical_url() {
		$this->set_permalink_structure( '/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', PHP_INT_MAX );
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$old_id  = get_object_id( \get_post( $post_id ) );

		\add_filter( 'activitypub_is_post_disabled', '__return_true' );
		\wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'edited-with-federation-disabled',
			)
		);
		\remove_filter( 'activitypub_is_post_disabled', '__return_true' );

		$this->assertSame( '', \get_post_meta( $post_id, '_activitypub_canonical_url', true ) );
		$this->assertNotSame( $old_id, get_object_id( \get_post( $post_id ) ) );
		$this->assertSame( \get_permalink( $post_id ), get_object_id( \get_post( $post_id ) ) );
	}

	/**
	 * Save paths used when republishing with federation disabled.
	 *
	 * @return array[] Test cases.
	 */
	public function data_disabled_republication() {
		return array(
			'post'            => array( false, false ),
			'post REST'       => array( false, true ),
			'attachment'      => array( true, false ),
			'attachment REST' => array( true, true ),
		);
	}

	/**
	 * URL cleanup must not depend on outgoing federation being enabled.
	 *
	 * @dataProvider data_disabled_republication
	 * @covers ::triage
	 * @covers ::transition_attachment_status
	 *
	 * @param bool $attachment Whether to use an attachment.
	 * @param bool $rest       Whether to republish through REST.
	 */
	public function test_disabled_republication_clears_canonical_url( $attachment, $rest ) {
		$this->set_permalink_structure( '/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', PHP_INT_MAX );
		\wp_set_current_user( self::$user_id );
		if ( $attachment ) {
			\add_post_type_support( 'attachment', 'activitypub' );
			$post_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );
		} else {
			$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		}
		$id = get_object_id( \get_post( $post_id ) );
		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$this->assertSame( $id, \get_post_meta( $post_id, '_activitypub_canonical_url', true ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Delete' ) );

		\add_filter( 'activitypub_is_post_disabled', '__return_true' );
		\wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'still-private',
			)
		);
		$hidden_url = \get_post_meta( $post_id, '_activitypub_canonical_url', true );
		$status     = $attachment ? 'inherit' : 'publish';
		if ( $rest ) {
			$request = new \WP_REST_Request( 'POST', '/wp/v2/' . ( $attachment ? 'media/' : 'posts/' ) . $post_id );
			$request->set_param( 'status', 'publish' );
			$request->set_param( 'slug', 'republished-with-federation-disabled' );
			$result = \rest_get_server()->dispatch( $request )->get_status();
		} else {
			$result = \wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $status,
					'post_name'   => 'republished-with-federation-disabled',
				)
			);
		}
		\remove_filter( 'activitypub_is_post_disabled', '__return_true' );
		if ( $attachment ) {
			\remove_post_type_support( 'attachment', 'activitypub' );
		}

		$this->assertSame( $rest ? 200 : $post_id, $result );
		$this->assertSame( $id, $hidden_url, 'An edit that stays private must retain the saved URL.' );
		$this->assertSame( '', \get_post_meta( $post_id, '_activitypub_canonical_url', true ) );
		$new_id = get_object_id( \get_post( $post_id ) );
		$this->assertNotSame( $id, $new_id );
		$this->assertSame( \get_permalink( $post_id ), $new_id );
		$this->assertCount( 0, $this->get_outbox_items_for( $new_id ), 'Disabled federation must not enqueue activities.' );
		$this->assertCount( 1, $this->get_outbox_items_for( $id ), 'Disabled federation must leave the existing Delete untouched.' );
	}

	/**
	 * Rescheduling must retain the published URL for a later withdrawal.
	 *
	 * @covers ::save_canonical_url
	 * @covers ::triage
	 */
	public function test_rescheduled_legacy_post_withdrawal_preserves_published_id() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
		\update_option( 'activitypub_last_post_with_permalink_as_id', PHP_INT_MAX );
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$id      = get_object_id( \get_post( $post_id ) );
		\wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'future',
				'post_date'     => \gmdate( 'Y-m-d H:i:s', \time() + YEAR_IN_SECONDS ),
				'post_date_gmt' => \gmdate( 'Y-m-d H:i:s', \time() + YEAR_IN_SECONDS ),
			)
		);
		$this->assertCount( 0, $this->get_outbox_items_for( $id, 'Delete' ) );
		$this->assertSame( $id, get_object_id( \get_post( $post_id ) ) );

		\wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'pending',
			)
		);
		$this->assertSame( $id, get_object_id( \get_post( $post_id ) ) );
		$this->assertCount( 1, $this->get_outbox_items_for( $id, 'Delete' ) );
		$this->assertCount( 0, $this->get_outbox_items_for( $id, 'Create' ) );
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
