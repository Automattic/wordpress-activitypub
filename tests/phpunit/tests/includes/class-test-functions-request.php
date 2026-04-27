<?php
/**
 * Test file for Request Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Request Functions.
 */
class Test_Functions_Request extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Test the get_remote_metadata_by_actor function.
	 *
	 * @covers \Activitypub\get_remote_metadata_by_actor
	 */
	public function test_get_remote_metadata_by_actor() {
		$metadata = \Activitypub\get_remote_metadata_by_actor( 'pfefferle@notiz.blog' );
		$this->assertEquals( 'https://notiz.blog/author/matthias-pfefferle/', $metadata['url'] );
		$this->assertEquals( 'pfefferle', $metadata['preferredUsername'] );
		$this->assertEquals( 'Matthias Pfefferle', $metadata['name'] );
	}

	/**
	 * Data provider for resolve_public_host IP-literal cases.
	 *
	 * @return array<string, array{0: string, 1: string|false}>
	 */
	public function resolve_public_host_ip_provider() {
		return array(
			'public_ipv4'                 => array( '8.8.8.8', '8.8.8.8' ),
			'cloudflare_ipv4'             => array( '1.1.1.1', '1.1.1.1' ),
			'loopback_ipv4'               => array( '127.0.0.1', false ),
			'loopback_ipv4_anywhere_in_8' => array( '127.5.4.3', false ),
			'unspecified_ipv4'            => array( '0.0.0.0', false ),
			'link_local_metadata'         => array( '169.254.169.254', false ),
			'rfc1918_10'                  => array( '10.0.0.1', false ),
			'rfc1918_172'                 => array( '172.20.0.7', false ),
			'rfc1918_192'                 => array( '192.168.1.1', false ),
			'ipv6_loopback'               => array( '::1', false ),
			'ipv6_loopback_bracketed'     => array( '[::1]', false ),
			'public_ipv6'                 => array( '2001:db8::1', '2001:db8::1' ),
			'public_ipv6_bracketed'       => array( '[2001:db8::1]', '2001:db8::1' ),
			'empty_string'                => array( '', false ),
		);
	}

	/**
	 * Test resolve_public_host returns the IP for public addresses and false otherwise.
	 *
	 * @dataProvider resolve_public_host_ip_provider
	 *
	 * @covers \Activitypub\resolve_public_host
	 *
	 * @param string       $host     The host or IP literal under test.
	 * @param string|false $expected Expected return value.
	 */
	public function test_resolve_public_host_ip_literals( $host, $expected ) {
		$this->assertSame( $expected, \Activitypub\resolve_public_host( $host ) );
	}

	/**
	 * Test that non-string input is rejected.
	 *
	 * @covers \Activitypub\resolve_public_host
	 */
	public function test_resolve_public_host_rejects_non_string() {
		$this->assertFalse( \Activitypub\resolve_public_host( null ) );
		$this->assertFalse( \Activitypub\resolve_public_host( 12345 ) );
		$this->assertFalse( \Activitypub\resolve_public_host( array( '8.8.8.8' ) ) );
	}
}
