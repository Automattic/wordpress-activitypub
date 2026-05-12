<?php
/**
 * Test file for get_attachment_ap_id().
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Functions;

/**
 * Tests for get_attachment_ap_id().
 *
 * @group activitypub
 * @group functions
 */
class Test_Get_Attachment_AP_ID extends \WP_UnitTestCase {

	/**
	 * Test that a valid attachment ID returns the canonical AP URL.
	 */
	public function test_returns_rest_url_for_valid_attachment() {
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$id = \Activitypub\get_attachment_ap_id( $attachment_id );

		$this->assertNotEmpty( $id );
		$this->assertStringContainsString( '/activitypub/1.0/media/' . $attachment_id, $id );
	}

	/**
	 * Test that an invalid attachment ID returns false.
	 */
	public function test_returns_false_for_missing_attachment() {
		$this->assertFalse( \Activitypub\get_attachment_ap_id( 999999999 ) );
	}

	/**
	 * Test that a non-attachment post returns false.
	 */
	public function test_returns_false_for_non_attachment_post() {
		$post_id = self::factory()->post->create();
		$this->assertFalse( \Activitypub\get_attachment_ap_id( $post_id ) );
	}
}
