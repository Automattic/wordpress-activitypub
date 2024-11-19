<?php
/**
 * Test file for Activitypub Mention.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub Mention.
 *
 * @coversDefaultClass \Activitypub\Mention
 */
class Test_Activitypub_Mention extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Users.
	 *
	 * @var array
	 */
	public static $users = array(
		'username@example.org' => array(
			'id'   => 'https://example.org/users/username',
			'url'  => 'https://example.org/users/username',
			'name' => 'username',
		),
	);

	/**
	 * Test the content.
	 *
	 * @dataProvider the_content_provider
	 * @covers ::the_content
	 *
	 * @param string $content The content.
	 * @param string $content_with_mention The content with mention.
	 */
	public function test_the_content( $content, $content_with_mention ) {
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
		$content = \Activitypub\Mention::the_content( $content );
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );

		$this->assertEquals( $content_with_mention, $content );
	}

	/**
	 * The content provider.
	 *
	 * @return array[] The content.
	 */
	public function the_content_provider() {
		$code = 'hallo <code>@username@example.org</code> test';
		$pre  = <<<ENDPRE
<pre>
Please don't mention @username@example.org
  here.
</pre>
ENDPRE;
		return array(
			array( 'hallo @username@example.org @pfefferle@notiz.blog test', 'hallo <a rel="mention" class="u-url mention" href="https://example.org/users/username">@<span>username</span></a> <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@<span>pfefferle</span></a> test' ),
			array( 'hallo @username@example.org @username@example.org test', 'hallo <a rel="mention" class="u-url mention" href="https://example.org/users/username">@<span>username</span></a> <a rel="mention" class="u-url mention" href="https://example.org/users/username">@<span>username</span></a> test' ),
			array( 'hallo @username@example.com @username@example.com test', 'hallo @username@example.com @username@example.com test' ),
			array( 'Hallo @pfefferle@lemmy.ml test', 'Hallo <a rel="mention" class="u-url mention" href="https://lemmy.ml/u/pfefferle">@<span>pfefferle</span></a> test' ),
			array( 'hallo @username@example.org test', 'hallo <a rel="mention" class="u-url mention" href="https://example.org/users/username">@<span>username</span></a> test' ),
			array( 'hallo @pfefferle@notiz.blog test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@<span>pfefferle</span></a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@<span>pfefferle</span>@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@<span>pfefferle</span>@notiz.blog</a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle@notiz.blog</a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/@pfefferle/">@pfefferle@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/@pfefferle/">@pfefferle@notiz.blog</a> test' ),
			array( 'hallo <img src="abc" alt="https://notiz.blog/@pfefferle/" title="@pfefferle@notiz.blog"/> test', 'hallo <img src="abc" alt="https://notiz.blog/@pfefferle/" title="@pfefferle@notiz.blog"/> test' ),
			array( '<!-- @pfefferle@notiz.blog -->', '<!-- @pfefferle@notiz.blog -->' ),
			array( $code, $code ),
			array( $pre, $pre ),
		);
	}

	/**
	 * Filter for get_remote_metadata_by_actor.
	 *
	 * @param string $pre The pre.
	 * @param string $actor The actor.
	 * @return array
	 */
	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		$actor = ltrim( $actor, '@' );
		if ( isset( self::$users[ $actor ] ) ) {
			return self::$users[ $actor ];
		}
		return $pre;
	}
}
