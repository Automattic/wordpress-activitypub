<?php
/**
 * Test file for Activitypub Hashtag.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub Hashtag.
 *
 * @coversDefaultClass \Activitypub\Hashtag
 */
class Test_Activitypub_Hashtag extends WP_UnitTestCase {
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
}
