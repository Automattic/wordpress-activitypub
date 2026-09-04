<?php
/**
 * Test file for Extra Fields.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Actors;
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

	/**
	 * The default fields include the "Powered by" entry.
	 *
	 * It used to be seeded separately by `Migration::add_default_extra_field()`, which ran before
	 * the post types were registered and recorded no flag, so it duplicated on a replayed
	 * migration and reappeared after deletion. Seeding it here instead means it inherits the
	 * empty-list check, the flag, and the timing that everything else already had.
	 *
	 * @covers ::default_actor_extra_fields
	 */
	public function test_default_fields_include_powered_by() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$fields = Extra_Fields::default_actor_extra_fields( array(), $user_id );
		$titles = \wp_list_pluck( $fields, 'post_title' );

		$this->assertContains( 'Powered by', $titles );
	}

	/**
	 * The blog actor gets it too, as it did when migration seeded it.
	 *
	 * @covers ::default_actor_extra_fields
	 */
	public function test_blog_default_fields_include_powered_by() {
		$fields = Extra_Fields::default_actor_extra_fields( array(), Actors::BLOG_USER_ID );
		$titles = \wp_list_pluck( $fields, 'post_title' );

		$this->assertContains( 'Powered by', $titles );
	}

	/**
	 * The "Powered by" default keeps its label and still resolves to a link.
	 *
	 * Every other default is a bare URL the loop linkifies, which would render this one as the
	 * shortened host. It also has to be a single anchor, or `fields_to_attachments()` emits a
	 * `Note`, which the actor schema does not allow.
	 *
	 * @covers ::default_actor_extra_fields
	 */
	public function test_a_non_url_default_is_not_turned_into_a_link() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$fields  = Extra_Fields::default_actor_extra_fields( array(), $user_id );
		$content = '';

		foreach ( $fields as $field ) {
			if ( 'Powered by' === $field->post_title ) {
				$content = $field->post_content;
			}
		}

		$this->assertStringContainsString( 'WordPress', $content, 'The label stays "WordPress".' );
		$this->assertStringContainsString( 'wordpress.org', $content, 'And it points at wordpress.org.' );
	}
}
