<?php
/**
 * Test file for Admin.
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

	/**
	 * Every ActivityPub bulk action on the users screen requires the bulk-users nonce.
	 *
	 * Loops over all bulk actions the plugin registers, so a new one is covered as soon as it is added.
	 *
	 * @covers ::handle_bulk_request
	 */
	public function test_bulk_actions_require_nonce() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $admin_id );

		$actions   = \array_keys( Admin::user_bulk_options( array() ) );
		$actions[] = 'delete_actor_confirmed';

		foreach ( $actions as $action ) {
			$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
			$died    = false;

			try {
				Admin::handle_bulk_request( 'users.php', $action, array( $user_id ) );
			} catch ( \WPDieException $e ) {
				$died = true;
			}

			$this->assertTrue( $died, \sprintf( 'Bulk action "%s" ran without a nonce.', $action ) );
			$this->assertFalse( \user_can( $user_id, 'activitypub' ), \sprintf( 'Bulk action "%s" changed the user without a nonce.', $action ) );
		}
	}

	/**
	 * An ActivityPub bulk action requires the capability to promote users.
	 *
	 * @covers ::handle_bulk_request
	 */
	public function test_bulk_action_requires_promote_users() {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$user_id   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $editor_id );

		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'bulk-users' );

		$died = false;
		try {
			Admin::handle_bulk_request( 'users.php', 'add_activitypub_cap', array( $user_id ) );
		} catch ( \WPDieException $e ) {
			$died = true;
		}

		unset( $_REQUEST['_wpnonce'] );

		$this->assertTrue( $died );
		$this->assertFalse( \user_can( $user_id, 'activitypub' ) );
	}

	/**
	 * An administrator with a valid nonce can enable users for ActivityPub.
	 *
	 * @covers ::handle_bulk_request
	 */
	public function test_bulk_action_with_nonce_adds_capability() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $admin_id );

		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'bulk-users' );

		$result = Admin::handle_bulk_request( 'users.php', 'add_activitypub_cap', array( $user_id ) );

		unset( $_REQUEST['_wpnonce'] );

		$this->assertSame( 'users.php', $result );
		$this->assertTrue( \user_can( $user_id, 'activitypub' ) );
	}

	/**
	 * Bulk actions of other plugins pass through untouched, with or without a nonce.
	 *
	 * @covers ::handle_bulk_request
	 */
	public function test_other_bulk_actions_pass_through() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $user_id );

		$this->assertSame( 'users.php', Admin::handle_bulk_request( 'users.php', 'another_plugin_action', array( $user_id ) ) );
	}

	/**
	 * The one-time self-destruct notice is kept for users who cannot manage the site.
	 *
	 * @covers ::admin_notices
	 */
	public function test_self_destruct_notice_is_kept_for_users_without_manage_options() {
		\set_current_screen( 'dashboard' );
		\update_option( 'activitypub_self_destruct_complete', true );

		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		\ob_start();
		Admin::admin_notices();
		$output = \ob_get_clean();

		$this->assertStringNotContainsString( 'Self-Destruct Complete', $output );
		$this->assertNotEmpty( \get_option( 'activitypub_self_destruct_complete' ) );

		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		\ob_start();
		Admin::admin_notices();
		$output = \ob_get_clean();

		$this->assertStringContainsString( 'Self-Destruct Complete', $output );
		$this->assertFalse( \get_option( 'activitypub_self_destruct_complete' ) );

		\set_current_screen( 'front' );
	}
}
