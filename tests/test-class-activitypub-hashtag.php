<?php
class Test_Activitypub_Hashtag extends WP_UnitTestCase {
	protected function setUp() {
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
		$object_link = get_tag_link( 'object' );

		return array(
			array( 'test', 'test' ),
			array( '#test', '#test' ),
			array( 'hallo #test test', 'hallo #test test' ),
			array( 'hallo #object test', 'hallo <a rel="tag" class="u-tag u-category" href="' . $object_link . '">#object</a> test' ),
		);
	}
}
