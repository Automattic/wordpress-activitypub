<?php
/**
 * Test file for Extra Fields.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Extra_Fields;

/**
 * Test class for Extra Fields.
 *
 * @coversDefaultClass \Activitypub\Collection\Extra_Fields
 */
class Test_Extra_Fields extends \WP_UnitTestCase {

	/**
	 * Test the get_attachment.
	 *
	 * @covers ::get_attachment
	 */
	public function test_get_attachment() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_content' => 'https://wordpress.org/plugins/activitypub/',
				'post_title'   => 'ActivityPub',
			)
		);

		// Multiple calls should not result in multiple "me" values in rel attribute.
		Extra_Fields::fields_to_attachments( array( $post ) );
		Extra_Fields::fields_to_attachments( array( $post ) );
		$attachments = Extra_Fields::fields_to_attachments( array( $post ) );
		$value_count = array_count_values( $attachments[1]['rel'] );

		$this->assertEquals( 1, $value_count['me'] );
	}
}
