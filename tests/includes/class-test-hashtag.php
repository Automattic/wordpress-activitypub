<?php
/**
 * Test file for Activitypub Hashtag.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

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
		$style    = <<<ENDSTYLE
<style type="text/css">
<![CDATA[
color: #ccc;
]]>
</style>
ENDSTYLE;
		$pre      = <<<ENDPRE
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
		$post_id = $this->factory->post->create(
			array(
				'post_content' => $content,
				'post_excerpt' => $excerpt,
			)
		);

		\Activitypub\Hashtag::insert_post( $post_id, get_post( $post_id ) );
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		foreach ( $expected_tags as $tag ) {
			$this->assertContains( $tag, $tags, $message );
		}
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
}
