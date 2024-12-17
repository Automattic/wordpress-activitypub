<?php
/**
 * Test file for Activitypub Mention.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Mention;

/**
 * Test class for Activitypub Mention.
 *
 * @coversDefaultClass \Activitypub\Mention
 */
class Test_Mention extends \WP_UnitTestCase {

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
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
		add_filter( 'pre_http_request', array( $this, 'pre_http_request' ), 10, 3 );
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request' ) );
		parent::tear_down();
	}

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
		$this->assertEquals( $content_with_mention, Mention::the_content( $content ) );
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
	 * Mock HTTP requests.
	 *
	 * @param false|array|\WP_Error $response    HTTP response.
	 * @param array                 $parsed_args HTTP request arguments.
	 * @param string                $url         The request URL.
	 * @return array|false|\WP_Error
	 */
	public function pre_http_request( $response, $parsed_args, $url ) {
		// Mock responses for remote users.
		if ( false !== strpos( $url, 'notiz.blog' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'id'   => 'https://notiz.blog/author/matthias-pfefferle/',
						'url'  => 'https://notiz.blog/author/matthias-pfefferle/',
						'name' => 'pfefferle',
					)
				),
			);
		}
		if ( false !== strpos( $url, 'lemmy.ml' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'id'   => 'https://lemmy.ml/u/pfefferle',
						'url'  => 'https://lemmy.ml/u/pfefferle',
						'name' => 'pfefferle',
					)
				),
			);
		}

		return $response;
	}

	/**
	 * Filters remote metadata by actor.
	 *
	 * @param array|string $pre   The pre-filtered value.
	 * @param string       $actor The actor.
	 * @return array|string
	 */
	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		$actor = ltrim( $actor, '@' );

		if ( isset( self::$users[ $actor ] ) ) {
			return self::$users[ $actor ];
		}

		// Handle remote users.
		if ( 'pfefferle@notiz.blog' === $actor ) {
			return array(
				'id'   => 'https://notiz.blog/author/matthias-pfefferle/',
				'url'  => 'https://notiz.blog/author/matthias-pfefferle/',
				'name' => 'pfefferle',
			);
		}

		if ( 'pfefferle@lemmy.ml' === $actor ) {
			return array(
				'id'   => 'https://lemmy.ml/u/pfefferle',
				'url'  => 'https://lemmy.ml/u/pfefferle',
				'name' => 'pfefferle',
			);
		}

		return $pre;
	}
}
