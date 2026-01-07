<?php
/**
 * Test file for Activitypub Hashtag.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Remote_Actors;

/**
 * Test class for Activitypub Hashtag.
 *
 * @coversDefaultClass \Activitypub\Hashtag
 */
class Test_Hashtag extends \WP_UnitTestCase {
	/**
	 * Test the content.
	 *
	 * @dataProvider the_content_provider
	 * @covers ::the_content
	 *
	 * @param string $content The content.
	 * @param string $content_with_hashtag The content with hashtag.
	 */
	public function test_the_content( $content, $content_with_hashtag ) {
		\wp_create_term( 'object', 'post_tag' );
		\wp_create_term( 'touch', 'post_tag' );
		\wp_create_term( 'ccc', 'post_tag' );
		$object = \get_term_by( 'name', 'object', 'post_tag' );
		$link   = \get_term_link( $object, 'post_tag' );

		$content = \Activitypub\Hashtag::the_content( $content );

		$this->assertEquals( sprintf( $content_with_hashtag, $link ), $content );
	}

	/**
	 * The content provider.
	 *
	 * @return array[] The content.
	 */
	public function the_content_provider() {
		$code     = '<code>text with some #object and <a> tag inside</code>';
		$style    = <<<'ENDSTYLE'
<style type="text/css">
<![CDATA[
color: #ccc;
]]>
</style>
ENDSTYLE;
		$pre      = <<<'ENDPRE'
<pre>
Please don't #touch
  this.
</pre>
ENDPRE;
		$textarea = '<textarea name="test" rows="20">color: #ccc</textarea>';
		return array(
			array( 'test', 'test' ),
			array( '#test', '#test' ),
			array( 'hallo #test test', 'hallo #test test' ),
			array( 'hallo #object test', 'hallo <a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a> test' ),
			array( '#object test', '<a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a> test' ),
			array( 'hallo <a href="http://test.test/#object">test</a> test', 'hallo <a href="http://test.test/#object">test</a> test' ),
			array( 'hallo <a href="http://test.test/#object">#test</a> test', 'hallo <a href="http://test.test/#object">#test</a> test' ),
			array( '<div>hallo #object test</div>', '<div>hallo <a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a> test</div>' ),
			array( '<div>hallo #object</div>', '<div>hallo <a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a></div>' ),
			array( '<div>#object</div>', '<div><a rel="tag" class="hashtag u-tag u-category" href="%s">#object</a></div>' ),
			array( '<a>#object</a>', '<a>#object</a>' ),
			array( '<!-- #object -->', '<!-- #object -->' ),
			array( '<div style="color: #ccc;">object</a>', '<div style="color: #ccc;">object</a>' ),
			array( $code, $code ),
			array( $style, $style ),
			array( $textarea, $textarea ),
			array( $pre, $pre ),
		);
	}

