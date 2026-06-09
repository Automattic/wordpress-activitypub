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

	/**
	 * Test handle_post_bulk_request filters out non-existent posts.
	 *
	 * @covers ::handle_post_bulk_request
	 */
	public function test_handle_post_bulk_request_non_existent_posts() {
		$send_back = 'http://example.org/wp-admin/edit.php';

		// Use non-existent post IDs.
		$result = Admin::handle_post_bulk_request( $send_back, 'activitypub_delete', array( 999999, 999998 ) );

		$this->assertStringContainsString( 'activitypub_no_federated=1', $result );
	}

	/**
	 * Test handle_post_bulk_request with mixed federated and non-federated posts.
	 *
	 * This test verifies that when selecting multiple posts, only federated ones
	 * are included in the confirmation redirect. Since the method redirects when
	 * federated posts exist, we test the filtering logic indirectly.
	 *
	 * @covers ::handle_post_bulk_request
	 */
	public function test_handle_post_bulk_request_mixed_posts() {
		// Create a non-federated post.
		$non_federated_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $non_federated_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_PENDING );

		// Create a deleted post (should not be included).
		$deleted_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $deleted_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_DELETED );

		$send_back = 'http://example.org/wp-admin/edit.php';

		// With only non-federated and deleted posts, should return notice.
		$result = Admin::handle_post_bulk_request(
			$send_back,
			'activitypub_delete',
			array( $non_federated_id, $deleted_id )
		);

		$this->assertStringContainsString( 'activitypub_no_federated=1', $result );
	}

	/**
	 * Test row_actions does not add Soft Delete for already deleted posts.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_no_soft_delete_for_deleted_post() {
		// Create a post marked as already deleted from Fediverse.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);

		// Mark as deleted.
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_DELETED );

		$post    = \get_post( $post_id );
		$actions = array(
			'edit'  => '<a href="#">Edit</a>',
			'trash' => '<a href="#">Trash</a>',
		);

		$result = Admin::row_actions( $actions, $post );

		$this->assertArrayNotHasKey( 'activitypub_delete', $result );
	}

	/**
	 * Test row_actions does not add Soft Delete for failed posts.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_no_soft_delete_for_failed_post() {
		// Create a post marked as failed.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);

		// Mark as failed.
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FAILED );

		$post    = \get_post( $post_id );
		$actions = array(
			'edit' => '<a href="#">Edit</a>',
		);

		$result = Admin::row_actions( $actions, $post );

		$this->assertArrayNotHasKey( 'activitypub_delete', $result );
	}

	/**
	 * Test row_actions includes correct nonce in delete URL.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_delete_url_has_nonce() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$post    = \get_post( $post_id );
		$actions = array();

		$result = Admin::row_actions( $actions, $post );

		$this->assertArrayHasKey( 'activitypub_delete', $result );
		$this->assertStringContainsString( '_wpnonce', $result['activitypub_delete'] );
		$this->assertStringContainsString( 'post_id=' . $post_id, $result['activitypub_delete'] );
	}

	/**
	 * Test row_actions includes confirmation dialog.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_delete_has_confirmation() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$post    = \get_post( $post_id );
		$actions = array();

		$result = Admin::row_actions( $actions, $post );

		$this->assertStringContainsString( 'class="activitypub-delete-link"', $result['activitypub_delete'] );
		$this->assertStringContainsString( 'data-activitypub-confirm', $result['activitypub_delete'] );
		$this->assertStringNotContainsString( 'onclick', $result['activitypub_delete'], 'Row action must not use an inline onclick handler.' );
	}

	/**
	 * Test add_removable_query_args includes all ActivityPub query args.
	 *
	 * @covers ::add_removable_query_args
	 */
	public function test_add_removable_query_args_complete() {
		$result = Admin::add_removable_query_args( array() );

		$expected_args = array(
			'activitypub_deleted',
			'activitypub_delete_failed',
			'activitypub_no_federated',
			'activitypub_no_users',
			'activitypub_no_posts',
		);

		foreach ( $expected_args as $arg ) {
			$this->assertContains( $arg, $result, "Missing removable query arg: {$arg}" );
		}
	}

	/**
	 * Test register_post_bulk_actions registers for page post type.
	 *
	 * @covers ::register_post_bulk_actions
	 */
	public function test_register_post_bulk_actions_pages() {
		// Ensure page post type supports activitypub.
		\add_post_type_support( 'page', 'activitypub' );

		// Clear existing filters.
		\remove_all_filters( 'bulk_actions-edit-page' );
		\remove_all_filters( 'handle_bulk_actions-edit-page' );

		Admin::register_post_bulk_actions();

		$this->assertTrue( \has_filter( 'bulk_actions-edit-page' ) !== false );
		$this->assertTrue( \has_filter( 'handle_bulk_actions-edit-page' ) !== false );
	}

	/**
	 * Test post_bulk_options preserves action order.
	 *
	 * @covers ::post_bulk_options
	 */
	public function test_post_bulk_options_preserves_order() {
		$actions = array(
			'edit'  => 'Edit',
			'trash' => 'Move to Trash',
		);

		$result = Admin::post_bulk_options( $actions );

		$keys = array_keys( $result );

		// Original actions should come first.
		$this->assertEquals( 'edit', $keys[0] );
		$this->assertEquals( 'trash', $keys[1] );
		// ActivityPub action added at end.
		$this->assertEquals( 'activitypub_delete', $keys[2] );
	}

	/**
	 * Test handle_post_bulk_request with empty post array.
	 *
	 * @covers ::handle_post_bulk_request
	 */
	public function test_handle_post_bulk_request_empty_array() {
		$send_back = 'http://example.org/wp-admin/edit.php';

		$result = Admin::handle_post_bulk_request( $send_back, 'activitypub_delete', array() );

		$this->assertStringContainsString( 'activitypub_no_federated=1', $result );
	}

	/**
	 * Test row_actions works with page post type.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_with_page() {
		// Ensure page post type supports activitypub.
		\add_post_type_support( 'page', 'activitypub' );

		$page_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $page_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$page    = \get_post( $page_id );
		$actions = array();

		$result = Admin::row_actions( $actions, $page );

		$this->assertArrayHasKey( 'activitypub_delete', $result );
		$this->assertStringContainsString( 'Soft Delete', $result['activitypub_delete'] );
	}

	/**
	 * Test row_actions with draft post does not add Soft Delete even if federated.
	 *
	 * Draft posts shouldn't normally be federated, but if somehow they are,
	 * the soft delete action should still be available since the state is federated.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_federated_draft() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'draft',
			)
		);
		// Hypothetically federated draft.
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$post    = \get_post( $post_id );
		$actions = array();

		$result = Admin::row_actions( $actions, $post );

		// Should still show soft delete since it's marked as federated.
		$this->assertArrayHasKey( 'activitypub_delete', $result );
	}

	/**
	 * Test row_actions delete link has proper title attribute.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_delete_has_title() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$post    = \get_post( $post_id );
		$actions = array();

		$result = Admin::row_actions( $actions, $post );

		$this->assertStringContainsString( 'title=', $result['activitypub_delete'] );
		$this->assertStringContainsString( 'Send Delete activity', $result['activitypub_delete'] );
	}

	/**
	 * Test that a single soft delete sends a Delete and marks the post local-only.
	 *
	 * @covers ::handle_single_post_delete
	 */
	public function test_handle_single_post_delete_marks_post_local() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$_GET['post_id']  = $post_id;
		$_GET['_wpnonce'] = \wp_create_nonce( 'activitypub-delete-post-' . $post_id );

		// Intercept the redirect so the handler does not exit the test run.
		$redirect = static function () {
			throw new \Exception( 'redirect' );
		};
		\add_filter( 'wp_redirect', $redirect );

		try {
			Admin::handle_single_post_delete();
			$this->fail( 'Expected a redirect.' );
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		} finally {
			\remove_filter( 'wp_redirect', $redirect );
			unset( $_GET['post_id'], $_GET['_wpnonce'] );
		}

		$this->assertSame(
			ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
			\get_post_meta( $post_id, 'activitypub_content_visibility', true ),
			'A soft-deleted post must be marked local-only, not private.'
		);
		$this->assertSame(
			ACTIVITYPUB_OBJECT_STATE_DELETED,
			\get_post_meta( $post_id, 'activitypub_status', true ),
			'Sending the Delete activity must move the post to the deleted state.'
		);
	}

	/**
	 * Test that a single soft delete with an invalid nonce is rejected.
	 *
	 * @covers ::handle_single_post_delete
	 */
	public function test_handle_single_post_delete_invalid_nonce() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$_GET['post_id']  = $post_id;
		$_GET['_wpnonce'] = 'invalid-nonce';

		try {
			$this->expectException( \WPDieException::class );
			Admin::handle_single_post_delete();
		} finally {
			unset( $_GET['post_id'], $_GET['_wpnonce'] );
		}
	}

	/**
	 * Test the bulk request stores IDs in a transient and redirects with a token (not in the URL).
	 *
	 * @covers ::handle_post_bulk_request
	 */
	public function test_handle_post_bulk_request_redirects_with_token() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$captured = null;
		$redirect = static function ( $location ) use ( &$captured ) {
			$captured = $location;
			throw new \Exception( 'redirect' );
		};
		\add_filter( 'wp_redirect', $redirect );

		try {
			Admin::handle_post_bulk_request( \admin_url( 'edit.php' ), 'activitypub_delete', array( $post_id ) );
			$this->fail( 'Expected a redirect.' );
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		} finally {
			\remove_filter( 'wp_redirect', $redirect );
		}

		$this->assertStringContainsString( 'action=activitypub_confirm_post_removal', $captured );
		$this->assertStringContainsString( 'token=', $captured );
		$this->assertStringContainsString( '_wpnonce=', $captured );
		$this->assertStringNotContainsString( 'posts%5B', $captured, 'Post IDs must not be passed in the URL.' );

		// The token resolves to the federated post via the transient.
		\parse_str( (string) \wp_parse_url( $captured, PHP_URL_QUERY ), $query );
		$stored = \get_transient( 'activitypub_bulk_delete_' . \get_current_user_id() . '_' . \sanitize_key( $query['token'] ) );
		$this->assertSame( array( $post_id ), $stored );
	}

	/**
	 * Test the bulk confirmation sends a Delete and marks posts local-only.
	 *
	 * @covers ::handle_bulk_post_delete_confirmation
	 */
	public function test_handle_bulk_post_delete_confirmation_deletes() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		$_POST['_wpnonce']       = \wp_create_nonce( 'activitypub-bulk-post-delete' );
		$_POST['selected_posts'] = array( $post_id );
		$_POST['send_back']      = \admin_url( 'edit.php' );

		$captured = null;
		$redirect = static function ( $location ) use ( &$captured ) {
			$captured = $location;
			throw new \Exception( 'redirect' );
		};
		\add_filter( 'wp_redirect', $redirect );

		try {
			Admin::handle_bulk_post_delete_confirmation();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		} finally {
			\remove_filter( 'wp_redirect', $redirect );
			unset( $_POST['_wpnonce'], $_POST['selected_posts'], $_POST['send_back'] );
		}

		$this->assertSame(
			ACTIVITYPUB_OBJECT_STATE_DELETED,
			\get_post_meta( $post_id, 'activitypub_status', true ),
			'The post must move to the deleted state.'
		);
		$this->assertSame(
			ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
			\get_post_meta( $post_id, 'activitypub_content_visibility', true ),
			'The post must be marked local-only.'
		);
		$this->assertStringContainsString( 'activitypub_deleted=1', $captured );
	}

	/**
	 * Test the bulk confirmation rejects an invalid nonce.
	 *
	 * @covers ::handle_bulk_post_delete_confirmation
	 */
	public function test_handle_bulk_post_delete_confirmation_invalid_nonce() {
		$_POST['_wpnonce'] = 'invalid-nonce';

		try {
			$this->expectException( \WPDieException::class );
			Admin::handle_bulk_post_delete_confirmation();
		} finally {
			unset( $_POST['_wpnonce'] );
		}
	}

	/**
	 * Test that an editor of a custom-capability post type can bulk soft delete its posts.
	 *
	 * @covers ::handle_bulk_post_delete_confirmation
	 */
	public function test_handle_bulk_post_delete_confirmation_allows_cpt_only_editor() {
		\register_post_type(
			'ap_event',
			array(
				'public'          => true,
				'capability_type' => array( 'ap_event', 'ap_events' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'editor', 'activitypub' ),
			)
		);
		\add_role(
			'ap_event_editor',
			'AP Event Editor',
			array(
				'read'                     => true,
				'edit_ap_events'           => true,
				'edit_published_ap_events' => true,
				'publish_ap_events'        => true,
			)
		);
		$editor_id = self::factory()->user->create( array( 'role' => 'ap_event_editor' ) );
		\get_user_by( 'id', $editor_id )->add_cap( 'activitypub' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'ap_event',
				'post_status' => 'publish',
				'post_author' => $editor_id,
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', ACTIVITYPUB_OBJECT_STATE_FEDERATED );

		\wp_set_current_user( $editor_id );

		// Precondition: this user cannot edit regular posts but can edit its own CPT post.
		$this->assertFalse( \current_user_can( 'edit_posts' ), 'Precondition: no edit_posts capability.' );
		$this->assertTrue( \current_user_can( 'edit_post', $post_id ), 'Precondition: can edit its own CPT post.' );

		$_POST['_wpnonce']       = \wp_create_nonce( 'activitypub-bulk-post-delete' );
		$_POST['selected_posts'] = array( $post_id );
		$_POST['send_back']      = \admin_url( 'edit.php' );

		$redirect = static function () {
			throw new \Exception( 'redirect' );
		};
		\add_filter( 'wp_redirect', $redirect );

		try {
			Admin::handle_bulk_post_delete_confirmation();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage(), 'Handler must not die on the edit_posts gate for a CPT-only editor.' );
		} finally {
			\remove_filter( 'wp_redirect', $redirect );
			unset( $_POST['_wpnonce'], $_POST['selected_posts'], $_POST['send_back'] );
			\wp_set_current_user( self::$user_id );
			\remove_role( 'ap_event_editor' );
			\unregister_post_type( 'ap_event' );
		}

		$this->assertSame(
			ACTIVITYPUB_OBJECT_STATE_DELETED,
			\get_post_meta( $post_id, 'activitypub_status', true ),
			'A custom-capability editor must be able to bulk soft delete its federated posts.'
		);
	}

	/**
	 * Test the select-all/confirm handler is enqueued on the user confirmation page.
	 *
	 * Regression: the handler used to load only on edit.php, leaving the user
	 * confirmation screen (rendered from users.php) without a working select-all.
	 *
	 * @covers ::enqueue_scripts
	 */
	public function test_select_all_handler_enqueued_on_users_page() {
		\wp_enqueue_script( 'common' );
		Admin::enqueue_scripts( 'users.php' );

		$after = \implode( '', (array) \wp_scripts()->get_data( 'common', 'after' ) );
		$this->assertStringContainsString(
			'cb-select-all',
			$after,
			'Select-all handler must be enqueued on the user confirmation page.'
		);
	}
}
