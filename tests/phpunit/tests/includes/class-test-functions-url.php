<?php
/**
 * Test file for URL Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for URL Functions.
 */
class Test_Functions_Url extends \WP_UnitTestCase {

	/**
	 * Test normalize_url.
	 *
	 * @dataProvider data_normalize_url
	 *
	 * @covers \Activitypub\normalize_url
	 *
	 * @param string $url     The URL.
	 * @param string $expected The expected result.
	 */
	public function test_normalize_url( $url, $expected ) {
		$this->assertEquals( $expected, \Activitypub\normalize_url( $url ) );
	}

	/**
	 * Data provider for test_normalize_url.
	 *
	 * @return array[]
	 */
	public function data_normalize_url() {
		return array(
			array( 'https://example.com', 'example.com' ),
			array( 'http://example.com', 'example.com' ),
			array( 'https://example.com/path', 'example.com/path' ),
			array( 'http://example.com/path', 'example.com/path' ),
			array( 'http://example.com/path/', 'example.com/path' ),
			array( 'https://www.example.com/path/to/nowhere', 'example.com/path/to/nowhere' ),
			array( 'http://www.example.com/path/to/nowhere', 'example.com/path/to/nowhere' ),
		);
	}

	/**
	 * Test normalize_host.
	 *
	 * @dataProvider data_normalize_host
	 *
	 * @covers \Activitypub\normalize_host
	 *
	 * @param string $host     The host.
	 * @param string $expected The expected result.
	 */
	public function test_normalize_host( $host, $expected ) {
		$this->assertEquals( $expected, \Activitypub\normalize_host( $host ) );
	}

	/**
	 * Data provider for test_normalize_host.
	 *
	 * @return array[]
	 */
	public function data_normalize_host() {
		return array(
			array( 'example.com', 'example.com' ),
			array( 'www.example.com', 'example.com' ),
		);
	}
}
