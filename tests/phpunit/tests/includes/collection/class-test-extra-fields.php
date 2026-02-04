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
	 * Test that pre-linked URLs get rel="me" added to both PropertyValue and Link.
	 *
	 * When users create extra fields in the block editor, URLs are often
	 * already wrapped in <a> tags without rel="me". This test ensures
	 * verification works by always adding "me" to the rel attribute in both
	 * the PropertyValue HTML and the Link attachment.
	 *
	 * @covers ::fields_to_attachments
	 * @covers ::add_rel_me_to_links
	 */
	public function test_prelinked_url_gets_rel_me() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_content' => '<a href="https://mastodon.social/@user">Mastodon</a>',
				'post_title'   => 'Mastodon',
			)
		);

		$attachments = Extra_Fields::fields_to_attachments( array( $post ) );

		// The PropertyValue HTML should have rel="me" for verification.
		$this->assertEquals( 'PropertyValue', $attachments[0]['type'] );
		$this->assertStringContainsString( 'rel="me"', $attachments[0]['value'] );

		// The Link attachment should also have rel="me".
		$this->assertEquals( 'Link', $attachments[1]['type'] );
		$this->assertEquals( 'https://mastodon.social/@user', $attachments[1]['href'] );
		$this->assertContains( 'me', $attachments[1]['rel'] );
	}

	/**
	 * Test that pre-linked URLs with existing rel attributes get "me" added.
	 *
	 * @covers ::fields_to_attachments
	 * @covers ::add_rel_me_to_links
	 */
	public function test_prelinked_url_with_rel_gets_me_added() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_content' => '<a href="https://example.com" rel="nofollow noopener">Example</a>',
				'post_title'   => 'Example',
			)
		);

		$attachments = Extra_Fields::fields_to_attachments( array( $post ) );

		// The PropertyValue HTML should have rel with "me" added.
		$this->assertEquals( 'PropertyValue', $attachments[0]['type'] );
		$this->assertMatchesRegularExpression( '/rel="[^"]*me[^"]*"/', $attachments[0]['value'] );
		$this->assertStringContainsString( 'nofollow', $attachments[0]['value'] );

		// The Link attachment should have original rel values plus "me".
		$this->assertEquals( 'Link', $attachments[1]['type'] );
		$this->assertContains( 'nofollow', $attachments[1]['rel'] );
		$this->assertContains( 'noopener', $attachments[1]['rel'] );
		$this->assertContains( 'me', $attachments[1]['rel'] );
	}

	/**
	 * Test that pre-linked URLs with existing rel="me" don't get duplicate.
	 *
	 * @covers ::fields_to_attachments
	 * @covers ::add_rel_me_to_links
	 */
	public function test_prelinked_url_with_rel_me_no_duplicate() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_content' => '<a href="https://example.com" rel="me nofollow">Example</a>',
				'post_title'   => 'Example',
			)
		);

		$attachments = Extra_Fields::fields_to_attachments( array( $post ) );

		// PropertyValue should have "me" exactly once in the HTML.
		$this->assertEquals( 1, \substr_count( $attachments[0]['value'], ' me' ) + \substr_count( $attachments[0]['value'], '"me' ) );

		// Link attachment should have "me" exactly once.
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
