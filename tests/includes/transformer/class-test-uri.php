<?php
/**
 * Test URI Transformer Class.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Transformer\Uri;

/**
 * Test class for URI Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Uri
 */
class Test_Uri extends \WP_UnitTestCase {
	/**
	 * Test transforming a URI to an ActivityPub Object.
	 *
	 * @covers ::to_object
	 */
	public function test_to_object() {
		$uri = 'https://example.com/test';
		$transformer = new Uri( $uri );

		$this->assertEquals( $uri, $transformer->to_object() );
	}

	/**
	 * Test getting the ID of a URI.
	 *
	 * @covers ::to_id
	 */
	public function test_to_id() {
		$uri = 'https://example.com/test';
		$transformer = new Uri( $uri );

		$this->assertEquals( $uri, $transformer->to_id() );
	}

	/**
	 * Test transforming different URI formats.
	 *
	 * @covers ::to_object
	 * @dataProvider uri_provider
	 *
	 * @param string $uri The URI to test.
	 */
	public function test_different_uri_formats( $uri ) {
		$transformer = new Uri( $uri );
		$this->assertEquals( $uri, $transformer->to_object() );
	}

	/**
	 * Data provider for test_different_uri_formats.
	 *
	 * @return array
	 */
	public function uri_provider() {
		return array(
			'simple_url' => array(
				'https://example.com/test',
			),
			'url_with_query' => array(
				'https://example.com/test?param=value',
			),
			'url_with_fragment' => array(
				'https://example.com/test#fragment',
			),
			'url_with_port' => array(
				'https://example.com:8080/test',
			),
			'url_with_username' => array(
				'https://user@example.com/test',
			),
			'complex_url' => array(
				'https://user:pass@example.com:8080/test?param=value#fragment',
			),
		);
	}
}
