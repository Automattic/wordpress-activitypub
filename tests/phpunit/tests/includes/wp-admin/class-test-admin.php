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
	 * Create a post of the test user in a given federation state.
	 *
	 * @param string $state The ActivityPub object state.
	 * @param array  $args  Additional post arguments.
	 *
	 * @return int The post ID.
	 */
	private function create_post( $state = ACTIVITYPUB_OBJECT_STATE_FEDERATED, $args = array() ) {
		$post_id = self::factory()->post->create(
			\wp_parse_args(
				$args,
				array(
					'post_author' => self::$user_id,
					'post_status' => 'publish',
				)
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', $state );

		return $post_id;
	}

	/**
	 * Run a handler that ends in a redirect and return the redirect target.
	 *
	 * @param callable $handler The handler to run.
	 *
	 * @return string The captured redirect URL.
	 */
	private function capture_redirect( $handler ) {
		$captured = '';
		$redirect = static function ( $location ) use ( &$captured ) {
			$captured = $location;
			throw new \Exception( 'redirect' );
		};
		\add_filter( 'wp_redirect', $redirect );

		try {
			$handler();
			$this->fail( 'Expected a redirect.' );
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		} finally {
			\remove_filter( 'wp_redirect', $redirect );
		}

		return $captured;
	}

	/**
	 * Test post_bulk_options adds the Soft Delete option after the existing ones.
	 *
	 * @covers ::post_bulk_options
	 */
	public function test_post_bulk_options() {
		$result = Admin::post_bulk_options(
			array(
				'edit'  => 'Edit',
				'trash' => 'Move to Trash',
			)
		);

		$this->assertSame( array( 'edit', 'trash', 'activitypub_delete' ), \array_keys( $result ) );
		$this->assertEquals( 'Soft Delete', $result['activitypub_delete'] );
	}

	/**
	 * Test handle_post_bulk_request returns early for non-activitypub actions.
	 *
	 * @covers ::handle_post_bulk_request
	 */
	public function test_handle_post_bulk_request_wrong_action() {
		$send_back = 'http://example.org/wp-admin/edit.php';

		$this->assertEquals( $send_back, Admin::handle_post_bulk_request( $send_back, 'trash', array( 1, 2, 3 ) ) );
	}

	/**
	 * A selection without a federated post goes back with the "no federated posts" notice.
	 *
	 * @covers ::handle_post_bulk_request
	 * @dataProvider data_selection_without_federated_posts
	 *
	 * @param string|null $state    The state of a post to create, or null for none.
	 * @param int[]       $post_ids The IDs to select when no post is created.
	 */
	public function test_handle_post_bulk_request_without_federated_posts( $state, $post_ids = array() ) {
		if ( null !== $state ) {
			$post_ids = array( $this->create_post( $state ) );
		}

		$result = Admin::handle_post_bulk_request( 'http://example.org/wp-admin/edit.php', 'activitypub_delete', $post_ids );

		$this->assertStringContainsString( 'activitypub_no_federated=1', $result );
	}

	/**
	 * Data provider for selections without a federated post.
	 *
	 * @return array[] Test parameters.
	 */
	public function data_selection_without_federated_posts() {
		return array(
			'pending post'     => array( ACTIVITYPUB_OBJECT_STATE_PENDING ),
			'deleted post'     => array( ACTIVITYPUB_OBJECT_STATE_DELETED ),
			'non-existent ids' => array( null, array( 999999, 999998 ) ),
			'empty selection'  => array( null, array() ),
		);
	}

	/**
	 * Test the bulk request stores IDs in a transient and redirects with a token (not in the URL).
	 *
	 * @covers ::handle_post_bulk_request
	 */
	public function test_handle_post_bulk_request_redirects_with_token() {
		$post_id = $this->create_post();

		$captured = $this->capture_redirect(
			static function () use ( $post_id ) {
				Admin::handle_post_bulk_request( \admin_url( 'edit.php' ), 'activitypub_delete', array( $post_id ) );
			}
		);

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
	 * Test row_actions adds a nonced, confirmed Soft Delete link for federated posts.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_adds_soft_delete_for_federated_post() {
		$post_id = $this->create_post();

		$result = Admin::row_actions( array( 'edit' => '<a href="#">Edit</a>' ), \get_post( $post_id ) );

		$this->assertArrayHasKey( 'edit', $result );
		$this->assertArrayHasKey( 'activitypub_delete', $result );

		$link = $result['activitypub_delete'];
		$this->assertStringContainsString( 'Soft Delete', $link );
		$this->assertStringContainsString( 'activitypub_delete_post', $link );
		$this->assertStringContainsString( 'post_id=' . $post_id, $link );
		$this->assertStringContainsString( '_wpnonce', $link );
		$this->assertStringContainsString( 'class="activitypub-delete-link"', $link );
		$this->assertStringContainsString( 'data-activitypub-confirm', $link );
		$this->assertStringNotContainsString( 'onclick', $link, 'Row action must not use an inline onclick handler.' );
		$this->assertStringContainsString( 'title=', $link );
		$this->assertStringContainsString( 'Send Delete activity', $link );
	}

	/**
	 * Test row_actions adds no Soft Delete for posts that are not federated.
	 *
	 * @covers ::row_actions
	 * @dataProvider data_not_federated_states
	 *
	 * @param string $state The ActivityPub object state.
	 */
	public function test_row_actions_no_soft_delete_when_not_federated( $state ) {
		$post_id = $this->create_post( $state );

		$result = Admin::row_actions( array( 'edit' => '<a href="#">Edit</a>' ), \get_post( $post_id ) );

		$this->assertArrayNotHasKey( 'activitypub_delete', $result );
	}

	/**
	 * Data provider for states that are not federated.
	 *
	 * @return array[] Test parameters.
	 */
	public function data_not_federated_states() {
		return array(
			'pending' => array( ACTIVITYPUB_OBJECT_STATE_PENDING ),
			'deleted' => array( ACTIVITYPUB_OBJECT_STATE_DELETED ),
			'failed'  => array( ACTIVITYPUB_OBJECT_STATE_FAILED ),
		);
	}

	/**
	 * Test row_actions returns unchanged for unsupported post types.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_unsupported_post_type() {
		\register_post_type( 'unsupported_type', array( 'public' => true ) );

		$post_id = $this->create_post( ACTIVITYPUB_OBJECT_STATE_FEDERATED, array( 'post_type' => 'unsupported_type' ) );

		$result = Admin::row_actions( array( 'edit' => '<a href="#">Edit</a>' ), \get_post( $post_id ) );

		\unregister_post_type( 'unsupported_type' );

		$this->assertArrayNotHasKey( 'activitypub_delete', $result );
	}

	/**
	 * Test row_actions returns unchanged for users without edit capability.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_no_capability() {
		$post_id = $this->create_post();

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $subscriber_id );

		$result = Admin::row_actions( array( 'view' => '<a href="#">View</a>' ), \get_post( $post_id ) );

		$this->assertArrayNotHasKey( 'activitypub_delete', $result );
	}

	/**
	 * Test row_actions works with the page post type.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_with_page() {
		\add_post_type_support( 'page', 'activitypub' );

		$page_id = $this->create_post( ACTIVITYPUB_OBJECT_STATE_FEDERATED, array( 'post_type' => 'page' ) );

		$result = Admin::row_actions( array(), \get_post( $page_id ) );

		\remove_post_type_support( 'page', 'activitypub' );

		$this->assertArrayHasKey( 'activitypub_delete', $result );
	}

	/**
	 * A federated draft still gets the Soft Delete action; the state decides, not the status.
	 *
	 * @covers ::row_actions
	 */
	public function test_row_actions_adds_soft_delete_for_federated_draft() {
		$post_id = $this->create_post( ACTIVITYPUB_OBJECT_STATE_FEDERATED, array( 'post_status' => 'draft' ) );

		$result = Admin::row_actions( array(), \get_post( $post_id ) );

		$this->assertArrayHasKey( 'activitypub_delete', $result );
	}

	/**
	 * Test add_removable_query_args keeps existing args and adds every ActivityPub notice arg.
	 *
	 * @covers ::add_removable_query_args
	 */
	public function test_add_removable_query_args() {
		$result = Admin::add_removable_query_args( array( 'existing_arg' ) );

		$expected_args = array(
			'existing_arg',
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
	 * Test register_post_bulk_actions registers filters for supported post types.
	 *
	 * @covers ::register_post_bulk_actions
	 */
	public function test_register_post_bulk_actions() {
		\remove_all_filters( 'bulk_actions-edit-post' );
		\remove_all_filters( 'handle_bulk_actions-edit-post' );

		Admin::register_post_bulk_actions();

		$this->assertNotFalse( \has_filter( 'bulk_actions-edit-post', array( Admin::class, 'post_bulk_options' ) ) );
		$this->assertNotFalse( \has_filter( 'handle_bulk_actions-edit-post', array( Admin::class, 'handle_post_bulk_request' ) ) );
	}

	/**
	 * The media library lists attachments on the upload screen, which has its own bulk-action hooks.
	 *
	 * @covers ::register_post_bulk_actions
	 */
	public function test_register_post_bulk_actions_uses_the_upload_screen_for_attachments() {
		\add_post_type_support( 'attachment', 'activitypub' );
		\remove_all_filters( 'bulk_actions-upload' );
		\remove_all_filters( 'bulk_actions-edit-attachment' );

		Admin::register_post_bulk_actions();

		\remove_post_type_support( 'attachment', 'activitypub' );

		$this->assertNotFalse( \has_filter( 'bulk_actions-upload', array( Admin::class, 'post_bulk_options' ) ) );
		$this->assertNotFalse( \has_filter( 'handle_bulk_actions-upload', array( Admin::class, 'handle_post_bulk_request' ) ) );
		$this->assertFalse( \has_filter( 'bulk_actions-edit-attachment', array( Admin::class, 'post_bulk_options' ) ) );
	}

	/**
	 * The confirm handler for the Soft Delete row action is enqueued on the posts screen.
	 *
	 * @covers ::enqueue_scripts
	 */
	public function test_confirm_handler_enqueued_on_posts_screen() {
		\wp_enqueue_script( 'common' );
		Admin::enqueue_scripts( 'edit.php' );

		$after = \implode( '', (array) \wp_scripts()->get_data( 'common', 'after' ) );
		$this->assertStringContainsString( 'activitypub-delete-link', $after );
	}

	/**
	 * Test that a single soft delete sends a Delete and marks the post local-only.
	 *
	 * @covers ::handle_single_post_delete
	 */
	public function test_handle_single_post_delete_marks_post_local() {
		$post_id = $this->create_post();

		$_GET['post_id']  = $post_id;
		$_GET['_wpnonce'] = \wp_create_nonce( 'activitypub-delete-post-' . $post_id );

		$captured = $this->capture_redirect( array( Admin::class, 'handle_single_post_delete' ) );

		$this->assertStringContainsString( 'activitypub_deleted=1', $captured );
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
		$_GET['post_id']  = $this->create_post();
		$_GET['_wpnonce'] = 'invalid-nonce';

		$this->expectException( \WPDieException::class );
		Admin::handle_single_post_delete();
	}

	/**
	 * A token another user stored resolves to nothing for the current user and stays untouched.
	 *
	 * @covers ::handle_bulk_post_delete_page
	 */
	public function test_handle_bulk_post_delete_page_ignores_a_foreign_token() {
		$token = \wp_generate_uuid4();
		$key   = 'activitypub_bulk_delete_' . ( self::$user_id + 1 ) . '_' . $token;
		\set_transient( $key, array( 999999 ), MINUTE_IN_SECONDS );

		$_GET['_wpnonce'] = \wp_create_nonce( 'activitypub-confirm-post-removal' );
		$_GET['token']    = $token;

		$captured = $this->capture_redirect( array( Admin::class, 'handle_bulk_post_delete_page' ) );

		$this->assertStringContainsString( 'activitypub_no_posts=1', $captured );
		$this->assertSame( array( 999999 ), \get_transient( $key ), 'The other user\'s token must not be consumed.' );
	}

	/**
	 * A token is deleted once it has been consumed.
	 *
	 * @covers ::handle_bulk_post_delete_page
	 */
	public function test_handle_bulk_post_delete_page_consumes_the_token() {
		$token = \wp_generate_uuid4();
		$key   = 'activitypub_bulk_delete_' . \get_current_user_id() . '_' . $token;
		\set_transient( $key, array( 999999 ), MINUTE_IN_SECONDS );

		$_GET['_wpnonce'] = \wp_create_nonce( 'activitypub-confirm-post-removal' );
		$_GET['token']    = $token;

		$this->capture_redirect( array( Admin::class, 'handle_bulk_post_delete_page' ) );

		$this->assertFalse( \get_transient( $key ), 'The token must be consumed by the first request.' );
	}

	/**
	 * A list URL with an encoded search term survives the round trip to the confirmation page.
	 *
	 * @covers ::handle_bulk_post_delete_page
	 */
	public function test_handle_bulk_post_delete_page_keeps_encoded_send_back() {
		$_GET['_wpnonce'] = \wp_create_nonce( 'activitypub-confirm-post-removal' );
		$_GET['token']    = 'unknown';
		// What PHP hands us after decoding the rawurlencoded value once.
		$_GET['send_back'] = \admin_url( 'edit.php?s=caf%C3%A9' );

		$captured = $this->capture_redirect( array( Admin::class, 'handle_bulk_post_delete_page' ) );

		$this->assertStringContainsString( 's=caf%C3%A9', $captured );
	}

	/**
	 * Test the bulk confirmation sends a Delete and marks posts local-only.
	 *
	 * @covers ::handle_bulk_post_delete_confirmation
	 */
	public function test_handle_bulk_post_delete_confirmation_deletes() {
		$post_id = $this->create_post();

		$_POST['_wpnonce']       = \wp_create_nonce( 'activitypub-bulk-post-delete' );
		$_POST['selected_posts'] = array( $post_id );
		$_POST['send_back']      = \admin_url( 'edit.php' );

		$captured = $this->capture_redirect( array( Admin::class, 'handle_bulk_post_delete_confirmation' ) );

		$this->assertStringContainsString( 'activitypub_deleted=1', $captured );
		$this->assertSame( ACTIVITYPUB_OBJECT_STATE_DELETED, \get_post_meta( $post_id, 'activitypub_status', true ) );
		$this->assertSame( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL, \get_post_meta( $post_id, 'activitypub_content_visibility', true ) );
	}

	/**
	 * Submitting the confirmation with nothing selected reports it instead of redirecting silently.
	 *
	 * @covers ::handle_bulk_post_delete_confirmation
	 */
	public function test_handle_bulk_post_delete_confirmation_reports_empty_selection() {
		$_POST['_wpnonce']       = \wp_create_nonce( 'activitypub-bulk-post-delete' );
		$_POST['selected_posts'] = array();
		$_POST['send_back']      = \admin_url( 'edit.php' );

		$captured = $this->capture_redirect( array( Admin::class, 'handle_bulk_post_delete_confirmation' ) );

		$this->assertStringContainsString( 'activitypub_no_posts=1', $captured );
	}

	/**
	 * When no Delete could be queued, the user sees the failure notice, not a success count of zero.
	 *
	 * @covers ::handle_bulk_post_delete_confirmation
	 */
	public function test_handle_bulk_post_delete_confirmation_reports_when_nothing_was_deleted() {
		$post_id = $this->create_post();

		// Fail every outbox insert.
		$fail_outbox = static function ( $maybe_empty, $postarr ) {
			return 'ap_outbox' === ( $postarr['post_type'] ?? '' ) ? true : $maybe_empty;
		};
		\add_filter( 'wp_insert_post_empty_content', $fail_outbox, 10, 2 );

		$_POST['_wpnonce']       = \wp_create_nonce( 'activitypub-bulk-post-delete' );
		$_POST['selected_posts'] = array( $post_id );
		$_POST['send_back']      = \admin_url( 'edit.php' );

		$captured = $this->capture_redirect( array( Admin::class, 'handle_bulk_post_delete_confirmation' ) );

		\remove_filter( 'wp_insert_post_empty_content', $fail_outbox, 10 );

		$this->assertStringContainsString( 'activitypub_delete_failed=1', $captured );
		$this->assertStringNotContainsString( 'activitypub_deleted=0', $captured );
	}

	/**
	 * Test the bulk confirmation rejects an invalid nonce.
	 *
	 * @covers ::handle_bulk_post_delete_confirmation
	 */
	public function test_handle_bulk_post_delete_confirmation_invalid_nonce() {
		$_POST['_wpnonce'] = 'invalid-nonce';

		$this->expectException( \WPDieException::class );
		Admin::handle_bulk_post_delete_confirmation();
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

		$post_id = $this->create_post(
			ACTIVITYPUB_OBJECT_STATE_FEDERATED,
			array(
				'post_type'   => 'ap_event',
				'post_author' => $editor_id,
			)
		);

		\wp_set_current_user( $editor_id );

		// Precondition: this user cannot edit regular posts but can edit its own CPT post.
		$this->assertFalse( \current_user_can( 'edit_posts' ) );
		$this->assertTrue( \current_user_can( 'edit_post', $post_id ) );

		$_POST['_wpnonce']       = \wp_create_nonce( 'activitypub-bulk-post-delete' );
		$_POST['selected_posts'] = array( $post_id );
		$_POST['send_back']      = \admin_url( 'edit.php' );

		$this->capture_redirect( array( Admin::class, 'handle_bulk_post_delete_confirmation' ) );

		\remove_role( 'ap_event_editor' );
		\unregister_post_type( 'ap_event' );

		$this->assertSame(
			ACTIVITYPUB_OBJECT_STATE_DELETED,
			\get_post_meta( $post_id, 'activitypub_status', true ),
			'A custom-capability editor must be able to bulk soft delete its federated posts.'
		);
	}

	/**
	 * The Source column is registered next to the other comment columns.
	 *
	 * @covers ::manage_comment_columns
	 */
	public function test_source_column_is_registered() {
		$columns = Admin::manage_comment_columns( array( 'author' => 'Author' ) );

		$this->assertArrayHasKey( 'comment_source', $columns );
	}

	/**
	 * A received comment links to the remote post it came from.
	 *
	 * @covers ::get_comment_source_link
	 * @covers ::manage_comments_custom_column
	 */
	public function test_received_comment_links_to_its_source() {
		$comment_id = self::factory()->comment->create();
		\add_comment_meta( $comment_id, 'protocol', 'activitypub' );
		\add_comment_meta( $comment_id, 'source_url', 'https://example.com/posts/123' );

		$link = Admin::get_comment_source_link( $comment_id );

		$this->assertStringStartsWith( '<a href="https://example.com/posts/123" title="https://example.com/posts/123" target="_blank" rel="noopener noreferrer">example.com', $link );
		$this->assertStringContainsString( 'dashicons-external', $link );
		$this->assertStringContainsString( 'screen-reader-text', $link );

		\ob_start();
		Admin::manage_comments_custom_column( 'comment_source', $comment_id );
		$this->assertStringContainsString( 'https://example.com/posts/123', \ob_get_clean() );
	}

	/**
	 * A source URL that is not a usable link is not linked.
	 *
	 * `esc_url()` returns an empty string for a URL with a disallowed protocol.
	 *
	 * @covers ::get_comment_source_link
	 */
	public function test_unusable_source_url_is_not_linked() {
		$comment_id = self::factory()->comment->create();
		\add_comment_meta( $comment_id, 'protocol', 'activitypub' );
		\add_comment_meta( $comment_id, 'source_url', 'javascript:alert(1)' );

		$this->assertSame( '', Admin::get_comment_source_link( $comment_id ) );
	}

	/**
	 * A received comment without a source URL gets no link.
	 *
	 * Reactions like Likes or Announces only store an ActivityPub ID as `source_id`,
	 * which is not always a browsable page.
	 *
	 * @covers ::get_comment_source_link
	 */
	public function test_comment_without_source_url_is_not_linked() {
		$comment_id = self::factory()->comment->create();
		\add_comment_meta( $comment_id, 'protocol', 'activitypub' );

		$this->assertSame( '', Admin::get_comment_source_link( $comment_id ) );

		\add_comment_meta( $comment_id, 'source_id', 'https://example.com/activity/1' );

		$this->assertSame( '', Admin::get_comment_source_link( $comment_id ) );
	}

	/**
	 * A local comment gets no link, even with a source URL in its meta.
	 *
	 * @covers ::get_comment_source_link
	 */
	public function test_local_comment_is_not_linked() {
		$comment_id = self::factory()->comment->create();
		\add_comment_meta( $comment_id, 'source_url', 'https://example.com/posts/123' );

		$this->assertSame( '', Admin::get_comment_source_link( $comment_id ) );
	}

	/**
	 * Saving the profile with a non-image header image id deletes the user option.
	 *
	 * @covers ::save_user_settings
	 */
	public function test_save_user_settings_rejects_non_image_header_image() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'text/plain' ) );
		\update_user_option( $user_id, 'activitypub_header_image', 123 );

		$_REQUEST['_apnonce']              = \wp_create_nonce( 'activitypub-user-settings' );
		$_POST['activitypub_header_image'] = (string) $attachment_id;

		Admin::save_user_settings( $user_id );

		$this->assertFalse( \get_user_option( 'activitypub_header_image', $user_id ) );

		unset( $_REQUEST['_apnonce'], $_POST['activitypub_header_image'] );
	}

	/**
	 * Saving the profile with an image header image id stores the attachment id.
	 *
	 * @covers ::save_user_settings
	 */
	public function test_save_user_settings_stores_image_header_image() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$attachment_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );

		$_REQUEST['_apnonce']              = \wp_create_nonce( 'activitypub-user-settings' );
		$_POST['activitypub_header_image'] = (string) $attachment_id;

		Admin::save_user_settings( $user_id );

		$this->assertSame( $attachment_id, (int) \get_user_option( 'activitypub_header_image', $user_id ) );

		unset( $_REQUEST['_apnonce'], $_POST['activitypub_header_image'] );
	}
}
