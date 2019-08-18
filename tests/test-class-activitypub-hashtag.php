<?php
class Test_Activitypub_Hashtag extends WP_UnitTestCase {
	public function setUp() {
		wp_create_tag( 'object' );
	}

	/**
	 * @dataProvider the_content_provider
	 */
	public function test_the_content( $content, $content_with_hashtag ) {
		$content = \Activitypub\Hashtag::the_content( $content );

		$this->assertEquals( $content_with_hashtag, $content );
	}

	public function the_content_provider() {
		$object = get_term_by( 'name', 'object', 'post_tag' );
		$link = get_term_link( $object, 'post_tag' );

		return array(
			array( 'test', 'test' ),
			array( '#test', '#test' ),
			array( 'hallo #test test', 'hallo #test test' ),
			array( 'hallo #object test', 'hallo <a rel="tag" class="u-tag u-category" href="' . $link . '">#object</a> test' ),
		);
	}
}
