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

	/**
	 * Internationalized hosts are converted to their A-label, everything else is returned as it is.
	 *
	 * @covers \Activitypub\to_ascii_host
	 * @dataProvider ascii_host_provider
	 *
	 * @param string $host     The host.
	 * @param string $expected The expected host.
	 */
	public function test_to_ascii_host( $host, $expected ) {
		$this->assertSame( $expected, \Activitypub\to_ascii_host( $host ) );
	}

	/**
	 * Hosts from the SWICG notes on internationalized handles, and ASCII hosts.
	 *
	 * @return array[]
	 */
	public function ascii_host_provider() {
		return array(
			'thai'    => array( 'ตัวอย่าง.example', 'xn--72c1a1bt4awk9o.example' ),
			'telugu'  => array( 'ఉదాహరణ.example', 'xn--noc6ci4b4b3a.example' ),
			'chinese' => array( 'example.中国', 'example.xn--fiqs8s' ),
			'latin'   => array( 'café.example', 'xn--caf-dma.example' ),
			'a-label' => array( 'xn--caf-dma.example', 'xn--caf-dma.example' ),
			'ascii'   => array( 'Example.org', 'Example.org' ),
			'ipv6'    => array( '[2001:db8::1]', '[2001:db8::1]' ),
			'empty'   => array( '', '' ),
		);
	}

	/**
	 * The Unicode form and the A-label of a host fold to the same value.
	 *
	 * @covers \Activitypub\fold_host
	 */
	public function test_fold_host_folds_internationalized_hosts() {
		$this->assertSame( 'xn--caf-dma.example', \Activitypub\fold_host( 'café.example' ) );
		$this->assertSame( 'xn--caf-dma.example', \Activitypub\fold_host( 'café.example.' ) );
		$this->assertSame( 'xn--caf-dma.example', \Activitypub\fold_host( 'XN--CAF-DMA.example' ) );
	}
}
