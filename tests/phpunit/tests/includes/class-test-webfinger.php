<?php
/**
 * Test file for Activitypub Webfinger.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Actor;
use Activitypub\Http;
use Activitypub\Webfinger;

/**
 * Test class for Activitypub Webfinger.
 *
 * @coversDefaultClass \Activitypub\Webfinger
 */
class Test_Webfinger extends \WP_UnitTestCase {
	/**
	 * Test the webfinger endpoint.
	 *
	 * @dataProvider the_cache_key_provider
	 * @covers ::generate_cache_key
	 *
	 * @param string $uri The URI to generate the cache key for.
	 * @param string $hash The expected hash.
	 */
	public function test_generate_cache_key( $uri, $hash ) {
		$cache_key = Webfinger::generate_cache_key( $uri );

		$this->assertEquals( $cache_key, 'webfinger_' . $hash );
	}

	/**
	 * Cache key provider.
	 *
	 * @return array[]
	 */
	public function the_cache_key_provider() {
		return array(
			array( 'http://example.org/?author=1', md5( 'http://example.org/?author=1' ) ),
			array( '@author@example.org', md5( 'acct:author@example.org' ) ),
			array( 'author@example.org', md5( 'acct:author@example.org' ) ),
			array( 'acct:author@example.org', md5( 'acct:author@example.org' ) ),
			array( 'https://example.org', md5( 'https://example.org' ) ),
		);
	}

	/**
	 * Test the get_identifier_and_host method.
	 *
	 * @dataProvider the_identifier_and_host_provider
	 * @covers ::get_identifier_and_host
	 *
	 * @param string $uri        The URI to generate the identifier and host for.
	 * @param string $identifier The expected identifier.
	 * @param string $host       The expected host.
	 */
	public function test_get_identifier_and_host( $uri, $identifier, $host ) {
		$this->assertEquals(
			array( $identifier, $host ),
			Webfinger::get_identifier_and_host( $uri )
		);
	}

	/**
	 * Identifier and host provider.
	 *
	 * @return array[]
	 */
	public function the_identifier_and_host_provider() {
		return array(
			array( 'author@example.org', 'acct:author@example.org', 'example.org' ),
			array( 'acct:author@example.org', 'acct:author@example.org', 'example.org' ),
			array( 'https://example.org/@pfefferle', 'https://example.org/@pfefferle', 'example.org' ),
			array( 'mailto:pfefferle@example.org', 'mailto:pfefferle@example.org', 'example.org' ),
			array( 'xmpp:pfefferle@example.com', 'xmpp:pfefferle@example.com', 'example.com' ),
		);
	}

