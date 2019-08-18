<?php
class Test_Activitypub_Hashtag extends WP_UnitTestCase {
	/**
	 * @dataProvider the_content_provider
	 */
	public function test_the_content( $content, $content_with_hashtag ) {
		$content = \Activitypub\Hashtag::the_content( $content );

		$this->assertEquals( $content_with_hashtag, $content );
	}

	public function the_content_provider() {
		return [
			[ 'test', 'test' ],
			[ '#test', '#test' ],
			[ 'hallo #test test', 'hallo #test test' ],
		];
	}
}
