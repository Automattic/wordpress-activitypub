<?php
/**
 * Test file for Activitypub Mention.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Mention;

use function Activitypub\object_to_uri;

/**
 * Test class for Activitypub Mention.
 *
 * @coversDefaultClass \Activitypub\Mention
 */
class Test_Mention extends \WP_UnitTestCase {

	/**
	 * Actors.
	 *
	 * @var array[]
	 */
	public static $actors = array(
		'username@example.org'  => array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'id'                => 'https://example.org/users/username',
			'type'              => 'Person',
			'url'               => 'https://example.org/users/username',
			'name'              => 'username',
			'preferredUsername' => 'username',
			'inbox'             => 'https://example.org/users/username/inbox',
		),
		'ελπίδα@ఉదాహరణ.example' => array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'id'                => 'https://xn--noc6ci4b4b3a.example/users/elpida',
			'type'              => 'Person',
			'url'               => 'https://xn--noc6ci4b4b3a.example/@elpida',
			'name'              => 'ελπίδα',
			'preferredUsername' => 'ελπίδα',
			'inbox'             => 'https://xn--noc6ci4b4b3a.example/users/elpida/inbox',
		),
	);

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'pre_http_request' ), 10, 3 );
		add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'activitypub_pre_http_get_remote_object' ), 10, 2 );
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request' ) );
		remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'activitypub_pre_http_get_remote_object' ) );
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
		$pre  = <<<'ENDPRE'
<pre>
Please don't mention @username@example.org
  here.
