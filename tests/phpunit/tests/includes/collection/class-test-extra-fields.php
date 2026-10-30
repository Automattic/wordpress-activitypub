<?php
/**
 * Test file for Extra Fields.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Tests\Equal_Html;

/**
 * Test class for Extra Fields.
 *
 * @coversDefaultClass \Activitypub\Collection\Extra_Fields
 */
class Test_Extra_Fields extends \WP_UnitTestCase {
	use Equal_Html;

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
		// wptexturize() curls the quotes when the stored content holds them literally, which WordPress 7.2's wp_kses() does.
		$this->assertMatchesRegularExpression( '/["\x{201C}]quotes["\x{201D}]/u', $attachments[0]['value'] );
		$this->assertStringNotContainsString( '&quot;', $attachments[0]['value'] );
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

		// Seeding the blog actor records a site option, which would otherwise outlive this test.
		\delete_option( 'activitypub_default_extra_fields' );

		$this->assertContains( 'Powered by', $titles );
	}

	/**
	 * The "Powered by" default keeps the plain text the migration used to write.
	 *
	 * Every other default is a bare URL the loop linkifies. This one is not a URL, and its value
	 * has to stay byte-identical to what `Migration::add_default_extra_field()` produced, so no
	 * site sees its profile change.
	 *
	 * @covers ::default_actor_extra_fields
	 */
	public function test_powered_by_keeps_the_plain_text_it_always_had() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$fields  = Extra_Fields::default_actor_extra_fields( array(), $user_id );
		$content = '';

		foreach ( $fields as $field ) {
			if ( 'Powered by' === $field->post_title ) {
				$content = $field->post_content;
			}
		}

		$this->assertStringContainsString( 'WordPress', $content );
		$this->assertStringNotContainsString( '<a ', $content, 'It was never a link, and must not become one.' );
	}

	/**
	 * A field that holds a single link federates it with the URL as its text, so Mastodon can verify it.
	 *
	 * @dataProvider single_link_provider
	 * @covers ::fields_to_attachments
	 *
	 * @param string $content The field content.
	 */
	public function test_single_link_field_is_verifiable( $content ) {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_content' => $content,
				'post_title'   => 'Blog',
			)
		);

		$attachments = Extra_Fields::fields_to_attachments( array( $post ) );

		$this->assert_equal_html( '<a href="https://notiz.example/" target="_blank" rel="nofollow noopener noreferrer me" translate="no"><span class="invisible">https://</span><span class="">notiz.example/</span><span class="invisible"></span></a>', $attachments[0]['value'] );
		$this->assertSame( 'https://notiz.example/', \wp_strip_all_tags( $attachments[0]['value'] ), 'The text of the link is its URL.' );
		$this->assertSame( 'https://notiz.example/', $attachments[1]['href'] );
	}

	/**
	 * Fields that hold a single link.
	 *
	 * @return array[] The field content.
	 */
	public function single_link_provider() {
		return array(
			'plain url'          => array( 'https://notiz.example/' ),
			'link with a label'  => array( '<a href="https://notiz.example/">notiz.example</a>' ),
			'link in paragraphs' => array( '<!-- wp:paragraph --><p><a href="https://notiz.example/" rel="me">notiz.example</a></p><!-- /wp:paragraph --><!-- wp:paragraph --><p></p><!-- /wp:paragraph -->' ),
		);
	}

	/**
	 * A field that is not a single web link keeps its content.
	 *
	 * @dataProvider not_a_single_link_provider
	 * @covers ::fields_to_attachments
	 *
	 * @param string $content  The field content.
	 * @param string $expected The text the value keeps.
	 */
	public function test_other_field_keeps_its_value( $content, $expected ) {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_content' => $content,
				'post_title'   => 'Field',
			)
		);

		$attachments = Extra_Fields::fields_to_attachments( array( $post ) );

		$this->assertStringContainsString( $expected, $attachments[0]['value'] );
		$this->assertStringNotContainsString( 'invisible', $attachments[0]['value'] );
	}

	/**
	 * Fields that are not a single web link.
	 *
	 * @return array[] The field content and the text it keeps.
	 */
	public function not_a_single_link_provider() {
		return array(
			'text around a link'  => array( 'Visit <a href="https://notiz.example/">notiz.example</a> now', 'Visit' ),
			'mail link'           => array( '<a href="mailto:me@notiz.example">Mail</a>', 'mailto:me@notiz.example' ),
			'plain text'          => array( 'Just text', 'Just text' ),
			'link without href'   => array( '<a>Anchor</a>', 'Anchor' ),
			'url text would lose' => array( '<a href="https://notiz.example/?">notiz.example</a>', 'notiz.example' ),
		);
	}
}
