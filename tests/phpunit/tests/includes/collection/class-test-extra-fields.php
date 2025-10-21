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
	 * @covers ::fields_to_attachments
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

	/**
	 * Test that HTML entities are decoded in field names and values.
	 *
	 * @covers ::fields_to_attachments
	 */
	public function test_html_entities_decoded() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_content' => 'Test content with &quot;quotes&quot; and &amp; ampersands',
				'post_title'   => 'Void&#8217;s Profile',
			)
		);

		$attachments = Extra_Fields::fields_to_attachments( array( $post ) );

		// Check PropertyValue has decoded entities in both name and value.
		$this->assertEquals( 'PropertyValue', $attachments[0]['type'] );
		// WordPress converts the HTML entity &#8217; to the UTF-8 right single quotation mark character.
		$expected_name = "Void\u{2019}s Profile";
		$this->assertEquals( $expected_name, $attachments[0]['name'] );
		$this->assertStringContainsString( '"quotes"', $attachments[0]['value'] );
		$this->assertStringContainsString( '& ampersands', $attachments[0]['value'] );
	}
}
