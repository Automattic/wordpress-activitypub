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
}
