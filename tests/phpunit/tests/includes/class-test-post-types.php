<?php
/**
 * Test file for Post Types.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

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
}