	/**
	 * Tests auto-converting hashtags to tags.
	 *
	 * @see https://github.com/Automattic/wordpress-activitypub/issues/955
	 * @dataProvider hashtag_provider
	 * @covers ::insert_post
	 *
	 * @param string   $content       The post content.
	 * @param string   $excerpt       The post excerpt.
	 * @param string[] $expected_tags The expected tags.
	 * @param string   $message       The error message.
	 */
	public function test_hashtag_conversion( $content, $excerpt, $expected_tags, $message ) {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_author'  => 1,
			)
		);

		\Activitypub\Hashtag::insert_post( $post_id, get_post( $post_id ) );
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		foreach ( $expected_tags as $tag ) {
			$this->assertContains( $tag, $tags, $message );
		}
	}

	/**
	 * Test no hashtags for unsupported post types.
	 *
	 * @covers ::insert_post
	 */
	public function test_no_hashtags_for_unsupported_post_types() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'Testing #php and #programming',
				'post_type'    => Remote_Actors::POST_TYPE,
			)
		);

		\Activitypub\Hashtag::insert_post( $post_id, get_post( $post_id ) );
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		$this->assertEmpty( $tags, 'Should not add hashtags to unsupported post types' );
	}

	/**
	 * Data provider for hashtag tests.
	 *
	 * @return array[] The data.
	 */
	public function hashtag_provider() {
		return array(
			'basic_hashtags'         => array(
				'Testing #php and #programming',
				'',
				array( 'php', 'programming' ),
				'Basic hashtags should be converted',
			),
			'hashtags_in_attributes' => array(
				'<div style="color: #fff">#validtag</div>',
				'',
				array( 'validtag' ),
				'Hashtags in HTML attributes should be ignored',
			),
			'mixed_content'          => array(
				'Color is #red <span style="color: #ff0000">#valid</span> #blue',
				'',
				array( 'red', 'blue', 'valid' ),
				'Should handle mixed content correctly',
			),
			'hex_in_text'            => array(
				'<span style="color: #ff0000">#f00</span> #fff #000000',
				'',
				array( 'f00', 'fff', '000000' ),
				'Hex colors in text should be converted',
			),
			'excerpt_tags'           => array(
				'',
				'Testing #excerpt with #tags',
				array( 'excerpt', 'tags' ),
				'Should process excerpt hashtags',
			),
			'multiple_attributes'    => array(
				'<div data-color="#123" style="border: 1px solid #456">#valid</div>',
				'',
				array( 'valid' ),
				'Should ignore multiple attribute hashtags',
			),
			'quotes_in_content'      => array(
				'Here is a "#quoted" #tag',
				'',
				array( 'tag' ),
				'Should handle quotes in content correctly',
			),
		);
	}

	/**
	 * Test that hashtag filters are added when the setting is enabled.
	 *
	 * @covers ::init
	 */
	public function test_init_adds_filters_when_enabled() {
		// Remove any existing filters first.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\remove_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );

		// Enable the hashtag setting.
		\update_option( 'activitypub_use_hashtags', '1' );

		// Call init.
		\Activitypub\Hashtag::init();

		// Verify filters were added.
		$this->assertNotFalse(
			\has_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) ),
			'insert_post action should be added when hashtags are enabled'
		);
		$this->assertNotFalse(
			\has_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) ),
			'the_content filter should be added when hashtags are enabled'
		);
		$this->assertNotFalse(
			\has_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ) ),
			'filter_activity_object filter should be added when hashtags are enabled'
		);

		// Clean up.
		\delete_option( 'activitypub_use_hashtags' );
	}

	/**
	 * Test that hashtag filters are not added when the setting is disabled.
	 *
	 * @covers ::init
	 */
	public function test_init_does_not_add_filters_when_disabled() {
		// Remove any existing filters first.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\remove_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );

		// Disable the hashtag setting (default).
		\update_option( 'activitypub_use_hashtags', '0' );

		// Call init.
		\Activitypub\Hashtag::init();

		// Verify filters were NOT added.
		$this->assertFalse(
			\has_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) ),
			'insert_post action should not be added when hashtags are disabled'
		);
		$this->assertFalse(
			\has_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) ),
			'the_content filter should not be added when hashtags are disabled'
		);
		$this->assertFalse(
			\has_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ) ),
			'filter_activity_object filter should not be added when hashtags are disabled'
		);

		// Clean up.
		\delete_option( 'activitypub_use_hashtags' );
	}

	/**
	 * Test that hashtag filters work with boolean-like option values.
	 *
	 * WordPress checkboxes can store '1', 1, true, or '0', 0, false, ''.
	 * This test ensures the setting works correctly with string '1'.
	 *
	 * @covers ::init
	 */
	public function test_init_with_string_option_value() {
		// Remove any existing filters first.
		\remove_action( 'wp_insert_post', array( \Activitypub\Hashtag::class, 'insert_post' ) );
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\remove_filter( 'activitypub_activity_object_array', array( \Activitypub\Hashtag::class, 'filter_activity_object' ), 99 );

		// Test with string '1' (typical checkbox value).
		\update_option( 'activitypub_use_hashtags', '1' );
		\Activitypub\Hashtag::init();

		$this->assertNotFalse(
			\has_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) ),
			'Hashtag feature should be enabled with string "1"'
		);

		// Clean up.
		\remove_filter( 'the_content', array( \Activitypub\Hashtag::class, 'the_content' ) );
		\delete_option( 'activitypub_use_hashtags' );
	}
}