	/**
	 * Test the get_data method.
	 *
	 * @dataProvider the_get_data_provider
	 * @covers ::get_data
	 *
	 * @param string $uri      The URI to get data for.
	 * @param array  $data     The data to return.
	 * @param array  $expected The expected data.
	 */
	public function test_get_data( $uri, $data, $expected ) {
		$filter = function () use ( $data ) {
			return $data;
		};
		\add_filter( 'pre_http_request', $filter );

		$data = Webfinger::get_data( $uri );

		$this->assertEquals( $expected, $data );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Data provider for test_get_data.
	 *
	 * @return array[]
	 */
	public function the_get_data_provider() {
		return array(
			array(
				'http://example.org/?author=1',
				array(
					'response' => array(
						'code' => 200,
					),
					'body'     => '{ "subject": "acct:pfefferle@example.org", "aliases": [ "https://example.org/?author=1" ] }',
				),
				array(
					'subject' => 'acct:pfefferle@example.org',
					'aliases' => array( 'https://example.org/?author=1' ),
				),
			),
			array(
				'http://example.org/?author=1',
				array(
					'response' => array(
						'code' => 400,
					),
					'body'     => 'error',
				),
				new \WP_Error(
					400,
					__( 'Failed HTTP Request', 'activitypub' ),
					array( 'status' => 400 )
				),
			),
			array(
				'test@example.org',
				array(
					'response' => array(
						'code' => 404,
					),
					'body'     => '{"type":"about:blank","title":"activitypub_wrong_host"}',
				),
				new \WP_Error(
					404,
					__( 'Failed HTTP Request', 'activitypub' ),
					array( 'status' => 404 )
				),
			),
		);
	}

	/**
	 * Test the resolve method.
	 *
	 * @dataProvider the_resolve_provider
	 * @covers ::resolve
	 *
	 * @param string $uri      The URI to resolve.
	 * @param array  $data     The data to return.
	 * @param mixed  $expected The expected result.
	 */
	public function test_resolve( $uri, $data, $expected ) {
		$filter = function () use ( $data ) {
			return $data;
		};
		\add_filter( 'pre_http_request', $filter );

		$data = Webfinger::resolve( $uri );

		$this->assertEquals( $expected, $data );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Data provider for test_resolve.
	 *
	 * @return array[]
	 */
	public function the_resolve_provider() {
		return array(
			array(
				'http://example.org/?author=1',
				array(
					'response' => array(
						'code' => 200,
					),
					'body'     => '{ "subject": "acct:test@example.org", "aliases": [ "https://example.org/?author=1" ] }',
				),
				new \WP_Error(
					'webfinger_missing_links',
					__( 'No valid Link elements found.', 'activitypub' ),
					array(
						'status' => 400,
						'data'   => array(
							'subject' => 'acct:test@example.org',
							'aliases' => array( 'https://example.org/?author=1' ),
						),
					)
				),
			),
			array(
				'http://example.org/?author=1',
				array(
					'response' => array(
						'code' => 200,
					),
					'body'     => '{ "subject": "acct:test@example.org", "aliases": [ "https://example.org/?author=1" ], "links": [] }',
				),
				new \WP_Error(
					'webfinger_missing_links',
					__( 'No valid Link elements found.', 'activitypub' ),
					array(
						'status' => 400,
						'data'   => array(
							'subject' => 'acct:test@example.org',
							'aliases' => array( 'https://example.org/?author=1' ),
							'links'   => array(),
						),
					)
				),
			),
			array(
				'http://example.org/?author=1',
				array(
					'response' => array(
						'code' => 200,
					),
					'body'     => '{ "subject": "acct:test@example.org", "aliases": [ "https://example.org/?author=1" ], "links": [ { "rel": "http://webfinger.net/rel/profile-page", "href": "https://example.org/?author=1" } ] }',
				),
				new \WP_Error(
					'webfinger_url_no_activitypub',
					__( 'The Site supports WebFinger but not ActivityPub', 'activitypub' ),
					array(
						'status' => 400,
						'data'   => array(
							'subject' => 'acct:test@example.org',
							'aliases' => array( 'https://example.org/?author=1' ),
							'links'   => array(
								array(
									'rel'  => 'http://webfinger.net/rel/profile-page',
									'href' => 'https://example.org/?author=1',
								),
							),
						),
					)
				),
			),
			array(
				'http://example.org/?author=1',
				array(
					'response' => array(
						'code' => 200,
					),
					'body'     => '{ "subject": "acct:test@example.org", "aliases": [ "https://example.org/?author=1" ], "links": [ { "rel": "self", "type": "application/activity+json", "href": "https://example.org/?author=1" } ] }',
				),
				'https://example.org/?author=1',
			),
		);
	}

	/**
	 * Test the guess method.
	 *
	 * @dataProvider the_guess_provider
	 * @covers ::guess
	 *
	 * @param string $actor_or_uri The Actor or URI.
	 * @param string $expected     The expected result.
	 */
	public function test_guess( $actor_or_uri, $expected ) {
		$this->assertEquals( $expected, Webfinger::guess( $actor_or_uri ) );
	}

	/**
	 * Guess provider.
	 *
	 * @return array[]
	 */
	public function the_guess_provider() {
		return array(
			array(
				'http://example.org/?author=1',
				'example.org@example.org',
			),
			array(
				'https://example.org/@author',
				'author@example.org',
			),
			array(
				'https://example.org/users/author',
				'author@example.org',
			),
			array(
				Actor::init_from_array(
					array(
						'id'                => 'https://example.org/users/author',
						'preferredUsername' => 'author',
					)
				),
				'author@example.org',
			),
			array(
				Actor::init_from_array(
					array(
						'id'   => 'https://example.org/users/author',
						'name' => 'john',
					)
				),
				'author@example.org',
			),
		);
	}

	/**
	 * Test get_intent_endpoint with FEP-3b86 link.
	 *
	 * @covers ::get_intent_endpoint
	 */
	public function test_get_intent_endpoint_fep3b86() {
		$filter = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'subject' => 'acct:user@example.com',
						'links'   => array(
							array(
								'rel'      => 'https://w3id.org/fep/3b86/like',
								'template' => 'https://example.com/intent/like?object={object}',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$result = Webfinger::get_intent_endpoint( 'user@example.com', 'like' );

		$this->assertEquals( 'https://example.com/intent/like?object={object}', $result );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test get_intent_endpoint falls back to OStatus subscribe.
	 *
	 * @covers ::get_intent_endpoint
	 */
	public function test_get_intent_endpoint_ostatus_fallback() {
		$filter = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'subject' => 'acct:user@example.com',
						'links'   => array(
							array(
								'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
								'template' => 'https://example.com/authorize_interaction?uri={uri}',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$result = Webfinger::get_intent_endpoint( 'user@example.com', 'like', true );

		$this->assertEquals( 'https://example.com/authorize_interaction?uri={uri}', $result );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test get_intent_endpoint without fallback returns error.
	 *
	 * @covers ::get_intent_endpoint
	 */
	public function test_get_intent_endpoint_no_fallback_returns_error() {
		$filter = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'subject' => 'acct:user@example.com',
						'links'   => array(
							array(
								'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
								'template' => 'https://example.com/authorize_interaction?uri={uri}',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$result = Webfinger::get_intent_endpoint( 'user@example.com', 'like', false );

		$this->assertWPError( $result );
		$this->assertEquals( 'webfinger_missing_intent_endpoint', $result->get_error_code() );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test get_intent_endpoint last-resort Mastodon-compatible URL for handle.
	 *
	 * @covers ::get_intent_endpoint
	 */
	public function test_get_intent_endpoint_mastodon_fallback_from_handle() {
		// Provide links that don't match any intent or OStatus template.
		$filter = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'subject' => 'acct:user@mastodon.social',
						'links'   => array(
							array(
								'rel'  => 'self',
								'type' => 'application/activity+json',
								'href' => 'https://mastodon.social/users/user',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$result = Webfinger::get_intent_endpoint( 'user@mastodon.social', 'like', true );

		$this->assertEquals( 'https://mastodon.social/authorize_interaction?uri={uri}', $result );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test get_intent_endpoint with missing links returns error.
	 *
	 * @covers ::get_intent_endpoint
	 */
	public function test_get_intent_endpoint_missing_links() {
		$filter = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'subject' => 'acct:user@example.com',
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$result = Webfinger::get_intent_endpoint( 'user@example.com', 'like' );

		$this->assertWPError( $result );
		$this->assertEquals( 'webfinger_missing_links', $result->get_error_code() );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test get_intent_endpoint with a full URL intent.
	 *
	 * @covers ::get_intent_endpoint
	 */
	public function test_get_intent_endpoint_full_url_intent() {
		$filter = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'subject' => 'acct:user@example.com',
						'links'   => array(
							array(
								'rel'      => 'https://custom.example/intent/share',
								'template' => 'https://example.com/share?url={uri}',
							),
						),
					)
				),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$result = Webfinger::get_intent_endpoint( 'user@example.com', 'https://custom.example/intent/share' );

		$this->assertEquals( 'https://example.com/share?url={uri}', $result );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test that WebFinger 4xx failures are cached (longer duration).
	 *
	 * @covers ::get_data
	 */
	public function test_get_data_caches_4xx_failure() {
		$webfinger_url = 'https://unreachable.example/.well-known/webfinger?resource=acct%3Afailure-test-4xx%40unreachable.example';

		// Clear Http cache.
		\delete_transient( Http::generate_cache_key( $webfinger_url ) );

		// Mock a 404 HTTP response.
		$request_count = 0;
		$filter        = function () use ( &$request_count ) {
			++$request_count;
			return array(
				'response' => array(
					'code' => 404,
				),
				'body'     => 'Not Found',
			);
		};
		\add_filter( 'pre_http_request', $filter );

		// First call should make an HTTP request and return an error.
		$result = Webfinger::get_data( 'failure-test-4xx@unreachable.example' );

		$this->assertWPError( $result );
		$this->assertEquals( 404, $result->get_error_code() );
		$this->assertEquals( 1, $request_count, 'First call should make one HTTP request' );

		// Second call should return cached error without making another HTTP request.
		$result = Webfinger::get_data( 'failure-test-4xx@unreachable.example' );

		$this->assertWPError( $result );
		$this->assertEquals( 404, $result->get_error_code() );
		$this->assertEquals( 1, $request_count, 'Second call should use cache, not make another HTTP request' );

		\remove_filter( 'pre_http_request', $filter );

		// Clean up.
		\delete_transient( Http::generate_cache_key( $webfinger_url ) );
	}

	/**
	 * Test that WebFinger 5xx failures are cached (shorter duration).
	 *
	 * @covers ::get_data
	 */
	public function test_get_data_caches_5xx_failure() {
		$webfinger_url = 'https://unreachable.example/.well-known/webfinger?resource=acct%3Afailure-test-5xx%40unreachable.example';

		// Clear Http cache.
		\delete_transient( Http::generate_cache_key( $webfinger_url ) );

		// Mock a 503 HTTP response.
		$request_count = 0;
		$filter        = function () use ( &$request_count ) {
			++$request_count;
			return array(
				'response' => array(
					'code' => 503,
				),
				'body'     => 'Service Unavailable',
			);
		};
		\add_filter( 'pre_http_request', $filter );

		// First call should make an HTTP request and return an error.
		$result = Webfinger::get_data( 'failure-test-5xx@unreachable.example' );

		$this->assertWPError( $result );
		$this->assertEquals( 503, $result->get_error_code() );
		$this->assertEquals( 1, $request_count, 'First call should make one HTTP request' );

		// Second call should return cached error without making another HTTP request.
		$result = Webfinger::get_data( 'failure-test-5xx@unreachable.example' );

		$this->assertWPError( $result );
		$this->assertEquals( 503, $result->get_error_code() );
		$this->assertEquals( 1, $request_count, 'Second call should use cache, not make another HTTP request' );

		\remove_filter( 'pre_http_request', $filter );

		// Clean up.
		\delete_transient( Http::generate_cache_key( $webfinger_url ) );
	}

	/**
	 * Test that WebFinger timeout failures are cached (shorter duration).
	 *
	 * @covers ::get_data
	 */
	public function test_get_data_caches_timeout_failure() {
		$webfinger_url = 'https://unreachable.example/.well-known/webfinger?resource=acct%3Afailure-test-timeout%40unreachable.example';

		// Clear Http cache.
		\delete_transient( Http::generate_cache_key( $webfinger_url ) );

		// Mock a timeout (WP_Error).
		$request_count = 0;
		$filter        = function () use ( &$request_count ) {
			++$request_count;
			return new \WP_Error( 'http_request_failed', 'Connection timed out' );
		};
		\add_filter( 'pre_http_request', $filter );

		// First call should make an HTTP request and return an error.
		$result = Webfinger::get_data( 'failure-test-timeout@unreachable.example' );

		$this->assertWPError( $result );
		$this->assertEquals( 1, $request_count, 'First call should make one HTTP request' );

		// Second call should return cached error without making another HTTP request.
		$result = Webfinger::get_data( 'failure-test-timeout@unreachable.example' );

		$this->assertWPError( $result );
		$this->assertEquals( 1, $request_count, 'Second call should use cache, not make another HTTP request' );

		\remove_filter( 'pre_http_request', $filter );

		// Clean up.
		\delete_transient( Http::generate_cache_key( $webfinger_url ) );
	}

	/**
	 * Test that WebFinger successes are still cached properly.
	 *
	 * @covers ::get_data
	 */
	public function test_get_data_caches_success() {
		$uri = 'success-test@reachable.example';

		// Clear any existing cache.
		$transient_key = Webfinger::generate_cache_key( $uri );
		\delete_transient( $transient_key );

		// Mock a successful HTTP response.
		$request_count = 0;
		$filter        = function () use ( &$request_count ) {
			++$request_count;
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{ "subject": "acct:success-test@reachable.example", "links": [] }',
			);
		};
		\add_filter( 'pre_http_request', $filter );

		// First call should make an HTTP request.
		$result = Webfinger::get_data( $uri );

		$this->assertIsArray( $result );
		$this->assertEquals( 'acct:success-test@reachable.example', $result['subject'] );
		$this->assertEquals( 1, $request_count, 'First call should make one HTTP request' );

		// Second call should use cache.
		$result = Webfinger::get_data( $uri );

		$this->assertIsArray( $result );
		$this->assertEquals( 'acct:success-test@reachable.example', $result['subject'] );
		$this->assertEquals( 1, $request_count, 'Second call should use cache, not make another HTTP request' );

		\remove_filter( 'pre_http_request', $filter );

		// Clean up.
		\delete_transient( $transient_key );
	}

	/**
	 * Test that valid acct identifiers are recognized.
	 *
	 * @covers ::is_acct
	 * @dataProvider data_valid_accts
	 *
	 * @param string $value The value under test.
	 */
	public function test_is_acct_valid( $value ) {
		$this->assertTrue( Webfinger::is_acct( $value ) );
	}

	/**
	 * Provider for valid acct identifiers.
	 *
	 * @return array
	 */
	public function data_valid_accts() {
		return array(
			'plain acct'          => array( 'user@example.com' ),
			'leading at'          => array( '@user@example.com' ),
			'acct uri'            => array( 'acct:user@example.com' ),
			'multi-segment host'  => array( 'user@subdomain.example.com' ),
			'numeric local part'  => array( 'user42@example.com' ),
			'dots in local part'  => array( 'first.last@example.com' ),
			'underscore in local' => array( 'first_last@example.com' ),
			'dash in local'       => array( 'first-last@example.com' ),
		);
	}

	/**
	 * Test that non-acct values are rejected.
	 *
	 * @covers ::is_acct
	 * @dataProvider data_invalid_accts
	 *
	 * @param mixed $value The value under test.
	 */
	public function test_is_acct_invalid( $value ) {
		$this->assertFalse( Webfinger::is_acct( $value ) );
	}

	/**
	 * Provider for non-acct values.
	 *
	 * @return array
	 */
	public function data_invalid_accts() {
		return array(
			'empty string'   => array( '' ),
			'plain word'     => array( 'user' ),
			'url'            => array( 'https://example.com/users/user' ),
			'mailto uri'     => array( 'mailto:user@example.com' ),
			'host only'      => array( '@example.com' ),
			'no tld'         => array( 'user@host' ),
			'trailing slash' => array( 'user@example.com/' ),
			'null'           => array( null ),
			'integer'        => array( 42 ),
			'array'          => array( array( 'user@example.com' ) ),
		);
	}
}
