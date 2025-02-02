<?php
/**
 * Test file for Activitypub Blog.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Model;

use Activitypub\Collection\Extra_Fields;
use Activitypub\Model\Blog;

/**
 * Test class for Activitypub Blog.
 *
 * @coversDefaultClass \Activitypub\Model\Blog
 */
class Test_Blog extends \WP_UnitTestCase {

	/**
	 * Test the get_attachment.
	 *
	 * @covers ::get_attachment
	 */
	public function test_get_attachment() {
		self::factory()->post->create(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_content' => 'https://wordpress.org/plugins/activitypub/',
				'post_title'   => 'ActivityPub',
			)
		);

		// Multiple calls should not result in multiple "me" values in rel attribute.
		$user = new Blog();
		$user->get_attachment();
		$user->get_attachment();
		$attachments = $user->get_attachment();
		$value_count = array_count_values( $attachments[1]['rel'] );

		$this->assertEquals( 1, $value_count['me'] );
	}
}
