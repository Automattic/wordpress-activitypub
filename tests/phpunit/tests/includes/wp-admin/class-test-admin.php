<?php
/**
 * Test file for Admin class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\WP_Admin\Admin;

/**
 * Test class for Admin.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Admin
 */
class Test_Admin extends \WP_UnitTestCase {
	/**
	 * User ID for testing.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Set up test resources.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		// Add activitypub capability to the user.
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Clean up test resources.
	 */
	public static function tear_down_after_class() {
		\wp_delete_user( self::$user_id );

		parent::tear_down_after_class();
	}

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		\wp_set_current_user( self::$user_id );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		_delete_all_posts();
	}

	/**
	 * Test post_bulk_options adds the Soft Delete option.
	 *
	 * @covers ::post_bulk_options
	 */
	public function test_post_bulk_options() {
		$actions = array(
			'edit'  => 'Edit',
			'trash' => 'Move to Trash',
		);

		$result = Admin::post_bulk_options( $actions );

		$this->assertArrayHasKey( 'activitypub_delete', $result );
		$this->assertEquals( 'Soft Delete', $result['activitypub_delete'] );
		// Ensure original actions are preserved.
		$this->assertArrayHasKey( 'edit', $result );
		$this->assertArrayHasKey( 'trash', $result );
	}

	/**
	 * Test handle_post_bulk_request returns early for non-activitypub actions.
	 *
	 * @covers ::handle_post_bulk_request
	 */
	public function test_handle_post_bulk_request_wrong_action() {
		$send_back = 'http://example.org/wp-admin/edit.php';
		$post_ids  = array( 1, 2, 3 );

		$result = Admin::handle_post_bulk_request( $send_back, 'trash', $post_ids );

		$this->assertEquals( $send_back, $result );
	}

	/**
	 * Test handle_post_bulk_request returns notice URL when no federated posts.
	 *
	 * @covers ::handle_post_bulk_request
	 */
	public function test_handle_post_bulk_request_no_federated_posts() {
		// Create a post and mark it as not federated (pending state).
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);

		// Explicitly set to pending state (not federated).
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_PENDING );

		$send_back = 'http://example.org/wp-admin/edit.php';

		$result = Admin::handle_post_bulk_request( $send_back, 'activitypub_delete', array( $post_id ) );

		$this->assertStringContainsString( 'activitypub_no_federated=1', $result );
	}

	/**
	 * Test row_actions adds Soft Delete for federated posts.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_adds_soft_delete_for_federated_post() {
		// Create a federated post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);

		// Mark as federated.
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$post    = \get_post( $post_id );
		$actions = array(
			'edit'  => '<a href="#">Edit</a>',
			'trash' => '<a href="#">Trash</a>',
		);

		$result = Admin::row_actions( $actions, $post );

		$this->assertArrayHasKey( 'activitypub_delete', $result );
		$this->assertStringContainsString( 'Soft Delete', $result['activitypub_delete'] );
		$this->assertStringContainsString( 'activitypub_delete_post', $result['activitypub_delete'] );
	}

	/**
	 * Test row_actions does not add Soft Delete for non-federated posts.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_no_soft_delete_for_non_federated_post() {
		// Create a post and mark as pending (not federated).
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);

		// Explicitly set to pending state (not federated).
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_PENDING );

		$post    = \get_post( $post_id );
		$actions = array(
			'edit'  => '<a href="#">Edit</a>',
			'trash' => '<a href="#">Trash</a>',
		);

		$result = Admin::row_actions( $actions, $post );

		$this->assertArrayNotHasKey( 'activitypub_delete', $result );
	}

	/**
	 * Test row_actions returns unchanged for unsupported post types.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_unsupported_post_type() {
		// Register an unsupported post type.
		\register_post_type( 'unsupported_type', array( 'public' => true ) );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_type'   => 'unsupported_type',
				'post_status' => 'publish',
			)
		);

		// Even mark it as federated - shouldn't matter.
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$post    = \get_post( $post_id );
		$actions = array(
			'edit' => '<a href="#">Edit</a>',
		);

		$result = Admin::row_actions( $actions, $post );

		$this->assertArrayNotHasKey( 'activitypub_delete', $result );

		\unregister_post_type( 'unsupported_type' );
	}

	/**
	 * Test add_removable_query_args adds the correct args.
	 *
	 * @covers ::add_removable_query_args
	 */
	public function test_add_removable_query_args() {
		$args = array( 'existing_arg' );

		$result = Admin::add_removable_query_args( $args );

		$this->assertContains( 'existing_arg', $result );
		$this->assertContains( 'activitypub_deleted', $result );
		$this->assertContains( 'activitypub_no_federated', $result );
		$this->assertContains( 'activitypub_no_posts', $result );
		$this->assertContains( 'activitypub_no_users', $result );
	}

	/**
	 * Test register_post_bulk_actions registers filters for supported post types.
	 *
	 * @covers ::register_post_bulk_actions
	 */
	public function test_register_post_bulk_actions() {
		// Clear existing filters.
		\remove_all_filters( 'bulk_actions-edit-post' );
		\remove_all_filters( 'handle_bulk_actions-edit-post' );

		Admin::register_post_bulk_actions();

		$this->assertTrue( \has_filter( 'bulk_actions-edit-post' ) !== false );
		$this->assertTrue( \has_filter( 'handle_bulk_actions-edit-post' ) !== false );
	}

	/**
	 * Test row_actions returns unchanged for users without edit capability.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_no_capability() {
		// Create a subscriber user.
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $subscriber_id );

		// Create a federated post by another user.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$post    = \get_post( $post_id );
		$actions = array(
			'view' => '<a href="#">View</a>',
		);

		$result = Admin::row_actions( $actions, $post );

		$this->assertArrayNotHasKey( 'activitypub_delete', $result );

		// Restore user.
		\wp_set_current_user( self::$user_id );
		\wp_delete_user( $subscriber_id );
	}
}
