<?php
/**
 * Test file for Activitypub Webfinger.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub Webfinger.
 *
 * @coversDefaultClass \Activitypub\Webfinger
 */
class Test_Activitypub_Webfinger extends WP_UnitTestCase {
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
		$cache_key = Activitypub\Webfinger::generate_cache_key( $uri );

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
}
