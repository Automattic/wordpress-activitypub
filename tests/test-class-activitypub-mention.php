<?php
class Test_Activitypub_Mention extends ActivityPub_TestCase_Cache_HTTP {
	public static $users = array(
		'username@example.org' => array(
			'url'  => 'https://example.org/users/username',
			'name' => 'username',
		),
	);
	/**
	 * @dataProvider the_content_provider
	 */
	public function test_the_content( $content, $content_with_mention ) {
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
		$content = \Activitypub\Mention::the_content( $content );
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );

		$this->assertEquals( $content_with_mention, $content );
	}

	public function the_content_provider() {
		$code = 'hallo <code>@username@example.org</code> test';
		$pre = <<<ENDPRE
<pre>
Please don't mention @username@example.org
  here.
</pre>
ENDPRE;
		return array(
			array( 'hallo @username@example.org test', 'hallo <a rel="mention" class="u-url mention" href="https://example.org/users/username">@<span>username</span></a> test' ),
			array( 'hallo @pfefferle@notiz.blog test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@<span>pfefferle</span></a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@<span>pfefferle</span>@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@<span>pfefferle</span>@notiz.blog</a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle@notiz.blog</a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/@pfefferle/">@pfefferle@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/@pfefferle/">@pfefferle@notiz.blog</a> test' ),
			array( $code, $code ),
			array( $pre, $pre ),
		);
	}

	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		$actor = ltrim( $actor, '@' );
		if ( isset( self::$users[ $actor ] ) ) {
			return self::$users[ $actor ];
		}
		return $pre;
	}
}
