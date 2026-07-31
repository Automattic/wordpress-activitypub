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
			'sixtofour_loopback'          => array( '2002:7f00:1::1', false ),
			'sixtofour_rfc1918'           => array( '2002:0a00:0001::1', false ),
			'teredo'                      => array( '2001:0:53aa:64c:18:7d:11ee:c4', false ),
			'documentation'               => array( '2001:db8::1', false ),
			'nat64_well_known'            => array( '64:ff9b::8.8.8.8', false ),
			'nat64_local_use'             => array( '64:ff9b:1::8.8.8.8', false ),
			'discard_prefix'              => array( '100::1', false ),
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
	 * The activitypub_allow_non_public_host filter opts a private-network deployment back in: a
	 * private address is returned as is instead of being rejected, but a host that does not resolve
	 * is still rejected.
	 *
	 * @covers \Activitypub\resolve_public_host
	 */
	public function test_resolve_public_host_allow_filter() {
		// Blocked by default.
		$this->assertFalse( \Activitypub\resolve_public_host( '10.0.0.1' ) );

		\add_filter( 'activitypub_allow_non_public_host', '__return_true' );

		// A private IP literal is now returned as is.
		$this->assertSame( '10.0.0.1', \Activitypub\resolve_public_host( '10.0.0.1' ) );

		// A private hostname resolves and is returned as is.
		$this->stub_resolved_addresses( array( 'ipv4' => array( '10.0.0.5' ) ) );
		$this->assertSame( '10.0.0.5', \Activitypub\resolve_public_host( 'intranet.example' ) );

		\remove_filter( 'activitypub_allow_non_public_host', '__return_true' );
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
	 * Data provider for is_json_only_accept.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function is_json_only_accept_provider() {
		return array(
			// Every media type is JSON -> true.
			'activity_json'        => array( 'application/activity+json', true ),
			'ld_json'              => array( 'application/ld+json', true ),
			'plain_json'           => array( 'application/json', true ),
			'ld_json_with_profile' => array( 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', true ),
			'multiple_json'        => array( 'application/activity+json, application/ld+json', true ),
			'json_with_q'          => array( 'application/activity+json;q=0.9', true ),
			'uppercase_json'       => array( 'Application/Activity+JSON', true ),
			'trailing_comma'       => array( 'application/activity+json,', true ),
			// At least one non-JSON media type -> false.
			'html_then_json'       => array( 'text/html, application/activity+json', false ),
			'json_then_html'       => array( 'application/activity+json, text/html', false ),
			'browser'              => array( 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false ),
			'wildcard'             => array( '*/*', false ),
			'plain_html'           => array( 'text/html', false ),
			'empty'                => array( '', false ),
			// Must classify the raw header, not a sanitized one, so it agrees with the pre-plugin cache
			// path: `%00` keeps this out of JSON where sanitize_text_field() would have stripped it.
			'percent_octet'        => array( 'application/activity+json%00', false ),
		);
	}

	/**
	 * Test the JSON-only Accept-header check.
	 *
	 * @dataProvider is_json_only_accept_provider
	 *
	 * @covers \Activitypub\is_json_only_accept
	 *
	 * @param string $accept   The Accept header value.
	 * @param bool   $expected Whether every listed media type is JSON.
	 */
	public function test_is_json_only_accept( $accept, $expected ) {
		$this->assertSame( $expected, \Activitypub\is_json_only_accept( $accept ) );
	}

	/**
	 * Data provider for is_unsafe_ipv6_literal.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function is_unsafe_ipv6_literal_provider() {
		return array(
			// IPv4-mapped IPv6 prefix.
			'mapped_loopback'        => array( '::ffff:127.0.0.1', true ),
			'mapped_rfc1918'         => array( '::ffff:10.0.0.1', true ),
			'mapped_public'          => array( '::ffff:8.8.8.8', true ),
			// 6to4 prefix; embeds IPv4 in the next 32 bits.
			'sixtofour_loopback'     => array( '2002:7f00:1::1', true ),
			'sixtofour_rfc1918'      => array( '2002:0a00:0001::1', true ),
			'sixtofour_public_embed' => array( '2002:0808:0808::1', true ),
			// Teredo prefix.
			'teredo'                 => array( '2001:0:53aa:64c:18:7d:11ee:c4', true ),
			// Documentation prefix.
			'documentation'          => array( '2001:db8::1', true ),
			'documentation_long'     => array( '2001:0db8:85a3::8a2e:0370:7334', true ),
			// NAT64 well-known prefix.
			'nat64_well_known'       => array( '64:ff9b::8.8.8.8', true ),
			// NAT64 local-use prefix.
			'nat64_local_use'        => array( '64:ff9b:1::8.8.8.8', true ),
			// Discard prefix.
			'discard_prefix'         => array( '100::1', true ),
			// Routable IPv6, unaffected.
			'pure_ipv6_public'       => array( '2606:4700:4700::1111', false ),
			'google_dns_v6'          => array( '2001:4860:4860::8888', false ),
			// Loopback caught elsewhere by NO_RES_RANGE, not by this helper.
			'pure_ipv6_loopback'     => array( '::1', false ),
			// Non-IPv6 input.
			'pure_ipv4'              => array( '8.8.8.8', false ),
			'private_ipv4'           => array( '10.0.0.1', false ),
			'not_an_ip'              => array( 'not-an-ip', false ),
			'empty_string'           => array( '', false ),
		);
	}

	/**
	 * Test the unsafe IPv6 literal detector.
	 *
	 * @dataProvider is_unsafe_ipv6_literal_provider
	 *
	 * @covers \Activitypub\is_unsafe_ipv6_literal
	 *
	 * @param string $ip       The IP literal to check.
	 * @param bool   $expected Whether it's in an unsafe IPv6 range.
	 */
	public function test_is_unsafe_ipv6_literal( $ip, $expected ) {
		$this->assertSame( $expected, \Activitypub\is_unsafe_ipv6_literal( $ip ) );
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
