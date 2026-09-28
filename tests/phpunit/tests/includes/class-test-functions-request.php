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
	 * Data provider for accept_prefers_activitypub.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function accept_prefers_activitypub_provider() {
		return array(
			// ActivityPub is the highest-priority acceptable type -> true.
			'activity_json'         => array( 'application/activity+json', true ),
			'ld_json_https_profile' => array( 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', true ),
			'ld_json_http_profile'  => array( 'application/ld+json; profile="http://www.w3.org/ns/activitystreams"', true ),
			'activity_json_with_q'  => array( 'application/activity+json;q=0.9', true ),
			'uppercase'             => array( 'Application/Activity+JSON', true ),
			'trailing_comma'        => array( 'application/activity+json,', true ),
			'activity_then_ld'      => array( 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"', true ),
			// Mastodon: ActivityPub at q=1, text/html as a q=0.1 fallback.
			'mastodon'              => array( 'application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/activity+json, text/html;q=0.1', true ),
			// Ties broken by order; higher q wins; a refused (q=0) type is ignored.
			'ap_first_on_tie'       => array( 'application/activity+json, text/html', true ),
			'ap_higher_q'           => array( 'text/html;q=0.5, application/activity+json;q=0.8', true ),
			'html_refused_q0'       => array( 'application/activity+json;q=0.5, text/html;q=0', true ),
			// A malformed q (empty or non-numeric) keeps the 1.0 default rather than refusing the type.
			'malformed_q_empty'     => array( 'application/activity+json;q=', true ),
			'malformed_q_text'      => array( 'application/activity+json;q=high', true ),
			// An out-of-range q>1.0 must not let an earlier q=1.0 non-AP type win the whole header.
			'html_then_ap_q_over_1' => array( 'text/html, application/activity+json;q=1.5', true ),
			// Not ActivityPub: plain JSON, bare or wrongly-profiled ld+json, other +json -> false.
			'plain_json'            => array( 'application/json', false ),
			'ld_json_no_profile'    => array( 'application/ld+json', false ),
			'ld_json_wrong_profile' => array( 'application/ld+json; profile="https://example.com/ns"', false ),
			'other_plus_json'       => array( 'application/geo+json', false ),
			'html_first_on_tie'     => array( 'text/html, application/activity+json', false ),
			'html_higher_q'         => array( 'application/activity+json;q=0.5, text/html;q=0.8', false ),
			'ap_refused_q0'         => array( 'application/activity+json;q=0, text/html', false ),
			'browser'               => array( 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false ),
			'wildcard'              => array( '*/*', false ),
			'plain_html'            => array( 'text/html', false ),
			'empty'                 => array( '', false ),
			// Classifies the raw header: a `%00` breaks the exact match, matching the pre-plugin cache path.
			'percent_octet'         => array( 'application/activity+json%00', false ),
		);
	}

	/**
	 * Test the ActivityPub-preference Accept-header check.
	 *
	 * @dataProvider accept_prefers_activitypub_provider
	 *
	 * @covers \Activitypub\accept_prefers_activitypub
	 *
	 * @param string $accept   The Accept header value.
	 * @param bool   $expected Whether the highest-priority acceptable media type is ActivityPub.
	 */
	public function test_accept_prefers_activitypub( $accept, $expected ) {
		$this->assertSame( $expected, \Activitypub\accept_prefers_activitypub( $accept ) );
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

	/**
	 * Holds the $_SERVER values captured by snapshot_client_ip_server() so
	 * each test in this group can restore them in its own try/finally,
	 * keeping the suite order-independent.
	 *
	 * @var array<string, mixed>
	 */
	private $client_ip_server_snapshot = array();

	/**
	 * Capture the values of every $_SERVER key get_client_ip touches before
	 * each test in this group.
	 */
	private function snapshot_client_ip_server() {
		$keys                            = array(
			'REMOTE_ADDR',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
		);
		$this->client_ip_server_snapshot = array();
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Capturing existing test fixture values for restore.
			$this->client_ip_server_snapshot[ $key ] = \array_key_exists( $key, $_SERVER ) ? $_SERVER[ $key ] : null;
		}
	}

	/**
	 * Restore $_SERVER values captured by snapshot_client_ip_server.
	 */
	private function restore_client_ip_server() {
		foreach ( $this->client_ip_server_snapshot as $key => $value ) {
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $value;
			}
		}
	}

	/**
	 * Test get_client_ip returns REMOTE_ADDR by default.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_default() {
		$this->snapshot_client_ip_server();
		try {
			$_SERVER['REMOTE_ADDR'] = '192.168.1.1';
			$this->assertSame( '192.168.1.1', \Activitypub\get_client_ip() );
		} finally {
			$this->restore_client_ip_server();
		}
	}

	/**
	 * Test get_client_ip is filterable.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_filter() {
		$this->snapshot_client_ip_server();
		$filter = function () {
			return '203.0.113.50';
		};
		\add_filter( 'activitypub_client_ip', $filter );

		try {
			$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
			$this->assertSame( '203.0.113.50', \Activitypub\get_client_ip() );
		} finally {
			\remove_filter( 'activitypub_client_ip', $filter );
			$this->restore_client_ip_server();
		}
	}

	/**
	 * Test get_client_ip returns an empty string when REMOTE_ADDR is missing.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_missing_remote_addr() {
		$this->snapshot_client_ip_server();
		try {
			unset( $_SERVER['REMOTE_ADDR'] );
			$this->assertSame( '', \Activitypub\get_client_ip() );
		} finally {
			$this->restore_client_ip_server();
		}
	}

	/**
	 * Test get_client_ip rejects non-IP values from a filter so a misbehaving
	 * filter can't collapse all callers into one rate-limit bucket.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_rejects_invalid_filter_output() {
		$this->snapshot_client_ip_server();
		$filter = function () {
			return 'unknown';
		};
		\add_filter( 'activitypub_client_ip', $filter );

		try {
			$_SERVER['REMOTE_ADDR'] = '198.51.100.10';
			$this->assertSame( '', \Activitypub\get_client_ip() );
		} finally {
			\remove_filter( 'activitypub_client_ip', $filter );
			$this->restore_client_ip_server();
		}
	}

	/**
	 * Proxy headers must NOT be trusted by default. A site that PHP serves
	 * directly receives X-Forwarded-For / CF-Connecting-IP straight from the
	 * client, so trusting them by default would let an attacker rotate the
	 * spoofed value to bypass per-IP rate limits.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_ignores_proxy_headers_by_default() {
		$this->snapshot_client_ip_server();
		try {
			$_SERVER['REMOTE_ADDR']           = '10.0.0.1';
			$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.50';
			$_SERVER['HTTP_X_FORWARDED_FOR']  = '203.0.113.51';

			$this->assertSame( '10.0.0.1', \Activitypub\get_client_ip() );
		} finally {
			$this->restore_client_ip_server();
		}
	}

	/**
	 * Operators behind a trusted reverse proxy can opt the relevant header
	 * back in via the activitypub_client_ip_sources filter.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_honors_trusted_proxy_filter() {
		$this->snapshot_client_ip_server();
		$filter = function () {
			return array( 'HTTP_CF_CONNECTING_IP' );
		};
		\add_filter( 'activitypub_client_ip_sources', $filter );

		try {
			$_SERVER['REMOTE_ADDR']           = '10.0.0.1';
			$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.50';

			$this->assertSame( '203.0.113.50', \Activitypub\get_client_ip() );
		} finally {
			\remove_filter( 'activitypub_client_ip_sources', $filter );
			$this->restore_client_ip_server();
		}
	}

	/**
	 * When a configured source has a non-IP value, the next source in the
	 * filter's list is consulted — so a typo'd or temporarily missing proxy
	 * header doesn't lock all callers out of rate-limited endpoints.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_falls_back_when_trusted_header_invalid() {
		$this->snapshot_client_ip_server();
		$filter = function () {
			return array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		};
		\add_filter( 'activitypub_client_ip_sources', $filter );

		try {
			$_SERVER['REMOTE_ADDR']           = '198.51.100.10';
			$_SERVER['HTTP_CF_CONNECTING_IP'] = 'unknown';
			$_SERVER['HTTP_X_FORWARDED_FOR']  = '203.0.113.7';

			$this->assertSame( '203.0.113.7', \Activitypub\get_client_ip() );
		} finally {
			\remove_filter( 'activitypub_client_ip_sources', $filter );
			$this->restore_client_ip_server();
		}
	}

	/**
	 * X-Forwarded-For may carry "client, proxy1, proxy2" — when the operator
	 * trusts the proxy that overwrites the header, the leftmost entry is the
	 * client.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_uses_leftmost_of_xff_list() {
		$this->snapshot_client_ip_server();
		$filter = function () {
			return array( 'HTTP_X_FORWARDED_FOR' );
		};
		\add_filter( 'activitypub_client_ip_sources', $filter );

		try {
			$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
			$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 198.51.100.7';

			$this->assertSame( '203.0.113.50', \Activitypub\get_client_ip() );
		} finally {
			\remove_filter( 'activitypub_client_ip_sources', $filter );
			$this->restore_client_ip_server();
		}
	}

	/**
	 * A filter that returns null (or any other non-array) must not blow up;
	 * the function falls back to its safe default of REMOTE_ADDR only.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_tolerates_null_filter_return() {
		$this->snapshot_client_ip_server();
		$filter = function () {
			return null;
		};
		\add_filter( 'activitypub_client_ip_sources', $filter );

		try {
			$_SERVER['REMOTE_ADDR']           = '198.51.100.10';
			$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.50';

			$this->assertSame( '198.51.100.10', \Activitypub\get_client_ip() );
		} finally {
			\remove_filter( 'activitypub_client_ip_sources', $filter );
			$this->restore_client_ip_server();
		}
	}

	/**
	 * A caller may ask up to its allowance, and is refused after that.
	 *
	 * @covers \Activitypub\spend_rate_limit
	 */
	public function test_spend_rate_limit_counts_down_and_refuses() {
		$this->snapshot_client_ip_server();
		\wp_set_current_user( 0 );

		try {
			$_SERVER['REMOTE_ADDR'] = '203.0.113.20';

			foreach ( array( 2, 1, 0 ) as $remaining ) {
				$allowance = \Activitypub\spend_rate_limit( 'test_bucket', 3 );

				$this->assertSame( 3, $allowance['limit'] );
				$this->assertSame( $remaining, $allowance['remaining'] );
				$this->assertGreaterThan( \time(), $allowance['reset'] );
			}

			$refused = \Activitypub\spend_rate_limit( 'test_bucket', 3 );

			$this->assertWPError( $refused );
			$this->assertSame( 'activitypub_rate_limited', $refused->get_error_code() );
			$this->assertSame( 429, $refused->get_error_data()['status'] );
			$this->assertSame( 3, $refused->get_error_data()['limit'], 'The refusal carries what the allowance was.' );
		} finally {
			$this->restore_client_ip_server();
		}
	}

	/**
	 * Allowances are separate per bucket, per address and per account.
	 *
	 * @covers \Activitypub\spend_rate_limit
	 */
	public function test_spend_rate_limit_counts_per_caller_and_bucket() {
		$this->snapshot_client_ip_server();
		\wp_set_current_user( 0 );

		try {
			$_SERVER['REMOTE_ADDR'] = '203.0.113.21';

			$this->assertIsArray( \Activitypub\spend_rate_limit( 'test_bucket', 1 ) );
			$this->assertWPError( \Activitypub\spend_rate_limit( 'test_bucket', 1 ), 'The bucket is spent.' );

			$this->assertIsArray( \Activitypub\spend_rate_limit( 'other_bucket', 1 ), 'Another bucket has its own allowance.' );

			$_SERVER['REMOTE_ADDR'] = '203.0.113.22';
			$this->assertIsArray( \Activitypub\spend_rate_limit( 'test_bucket', 1 ), 'Another address has its own allowance.' );

			\wp_set_current_user( self::factory()->user->create() );
			$this->assertIsArray( \Activitypub\spend_rate_limit( 'test_bucket', 1 ), 'A signed-in caller is counted per account.' );
		} finally {
			\wp_set_current_user( 0 );
			$this->restore_client_ip_server();
		}
	}

	/**
	 * A caller that cannot be identified is refused rather than let through unlimited.
	 *
	 * @covers \Activitypub\spend_rate_limit
	 */
	public function test_spend_rate_limit_refuses_an_unidentifiable_caller() {
		$this->snapshot_client_ip_server();
		\wp_set_current_user( 0 );

		try {
			foreach ( array( 'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR' ) as $key ) {
				unset( $_SERVER[ $key ] );
			}

			$window = (int) \floor( \time() / MINUTE_IN_SECONDS );
			$key    = \sprintf( 'activitypub_rate_%s_%s_%d', 'test_bucket', '', $window );

			$refused = \Activitypub\spend_rate_limit( 'test_bucket', 10 );

			$this->assertWPError( $refused );
			$this->assertSame( 429, $refused->get_error_data()['status'] );
			$this->assertFalse( \get_transient( $key ), 'Unidentifiable callers share no bucket.' );
		} finally {
			$this->restore_client_ip_server();
		}
	}

	/**
	 * The window ends by itself instead of being pushed forward by every request.
	 *
	 * @covers \Activitypub\spend_rate_limit
	 */
	public function test_spend_rate_limit_uses_a_fixed_window() {
		$this->snapshot_client_ip_server();
		\wp_set_current_user( 0 );

		try {
			$_SERVER['REMOTE_ADDR'] = '203.0.113.23';

			\Activitypub\spend_rate_limit( 'test_bucket', 5 );

			$window  = (int) \floor( \time() / MINUTE_IN_SECONDS );
			$key     = \sprintf( 'activitypub_rate_%s_%s_%d', 'test_bucket', '203.0.113.23', $window );
			$timeout = (int) \get_option( '_transient_timeout_' . $key );

			$this->assertSame( 1, (int) \get_transient( $key ), 'The window holds the count.' );
			$this->assertLessThanOrEqual( \time() + MINUTE_IN_SECONDS, $timeout, 'It expires within the window.' );
		} finally {
			$this->restore_client_ip_server();
		}
	}
}
