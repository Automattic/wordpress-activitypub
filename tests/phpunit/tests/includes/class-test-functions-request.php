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
			'public_ipv6'                 => array( '2606:4700:4700::1111', '2606:4700:4700::1111' ),
			'public_ipv6_bracketed'       => array( '[2606:4700:4700::1111]', '2606:4700:4700::1111' ),
			'ipv4_mapped_loopback'        => array( '::ffff:127.0.0.1', false ),
			'ipv4_mapped_rfc1918'         => array( '::ffff:10.0.0.1', false ),
			'ipv4_mapped_link_local'      => array( '::ffff:169.254.169.254', false ),
			'ipv4_mapped_public'          => array( '::ffff:8.8.8.8', false ),
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

	/**
	 * Data provider for is_ipv4_mapped_ipv6.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function is_ipv4_mapped_ipv6_provider() {
		return array(
			'mapped_loopback'    => array( '::ffff:127.0.0.1', true ),
			'mapped_rfc1918'     => array( '::ffff:10.0.0.1', true ),
			'mapped_link_local'  => array( '::ffff:169.254.169.254', true ),
			'mapped_public'      => array( '::ffff:8.8.8.8', true ),
			'mapped_hex_form'    => array( '::ffff:7f00:1', true ),
			'pure_ipv6_loopback' => array( '::1', false ),
			'pure_ipv6_public'   => array( '2606:4700:4700::1111', false ),
			'pure_ipv4'          => array( '8.8.8.8', false ),
			'private_ipv4'       => array( '10.0.0.1', false ),
			'not_an_ip'          => array( 'not-an-ip', false ),
			'empty_string'       => array( '', false ),
		);
	}

	/**
	 * Test the IPv4-mapped IPv6 detector.
	 *
	 * @dataProvider is_ipv4_mapped_ipv6_provider
	 *
	 * @covers \Activitypub\is_ipv4_mapped_ipv6
	 *
	 * @param string $ip       The IP literal to check.
	 * @param bool   $expected Whether it's an IPv4-mapped IPv6 address.
	 */
	public function test_is_ipv4_mapped_ipv6( $ip, $expected ) {
		$this->assertSame( $expected, \Activitypub\is_ipv4_mapped_ipv6( $ip ) );
	}
}