</pre>
ENDPRE;
		return array(
			array( 'hallo @username@example.org @pfefferle@notiz.blog test', 'hallo <a rel="mention" class="u-url mention" href="https://example.org/users/username">@username</a> <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle</a> test' ),
			array( 'hallo @username@example.org @username@example.org test', 'hallo <a rel="mention" class="u-url mention" href="https://example.org/users/username">@username</a> <a rel="mention" class="u-url mention" href="https://example.org/users/username">@username</a> test' ),
			array( 'hallo @username@example.com @username@example.com test', 'hallo @username@example.com @username@example.com test' ),
			array( 'Hallo @pfefferle@lemmy.ml test', 'Hallo <a rel="mention" class="u-url mention" href="https://lemmy.ml/u/pfefferle">@pfefferle</a> test' ),
			array( 'hallo @username@example.org test', 'hallo <a rel="mention" class="u-url mention" href="https://example.org/users/username">@username</a> test' ),
			array( 'hallo @pfefferle@notiz.blog test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle</a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle@notiz.blog</a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/author/matthias-pfefferle/">@pfefferle@notiz.blog</a> test' ),
			array( 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/@pfefferle/">@pfefferle@notiz.blog</a> test', 'hallo <a rel="mention" class="u-url mention" href="https://notiz.blog/@pfefferle/">@pfefferle@notiz.blog</a> test' ),
			array( 'hallo <img src="abc" alt="https://notiz.blog/@pfefferle/" title="@pfefferle@notiz.blog"/> test', 'hallo <img src="abc" alt="https://notiz.blog/@pfefferle/" title="@pfefferle@notiz.blog"/> test' ),
			array( '<!-- @pfefferle@notiz.blog -->', '<!-- @pfefferle@notiz.blog -->' ),
			array( $code, $code ),
			array( $pre, $pre ),
			array( 'hallo @ελπίδα@ఉదాహరణ.example test', 'hallo <a rel="mention" class="u-url mention" href="https://xn--noc6ci4b4b3a.example/@elpida">@ελπίδα</a> test' ),
			array( 'hallo @username@example.org-thanks', 'hallo <a rel="mention" class="u-url mention" href="https://example.org/users/username">@username</a>-thanks' ),
			// Invalid UTF-8 still links ASCII handles, and the stray byte is kept.
			array( "hallo @username@example.org \xC3\x28", "hallo <a rel=\"mention\" class=\"u-url mention\" href=\"https://example.org/users/username\">@username</a> \xC3\x28" ),
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
		// Mock webfinger for test actors.
		if ( 'https://example.org/.well-known/webfinger?resource=acct%3Ausername%40example.org' === $url ) {
			return array(
				'headers'  => array( 'content-type' => 'application/jrd+json' ),
				'body'     => wp_json_encode(
					array(
						'subject' => 'acct:username@example.org',
						'links'   => array(
							array(
								'rel'  => 'self',
								'type' => 'application/activity+json',
								'href' => 'https://example.org/users/username',
							),
						),
					)
				),
				'response' => array( 'code' => 200 ),
			);
		}

		// The lookup URL for `ελπίδα@ఉదాహరణ.example`, as the SWICG notes on internationalized handles spell it.
		if ( 'https://xn--noc6ci4b4b3a.example/.well-known/webfinger?resource=acct%3A%25CE%25B5%25CE%25BB%25CF%2580%25CE%25AF%25CE%25B4%25CE%25B1%40xn--noc6ci4b4b3a.example' === $url ) {
			return array(
				'headers'  => array( 'content-type' => 'application/jrd+json' ),
				'body'     => wp_json_encode(
					array(
						'subject' => 'acct:%CE%B5%CE%BB%CF%80%CE%AF%CE%B4%CE%B1@xn--noc6ci4b4b3a.example',
						'links'   => array(
							array(
								'rel'  => 'self',
								'type' => 'application/activity+json',
								'href' => 'https://xn--noc6ci4b4b3a.example/users/elpida',
							),
						),
					)
				),
				'response' => array( 'code' => 200 ),
			);
		}

		// Mock responses for remote users.
		if ( 'https://notiz.blog/.well-known/webfinger?resource=acct%3Apfefferle%40notiz.blog' === $url ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return json_decode( file_get_contents( AP_TESTS_DIR . '/data/fixtures/notiz-blog-well-known-webfinger.json' ), true );
		}

		if ( 'https://lemmy.ml/.well-known/webfinger?resource=acct%3Apfefferle%40lemmy.ml' === $url ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return json_decode( file_get_contents( AP_TESTS_DIR . '/data/fixtures/lemmy-ml-well-known-webfinger.json' ), true );
		}

		if ( 'https://notiz.blog/author/matthias-pfefferle/' === $url ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return json_decode( file_get_contents( AP_TESTS_DIR . '/data/fixtures/notiz-blog-author-matthias-pfefferle.json' ), true );
		}

		if ( 'https://lemmy.ml/u/pfefferle' === $url ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return json_decode( file_get_contents( AP_TESTS_DIR . '/data/fixtures/lemmy-ml-u-pfefferle.json' ), true );
		}

		return $response;
	}

	/**
	 * Mock ActivityPub remote object requests.
	 *
	 * @param mixed        $pre           The pre-filtered value.
	 * @param array|string $url_or_object The URL or object.
	 * @return mixed
	 */
	public function activitypub_pre_http_get_remote_object( $pre, $url_or_object ) {
		$url = object_to_uri( $url_or_object );

		// Check if this is a URL from our test actors.
		foreach ( self::$actors as $actor_data ) {
			if ( isset( $actor_data['id'] ) && $actor_data['id'] === $url ) {
				return $actor_data;
			}
			if ( isset( $actor_data['url'] ) && $actor_data['url'] === $url ) {
				return $actor_data;
			}
		}

		// Return parsed object data for ActivityPub actors.
		if ( 'https://notiz.blog/author/matthias-pfefferle/' === $url ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$fixture = json_decode( file_get_contents( AP_TESTS_DIR . '/data/fixtures/notiz-blog-author-matthias-pfefferle.json' ), true );
			return json_decode( $fixture['body'], true );
		}

		if ( 'https://lemmy.ml/u/pfefferle' === $url ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$fixture = json_decode( file_get_contents( AP_TESTS_DIR . '/data/fixtures/lemmy-ml-u-pfefferle.json' ), true );
			return json_decode( $fixture['body'], true );
		}

		return $pre;
	}

	/**
	 * An ASCII handle is extracted from content that is not valid UTF-8.
	 *
	 * @covers ::extract_mentions
	 */
	public function test_extract_mentions_in_invalid_utf8() {
		$mentions = Mention::extract_mentions( array(), "hallo @username@example.org \xC3\x28" );

		$this->assertArrayHasKey( '@username@example.org', $mentions );
	}

	/**
	 * Content that is not valid UTF-8 never has a multibyte character cut into a host.
	 *
	 * @covers ::extract_mentions
	 */
	public function test_extract_mentions_in_invalid_utf8_skips_non_ascii_hosts() {
		$hosts = array();
		$log   = static function ( $pre, $args, $url ) use ( &$hosts ) {
			$hosts[] = \wp_parse_url( $url, \PHP_URL_HOST );
			return $pre;
		};
		\add_filter( 'pre_http_request', $log, 5, 3 );

		Mention::extract_mentions( array(), "hallo \xC3\x28 @user@example.\xC3\xA9" );

		\remove_filter( 'pre_http_request', $log, 5 );

		$this->assertSame( array(), $hosts );
	}

	/**
	 * A mention written as a link, the way clients and Mastodon do, is extracted like a handle.
	 *
	 * @covers ::extract_mentions
	 */
	public function test_extract_mentions_finds_mention_links() {
		$stub = function ( $pre, $args, $url ) {
			if ( ! \str_starts_with( $url, 'https://mention.example/' ) ) {
				return $pre;
			}

			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array(),
				'body'     => \wp_json_encode(
					array(
						'subject' => 'acct:alice@mention.example',
						'links'   => array(
							array(
								'rel'  => 'self',
								'type' => 'application/activity+json',
								'href' => 'https://mention.example/users/alice',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $stub, 5, 3 );

		$content  = '<a rel="mention" class="u-url mention" href="https://mention.example/@alice">@alice</a> and <a href="https://mention.example/some/page">a page</a>';
		$mentions = Mention::extract_mentions( array(), $content );

		\remove_filter( 'pre_http_request', $stub, 5 );

		$this->assertSame( array( '@alice@mention.example' => 'https://mention.example/@alice' ), $mentions );
	}

	/**
	 * The plugin's own reply block links to the replied-to post, not to an actor: the post URL
	 * is not a mention link and must not be looked up.
	 *
	 * @covers ::extract_mentions
	 * @covers ::extract_mention_links
	 */
	public function test_extract_mentions_ignores_the_reply_block() {
		$lookups = 0;
		$stub    = function ( $pre, $args, $url ) use ( &$lookups ) {
			if ( false !== \strpos( \rawurldecode( $url ), 'https://mention.example/notes/1' ) ) {
				++$lookups;
			}

			return $pre;
		};
		\add_filter( 'pre_http_request', $stub, 5, 3 );

		$content  = '<p class="ap-reply-mention"><a rel="in-reply-to ugc" class="u-in-reply-to" href="https://mention.example/notes/1" title="@alice@mention.example">@alice</a></p>';
		$mentions = Mention::extract_mentions( array(), $content );

		\remove_filter( 'pre_http_request', $stub, 5 );

		$this->assertSame( array(), Mention::extract_mention_links( $content ) );
		$this->assertNotContains( 'https://mention.example/notes/1', $mentions );
		$this->assertSame( 0, $lookups );
	}

	/**
	 * The content links mentions, an excerpt keeps the full handle, since its tags are stripped.
	 *
	 * @covers ::the_content
	 */
	public function test_the_content_keeps_full_handle_in_excerpts() {
		$post = \get_post(
			self::factory()->post->create(
				array(
					'post_excerpt' => '',
					'post_content' => 'hallo @username@example.org test',
				)
			)
		);

		$this->assertStringContainsString( '<a rel="mention" class="u-url mention" href="https://example.org/users/username">@username</a>', \apply_filters( 'the_content', $post->post_content ) );
		$this->assertSame( 'hallo @username@example.org test', \get_the_excerpt( $post ) );
	}

	/**
	 * A mention in a post summary built from the content is still linked.
	 *
	 * @covers ::the_content
	 * @covers ::filter_activity_object
	 */
	public function test_post_summary_links_mentions() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::factory()->user->create( array( 'role' => 'author' ) ),
				'post_title'   => 'Title',
				'post_excerpt' => '',
				'post_content' => '<!-- wp:paragraph --><p>Hello @username@example.org how are you</p><!-- /wp:paragraph -->',
			)
		);

		$object = \Activitypub\Transformer\Factory::get_transformer( \get_post( $post_id ) )->to_object()->to_array();

		$this->assertSame( 'Article', $object['type'] );
		$this->assertSame( 'Hello <a rel="mention" class="u-url mention" href="https://example.org/users/username">@username</a> how are you', $object['summary'] );
	}

	/**
	 * A link marked with `rel="mention"` counts like one with the `mention` class.
	 *
	 * @covers ::extract_mention_links
	 */
	public function test_extract_mention_links_accepts_rel_mention() {
		$content = '<a rel="mention" href="https://mention.example/@alice">@alice</a> <a href="https://mention.example/some/page">a page</a>';

		$this->assertSame( array( 'https://mention.example/@alice' ), Mention::extract_mention_links( $content ) );
	}
}
