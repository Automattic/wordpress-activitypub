<?php
/**
 * Test file for Post Types.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activitypub;
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
	public function setUp(): void {
		parent::setUp();
		Activitypub::init();
	}

	/**
	 * Test prevent_empty_post_meta method.
	 *
	 * @covers ::prevent_empty_post_meta
	 */
	public function test_prevent_empty_post_meta() {
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );

		\update_post_meta( $post_id, 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS );
		$this->assertEmpty( \get_post_meta( $post_id, 'activitypub_max_image_attachments', true ) );
		\delete_post_meta( $post_id, 'activitypub_max_image_attachments' );

		\update_post_meta( $post_id, 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS + 3 );
		$this->assertEquals( ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS + 3, \get_post_meta( $post_id, 'activitypub_max_image_attachments', true ) );
		\delete_post_meta( $post_id, 'activitypub_max_image_attachments' );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_post_metadata method.
	 *
	 * @covers ::default_post_meta_data
	 */
	public function test_get_post_metadata() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 months' ) ), // Post older than a month.
			)
		);

		// Test 1: When meta_key is not 'activitypub_content_visibility', should return the original value.
		$result = Post_Types::default_post_meta_data( 'original_value', $post_id, 'some_other_key' );
		$this->assertEquals( 'original_value', $result, 'Should return original value for non-matching meta key.' );

		// Test 2: When post is federated, should return the original value.
		\update_post_meta( $post_id, 'activitypub_status', 'federated' );
		$result = Post_Types::default_post_meta_data( 'original_value', $post_id, 'activitypub_content_visibility' );
		$this->assertEquals( 'original_value', $result, 'Should return original value for federated posts.' );

		// Test 3: When post is not federated and older than a month, should return local visibility.
		\update_post_meta( $post_id, 'activitypub_status', 'pending' );
		$result = Post_Types::default_post_meta_data( null, $post_id, 'activitypub_content_visibility' );
		$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL, $result, 'Should return local visibility for old non-federated posts.' );

		// Test 4: When post is not federated but less than a month old, should return original value.
		$recent_post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 weeks' ) ), // Recent post.
			)
		);
		\update_post_meta( $recent_post_id, 'activitypub_status', 'pending' );
		$result = Post_Types::default_post_meta_data( null, $recent_post_id, 'activitypub_content_visibility' );
		$this->assertEquals( null, $result, 'Should return original value for recent non-federated posts.' );

		// Test 5: When meta value is already set (not null), should respect author's explicit choice.
		\update_post_meta( $post_id, 'activitypub_status', 'pending' ); // Ensure not federated.
		$result = Post_Types::default_post_meta_data( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, $post_id, 'activitypub_content_visibility' );
		$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, $result, 'Should respect explicitly set public visibility even for old unfederated posts.' );

		// Test 6: Only apply local visibility when meta value is null (no explicit setting).
		$result = Post_Types::default_post_meta_data( null, $post_id, 'activitypub_content_visibility' );
		$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL, $result, 'Should return local visibility when no explicit value is set for old unfederated posts.' );

		// Clean up.
		\wp_delete_post( $post_id, true );
		\wp_delete_post( $recent_post_id, true );
	}
}
