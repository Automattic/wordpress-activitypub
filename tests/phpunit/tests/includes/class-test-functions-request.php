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
	 * Filter callbacks registered by stub_resolved_addresses(), removed in tear_down().
	 *
	 * @var callable[]
	 */
	private $stub_callbacks = array();

	/**
	 * Tear down registered stubs so they can't leak between tests.
	 */
	public function tear_down() {
		foreach ( $this->stub_callbacks as $callback ) {
			\remove_filter( 'activitypub_pre_resolve_public_host', $callback );
		}
		$this->stub_callbacks = array();

		parent::tear_down();
	}

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

	/**
	 * Inject a fixed set of resolved addresses for the next call so the test
	 * doesn't depend on real DNS. The registered filter is recorded and removed
	 * automatically in tear_down() so it can't leak into later tests.
	 *
	 * @param array $addresses ipv4/ipv6 lists to inject.
	 */
	private function stub_resolved_addresses( $addresses ) {
		$callback = static function () use ( $addresses ) {
			return $addresses;
		};

		$this->stub_callbacks[] = $callback;
		\add_filter( 'activitypub_pre_resolve_public_host', $callback );
	}

	/**
	 * Public IPv4 from DNS is preferred over the IPv6 fallback.
	 *
	 * @covers \Activitypub\resolve_public_host
	 */
	public function test_resolve_public_host_prefers_ipv4_when_both_exist() {
		$this->stub_resolved_addresses(
			array(
				'ipv4' => array( '93.184.216.34' ),
				'ipv6' => array( '2606:2800:220:1:248:1893:25c8:1946' ),
			)
		);

		$this->assertSame( '93.184.216.34', \Activitypub\resolve_public_host( 'example.com' ) );
	}

	/**
	 * IPv6 is returned when no A records resolve.
	 *
	 * @covers \Activitypub\resolve_public_host
	 */
	public function test_resolve_public_host_falls_through_to_ipv6() {
		$this->stub_resolved_addresses(
			array(
				'ipv4' => array(),
				'ipv6' => array( '2606:4700:4700::1111' ),
			)
		);

		$this->assertSame( '2606:4700:4700::1111', \Activitypub\resolve_public_host( 'ipv6.example' ) );
	}

	/**
	 * A single private IPv4 in the answer set rejects the whole resolution
	 * (split-horizon DNS defence).
	 *
	 * @covers \Activitypub\resolve_public_host
	 */
	public function test_resolve_public_host_rejects_split_horizon_ipv4() {
		$this->stub_resolved_addresses(
			array(
				'ipv4' => array( '93.184.216.34', '10.0.0.1' ),
				'ipv6' => array(),
			)
		);

		$this->assertFalse( \Activitypub\resolve_public_host( 'split.example' ) );
	}

	/**
	 * A single private IPv6 in the answer set rejects the whole resolution.
	 *
	 * @covers \Activitypub\resolve_public_host
	 */
	public function test_resolve_public_host_rejects_split_horizon_ipv6() {
		$this->stub_resolved_addresses(
			array(
				'ipv4' => array(),
				'ipv6' => array( '2606:4700:4700::1111', 'fc00::1' ),
			)
		);

		$this->assertFalse( \Activitypub\resolve_public_host( 'split6.example' ) );
	}

	/**
	 * IPv4-mapped IPv6 in the AAAA path rejects the whole resolution, just
	 * like the IP-literal path.
	 *
	 * @covers \Activitypub\resolve_public_host
	 */
	public function test_resolve_public_host_rejects_ipv4_mapped_aaaa() {
		$this->stub_resolved_addresses(
			array(
				'ipv4' => array(),
				'ipv6' => array( '::ffff:127.0.0.1' ),
			)
		);

		$this->assertFalse( \Activitypub\resolve_public_host( 'mapped.example' ) );
	}

	/**
	 * No resolved addresses (empty A and AAAA) yields false.
	 *
	 * @covers \Activitypub\resolve_public_host
	 */
	public function test_resolve_public_host_returns_false_for_unresolvable() {
		$this->stub_resolved_addresses(
			array(
				'ipv4' => array(),
				'ipv6' => array(),
			)
		);

		$this->assertFalse( \Activitypub\resolve_public_host( 'nx.example' ) );
	}
}
