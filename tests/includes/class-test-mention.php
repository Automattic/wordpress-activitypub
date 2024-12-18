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
		if ( 'https://notiz.blog/.well-known/webfinger?resource=acct%3Apfefferle%40notiz.blog' === $url ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'subject' => 'acct:pfefferle@notiz.blog',
						'aliases' => array(
							'acct:pfefferle@notiz.blog',
							'https://notiz.blog/author/matthias-pfefferle/',
							'https://notiz.blog/@pfefferle',
						),
						'links'   => array(
							array(
								'rel'  => 'http://webfinger.net/rel/profile-page',
								'type' => 'text/html',
								'href' => 'https://notiz.blog/author/matthias-pfefferle/',
							),
							array(
								'rel'  => 'http://webfinger.net/rel/avatar',
								'href' => 'https://notiz.blog/wp-content/uploads/avatar-privacy/cache/user/1/9/19d7da2fb5b6409265f7c51eb992c3aca83b854ddb371bec96ab05d6f40a45eb-96.jpg',
							),
							array(
								'rel'  => 'http://webfinger.net/rel/profile-page',
								'type' => 'text/html',
								'href' => 'https://pfefferle.org/',
							),
							array(
								'rel'  => 'self',
								'type' => 'application/activity+json',
								'href' => 'https://notiz.blog/author/matthias-pfefferle/',
							),
							array(
								'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
								'template' => 'https://notiz.blog/wp-api/activitypub/1.0/interactions?uri={uri}',
							),
							array(
								'rel'  => 'payment',
								'href' => 'https://www.paypal.me/matthiaspfefferle',
							),
							array(
								'rel'  => 'payment',
								'href' => 'https://liberapay.com/pfefferle/',
							),
							array(
								'rel'  => 'payment',
								'href' => 'https://notiz.blog/donate/',
							),
							array(
								'rel'  => 'payment',
								'href' => 'https://flattr.com/@pfefferle',
							),
							array(
								'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.1',
								'href' => 'https://notiz.blog/wp-api/nodeinfo/2.1',
							),
							array(
								'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
								'href' => 'https://notiz.blog/wp-api/nodeinfo/2.0',
							),
							array(
								'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/1.1',
								'href' => 'https://notiz.blog/wp-api/nodeinfo/1.1',
							),
							array(
								'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/1.0',
								'href' => 'https://notiz.blog/wp-api/nodeinfo/1.0',
							),
							array(
								'rel'  => 'http://a9.com/-/spec/opensearch/1.1/',
								'type' => 'application/opensearchdescription+xml',
								'href' => 'https://notiz.blog/wp-api/opensearch/1.1/document',
							),
							array(
								'rel'  => 'webmention',
								'href' => 'https://notiz.blog/wp-api/webmention/1.0/endpoint',
							),
							array(
								'rel'  => 'http://webmention.org/',
								'href' => 'https://notiz.blog/wp-api/webmention/1.0/endpoint',
							),
						),
					)
				),
			);
		}

		if ( 'https://lemmy.ml/.well-known/webfinger?resource=acct%3Apfefferle%40lemmy.ml' === $url ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'subject' => 'acct:pfefferle@lemmy.ml',
						'links'   => array(
							array(
								'rel'  => 'http://webfinger.net/rel/profile-page',
								'type' => 'text/html',
								'href' => 'https://lemmy.ml/u/pfefferle',
							),
							array(
								'rel'        => 'self',
								'type'       => 'application/activity+json',
								'href'       => 'https://lemmy.ml/u/pfefferle',
								'properties' => array(
									'https://www.w3.org/ns/activitystreams#type' => 'Person',
								),
							),
							array(
								'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
								'template' => 'https://lemmy.ml/activitypub/externalInteraction?uri={uri}',
							),
						),
					)
				),
			);
		}

		if ( false !== strpos( $url, 'notiz.blog' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'@context'                  => array(
							'https://www.w3.org/ns/activitystreams',
							'https://w3id.org/security/v1',
							'https://purl.archive.org/socialweb/webfinger',
							array(
								'schema'                  => 'http://schema.org#',
								'toot'                    => 'http://joinmastodon.org/ns#',
								'lemmy'                   => 'https://join-lemmy.org/ns#',
								'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
								'PropertyValue'           => 'schema:PropertyValue',
								'value'                   => 'schema:value',
								'Hashtag'                 => 'as:Hashtag',
								'featured'                => array(
									'@id'   => 'toot:featured',
									'@type' => '@id',
								),
								'featuredTags'            => array(
									'@id'   => 'toot:featuredTags',
									'@type' => '@id',
								),
								'moderators'              => array(
									'@id'   => 'lemmy:moderators',
									'@type' => '@id',
								),
								'attributionDomains'      => array(
									'@id'   => 'toot:attributionDomains',
									'@type' => '@id',
								),
								'postingRestrictedToMods' => 'lemmy:postingRestrictedToMods',
								'discoverable'            => 'toot:discoverable',
								'indexable'               => 'toot:indexable',
							),
						),
						'id'                        => 'https://notiz.blog/author/matthias-pfefferle/',
						'type'                      => 'Person',
						'attachment'                => array(
							array(
								'type'  => 'PropertyValue',
								'name'  => 'Blog',
								'value' => '<p><a rel="me noopener" title="https://notiz.blog/" target="_blank" href="https://notiz.blog/">notiz.blog</a></p>',
							),
							array(
								'type' => 'Link',
								'name' => 'Blog',
								'href' => 'https://notiz.blog/',
								'rel'  => array( 'me', 'noopener' ),
							),
							array(
								'type'  => 'PropertyValue',
								'name'  => 'Podcasts',
								'value' => '<p><a href="https://notiz.blog/podcasts" rel="me">notiz.blog/podcasts</a></p>',
							),
							array(
								'type' => 'Link',
								'name' => 'Podcasts',
								'href' => 'https://notiz.blog/podcasts',
								'rel'  => array( 'me' ),
							),
						),
						'name'                      => 'Matthias Pfefferle',
						'icon'                      => array(
							'type' => 'Image',
							'url'  => 'https://notiz.blog/wp-content/uploads/avatar-privacy/cache/user/1/9/19d7da2fb5b6409265f7c51eb992c3aca83b854ddb371bec96ab05d6f40a45eb-120.jpg',
						),
						'image'                     => array(
							'type' => 'Image',
							'url'  => 'https://notiz.blog/wp-content/uploads/2024/07/cropped-pixel-pfefferle.jpg',
						),
						'published'                 => '2005-11-25T11:41:56Z',
						'summary'                   => '',
						'tag'                       => array(),
						'url'                       => 'https://notiz.blog/author/matthias-pfefferle/',
						'inbox'                     => 'https://notiz.blog/wp-api/activitypub/1.0/actors/1/inbox',
						'outbox'                    => 'https://notiz.blog/wp-api/activitypub/1.0/actors/1/outbox',
						'following'                 => 'https://notiz.blog/wp-api/activitypub/1.0/actors/1/following',
						'followers'                 => 'https://notiz.blog/wp-api/activitypub/1.0/actors/1/followers',
						'preferredUsername'         => 'pfefferle',
						'publicKey'                 => array(
							'id'           => 'https://notiz.blog/author/matthias-pfefferle/#main-key',
							'owner'        => 'https://notiz.blog/author/matthias-pfefferle/',
							'publicKeyPem' => '',
						),
						'manuallyApprovesFollowers' => false,
						'attributionDomains'        => array( 'notiz.blog' ),
						'featured'                  => 'https://notiz.blog/wp-api/activitypub/1.0/actors/1/collections/featured',
						'discoverable'              => true,
						'indexable'                 => true,
						'webfinger'                 => 'pfefferle@notiz.blog',
					)
				),
			);
		}
		if ( false !== strpos( $url, 'lemmy.ml' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'@context'          => array(
							'https://join-lemmy.org/context.json',
							'https://www.w3.org/ns/activitystreams',
						),
						'type'              => 'Person',
						'id'                => 'https://lemmy.ml/u/pfefferle',
						'preferredUsername' => 'pfefferle',
						'inbox'             => 'https://lemmy.ml/u/pfefferle/inbox',
						'outbox'            => 'https://lemmy.ml/u/pfefferle/outbox',
						'publicKey'         => array(
							'id'           => 'https://lemmy.ml/u/pfefferle#main-key',
							'owner'        => 'https://lemmy.ml/u/pfefferle',
							'publicKeyPem' => '',
						),
						'endpoints'         => array(
							'sharedInbox' => 'https://lemmy.ml/inbox',
						),
						'published'         => '2020-01-07T08:09:09.600169Z',
					)
				),
			);
		}

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array() ),
		);
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

		return $pre;
	}
}
