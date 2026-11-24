<?php
/**
 * Test file for Admin.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;
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
	 * A bulk action sent from the users screen runs in the request that carries its nonce.
	 *
	 * Replays users.php: the list form sends `_wpnonce` and `_wp_http_referer`. For an action
	 * it does not know, core redirects without both when `$_GET['_wp_http_referer']` is set,
	 * otherwise it passes `wp_get_referer()` to the `handle_bulk_actions-users` filter.
	 *
	 * @covers ::keep_bulk_nonce
	 * @covers ::handle_bulk_request
	 */
	public function test_bulk_action_from_users_screen_keeps_its_nonce() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $admin_id );

		$original_get           = $_GET; // phpcs:ignore WordPress.Security.NonceVerification -- Remembered only to be put back.
		$original_request       = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification -- Remembered only to be put back.
		$request_uri            = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Remembered only to be put back.
		$_SERVER['REQUEST_URI'] = '/wp-admin/users.php?action=add_activitypub_cap';

		$form = array(
			'action'           => 'add_activitypub_cap',
			'users'            => array( $user_id ),
			'_wpnonce'         => \wp_create_nonce( 'bulk-users' ),
			'_wp_http_referer' => '/wp-admin/users.php?role=subscriber',
		);

		try {
			$_GET     = $form;
			$_REQUEST = $form;

			Admin::keep_bulk_nonce();

			// With the field set, users.php would redirect without the nonce.
			$this->assertArrayNotHasKey( '_wp_http_referer', $_GET ); // phpcs:ignore WordPress.Security.NonceVerification

			$send_back = Admin::handle_bulk_request( \wp_get_referer(), 'add_activitypub_cap', array( $user_id ) );

			$this->assertSame( '/wp-admin/users.php?role=subscriber', $send_back );
			$this->assertTrue( \user_can( $user_id, 'activitypub' ) );

			// Bulk actions of other plugins keep the redirect of core.
			$_GET     = \array_merge( $form, array( 'action' => 'another_plugin_action' ) );
			$_REQUEST = $_GET; // phpcs:ignore WordPress.Security.NonceVerification

			Admin::keep_bulk_nonce();

			$this->assertArrayHasKey( '_wp_http_referer', $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		} finally {
			$_GET     = $original_get;
			$_REQUEST = $original_request;

			if ( null === $request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $request_uri;
			}
		}
	}

	/**
	 * Confirming the removal requires the same capability as the bulk action that leads to it.
	 *
	 * @covers ::handle_bulk_actor_delete_confirmation
	 */
	public function test_bulk_delete_confirmation_requires_promote_users() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		\get_userdata( $user_id )->add_cap( 'edit_users' );
		\wp_set_current_user( $user_id );

		$_POST['_wpnonce'] = \wp_create_nonce( 'bulk-users' );

		$redirect = static function () {
			throw new \Exception( 'redirect' );
		};
		\add_filter( 'wp_redirect', $redirect );

		try {
			Admin::handle_bulk_actor_delete_confirmation();
			$result = 'returned';
		} catch ( \WPDieException $e ) {
			$result = 'died';
		} catch ( \Exception $e ) {
			$result = $e->getMessage();
		} finally {
			\remove_filter( 'wp_redirect', $redirect );
			unset( $_POST['_wpnonce'] );
		}

		$this->assertSame( 'died', $result );
	}

	/**
	 * Only the users of the removal that no longer publish to the Fediverse get a Delete.
	 *
	 * @covers ::process_capability_removal
	 */
	public function test_capability_removal_deletes_only_disabled_selected_users() {
		$disabled = self::factory()->user->create( array( 'role' => 'author' ) );
		$enabled  = self::factory()->user->create( array( 'role' => 'author' ) );
		$other    = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_userdata( $enabled )->add_cap( 'activitypub' );

		// As after the bulk action, which takes the capability away from the selected users.
		\get_userdata( $disabled )->remove_cap( 'activitypub' );
		\get_userdata( $other )->remove_cap( 'activitypub' );

		Admin::process_capability_removal( array( $disabled, $enabled ), array( $disabled, $enabled, $other ), 'users.php' );

		$deletes = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'any',
				'fields'      => 'ids',
				'author__in'  => array( $disabled, $enabled, $other ),
				'meta_key'    => '_activitypub_activity_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'Delete', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$this->assertSame( array( $disabled ), \array_map( 'intval', \wp_list_pluck( \array_map( 'get_post', $deletes ), 'post_author' ) ) );
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
