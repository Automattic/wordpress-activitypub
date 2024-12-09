<?php
/**
 * Test file for Activitypub Link.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Activitypub Link.
 *
 * @coversDefaultClass \Activitypub\Link
 */
class Test_Link extends \WP_UnitTestCase {
	/**
	 * Test the content.
	 *
	 * @dataProvider the_content_provider
	 * @covers ::the_content
	 *
	 * @param string $content The content.
	 *  @param string $content_with_hashtag The content with hashtag.
	 */
	public function test_the_content( $content, $content_with_hashtag ) {
		$content = \Activitypub\Link::the_content( $content );

		$this->assertEquals( $content_with_hashtag, $content );
	}

	/**
	 * The content provider.
	 *
	 * @return array[] The content.
	 */
	public function the_content_provider() {
		$code     = '<code>text with some https://test.de and <a> tag inside</code>';
		$style    = <<<ENDSTYLE
<style type="text/css">
<![CDATA[
color: #ccc;
]]>
</style>
ENDSTYLE;
		$pre      = <<<ENDPRE
<pre>
Please don't https://test.de
  this.
</pre>
ENDPRE;
		$textarea = '<textarea name="test" rows="20">color: #ccc</textarea>';
		$a_href   = '<a href="https://test.de">Text</a>';
		return array(
			array( 'https://wordpress.org/plugins/activitypub/', '<a href="https://wordpress.org/plugins/activitypub/" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://</span><span class="ellipsis">wordpress.org/plugins/activity</span><span class="invisible">pub/</span></a>' ),
			array( 'http://wordpress.org/', '<a href="http://wordpress.org/" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">http://</span><span class="">wordpress.org/</span><span class="invisible"></span></a>' ),
			array( 'https://test.org/?get=täst', '<a href="https://test.org/?get=täst" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://</span><span class="">test.org/?get=täst</span><span class="invisible"></span></a>' ),
			array( 'https://täst.org/', '<a href="https://täst.org/" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://</span><span class="">täst.org/</span><span class="invisible"></span></a>' ),
			array( 'https://wordpress.org/index.html', '<a href="https://wordpress.org/index.html" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://</span><span class="">wordpress.org/index.html</span><span class="invisible"></span></a>' ),
			array( 'ftp://test.de/', 'ftp://test.de/' ),
			array( 'hello https://www.test.de world https://wordpress.org/ test', 'hello <a href="https://www.test.de" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://www.</span><span class="">test.de</span><span class="invisible"></span></a> world <a href="https://wordpress.org/" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://</span><span class="">wordpress.org/</span><span class="invisible"></span></a> test' ),
			array( 'hello https://www.test.de test', 'hello <a href="https://www.test.de" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://www.</span><span class="">test.de</span><span class="invisible"></span></a> test' ),
			array( 'hello www.test.de test', 'hello <a href="https://www.test.de" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://www.</span><span class="">test.de</span><span class="invisible"></span></a> test' ),
			array( 'hello https://test:test@test.de test', 'hello <a href="https://test:test@test.de" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://test:test@</span><span class="">test.de</span><span class="invisible"></span></a> test' ),
			array( 'wwwfoocom', 'wwwfoocom' ),
			array( 'www.foo.com', '<a href="https://www.foo.com" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">https://www.</span><span class="">foo.com</span><span class="invisible"></span></a>' ),
			array( $code, $code ),
			array( $style, $style ),
			array( $textarea, $textarea ),
			array( $pre, $pre ),
			array( $a_href, $a_href ),
		);
	}
}
