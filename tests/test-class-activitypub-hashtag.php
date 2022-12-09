<?php
class Test_Activitypub_Hashtag extends WP_UnitTestCase {
	/**
	 * @dataProvider the_content_provider
	 */
	public function test_the_content( $content, $content_with_hashtag ) {
		\wp_create_term( 'object', 'post_tag' );
		$object = \get_term_by( 'name', 'object', 'post_tag' );
		$link = \get_term_link( $object, 'post_tag' );

		$content = \Activitypub\Hashtag::the_content( $content );

		$this->assertEquals( sprintf( $content_with_hashtag, $link ), $content );
	}

	public function the_content_provider() {
		return array(
			array( 'test', 'test' ),
			array( '#test', '#test' ),
			array( 'hallo #test test', 'hallo #test test' ),
			array( 'hallo #object test', 'hallo <a rel="tag" class="u-tag u-category" href="%s">#object</a> test' ),
			array( '#object test', '<a rel="tag" class="u-tag u-category" href="%s">#object</a> test' ),
			array( 'hallo <a href="http://test.test/#object">test</a> test', 'hallo <a href="http://test.test/#object">test</a> test' ),
			array( 'hallo <a href="http://test.test/#object">#test</a> test', 'hallo <a href="http://test.test/#object">#test</a> test' ),
			array( '<div>hallo #object test</div>', '<div>hallo <a rel="tag" class="u-tag u-category" href="%s">#object</a> test</div>' ),
			array( '<div>hallo #object</div>', '<div>hallo <a rel="tag" class="u-tag u-category" href="%s">#object</a></div>' ),
			array( '<div>#object</div>', '<div>#object</div>' ),
			array( '<a>#object</a>', '<a>#object</a>' ),
			array( '<div style="color: #ccc;">object</a>', '<div style="color: #ccc;">object</a>' ),
		);
	}
}
