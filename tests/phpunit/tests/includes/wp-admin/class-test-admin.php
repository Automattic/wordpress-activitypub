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
}
