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
			'upper'   => array( 'CAFÉ.example', 'xn--caf-dma.example' ),
			'a-label' => array( 'xn--caf-dma.example', 'xn--caf-dma.example' ),
			'ascii'   => array( 'Example.org', 'Example.org' ),
			'ipv6'    => array( '[2001:db8::1]', '[2001:db8::1]' ),
			'empty'   => array( '', '' ),
		);
	}

	/**
	 * A decomposed accent gives the same A-label as the composed one.
	 *
	 * @requires extension intl
	 * @covers \Activitypub\to_ascii_host
	 */
	public function test_to_ascii_host_normalizes_decomposed_hosts() {
		$this->assertSame( 'xn--caf-dma.example', \Activitypub\to_ascii_host( "cafe\u{0301}.example" ) );
	}

	/**
	 * A fullwidth host gives the same A-label as its plain form.
	 *
	 * @requires extension intl
	 * @covers \Activitypub\to_ascii_host
	 */
	public function test_to_ascii_host_maps_fullwidth_hosts() {
		$this->assertSame( 'xn--caf-dma.example', \Activitypub\to_ascii_host( 'ｃａｆé.example' ) );
	}

	/**
	 * A label that UTS #46 maps to plain ASCII is not folded onto that ASCII host.
	 *
	 * @requires extension intl
	 * @covers \Activitypub\to_ascii_host
	 * @covers \Activitypub\fold_host
	 * @dataProvider mapped_away_host_provider
	 *
	 * @param string $host The host.
	 */
	public function test_to_ascii_host_keeps_hosts_mapped_to_ascii( $host ) {
		$this->assertSame( $host, \Activitypub\to_ascii_host( $host ) );
		$this->assertNotSame( \Activitypub\fold_host( 'example.com' ), \Activitypub\fold_host( $host ) );
	}

	/**
	 * Hosts with labels that UTS #46 maps to plain ASCII.
	 *
	 * @return array[]
	 */
	public function mapped_away_host_provider() {
		return array(
			'soft hyphen'          => array( "exam\u{00AD}ple.com" ),
			'zero width space'     => array( "exam\u{200B}ple.com" ),
			'fullwidth latin'      => array( 'ｅｘａｍｐｌｅ.com' ),
			'next to an idn label' => array( "café.exam\u{00AD}ple.com" ),
		);
	}

	/**
	 * An ideographic full stop at the end is trimmed like an ASCII dot.
	 *
	 * @requires extension intl
	 * @covers \Activitypub\fold_host
	 */
	public function test_fold_host_trims_ideographic_trailing_dot() {
		$this->assertSame( 'xn--caf-dma.example', \Activitypub\fold_host( 'café.example。' ) );
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

	/**
	 * Only a host this site answers on, or one added through the filter, may be signed for.
	 *
	 * @covers \Activitypub\is_signature_host
	 * @dataProvider signature_host_provider
	 *
	 * @param string $authority The authority, with `%s` standing for this site's host.
	 * @param bool   $expected  The expected result.
	 */
	public function test_is_signature_host( $authority, $expected ) {
		$allow = function ( $hosts ) {
			$hosts[] = 'Mapped.Example';

			return $hosts;
		};
		\add_filter( 'activitypub_signature_original_hosts', $allow );

		$result = \Activitypub\is_signature_host( \sprintf( $authority, \Activitypub\home_host() ) );

		\remove_filter( 'activitypub_signature_original_hosts', $allow );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Authorities for test_is_signature_host.
	 *
	 * @return array[]
	 */
	public function signature_host_provider() {
		return array(
			'home host'                 => array( '%s', true ),
			'home host with a port'     => array( '%s:8080', true ),
			'filtered host'             => array( 'mapped.example', true ),
			'filtered host, case, port' => array( 'MAPPED.EXAMPLE:443', true ),
			'foreign host'              => array( 'evil.example', false ),
			'home host as a subdomain'  => array( 'evil.%s', false ),
			'empty'                     => array( '', false ),
			'a port without a host'     => array( ':8080', false ),
		);
	}

	/**
	 * The authority keeps a port only when it is not the scheme's default, like `Host`.
	 *
	 * @covers \Activitypub\get_authority
	 * @dataProvider authority_provider
	 *
	 * @param string $url      The URL.
	 * @param string $expected The expected authority.
	 */
	public function test_get_authority( $url, $expected ) {
		$this->assertSame( $expected, \Activitypub\get_authority( $url ) );
	}

	/**
	 * URLs for test_get_authority.
	 *
	 * @return array[]
	 */
	public function authority_provider() {
		return array(
			'no port'              => array( 'https://example.org/inbox', 'example.org' ),
			'default https port'   => array( 'https://example.org:443/inbox', 'example.org' ),
			'default http port'    => array( 'http://example.org:80/inbox', 'example.org' ),
			'non-default port'     => array( 'https://example.org:8443/inbox', 'example.org:8443' ),
			'other scheme default' => array( 'http://example.org:443/inbox', 'example.org:443' ),
			'ipv6 with a port'     => array( 'https://[2001:db8::1]:8443/', '[2001:db8::1]:8443' ),
			'no host'              => array( '/inbox', '' ),
		);
	}
}
