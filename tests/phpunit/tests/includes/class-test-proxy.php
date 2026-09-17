<?php
/**
 * Test file for the Proxy class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Http;
use Activitypub\Proxy;

/**
 * Test class for the Proxy class.
 *
 * @coversDefaultClass \Activitypub\Proxy
 */
class Test_Proxy extends \WP_UnitTestCase {
	/**
	 * The number of HTTP requests the stub answered.
	 *
	 * @var int
	 */
	private $requests = 0;

	/**
	 * What the stub answers, keyed by URL: an array to serve as JSON, or an int status code.
	 *
	 * @var array<string, array|int>
	 */
	private $responses = array();

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->requests  = 0;
		$this->responses = array();
		\add_filter( 'pre_http_request', array( $this, 'stub_request' ), 10, 3 );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		\remove_filter( 'pre_http_request', array( $this, 'stub_request' ) );

		parent::tear_down();
	}

	/**
	 * Answer requests from the stub table.
	 *
	 * @param false|array $pre  The pre-empted response.
	 * @param array       $args The request arguments.
	 * @param string      $url  The URL.
	 *
	 * @return array The response.
	 */
	public function stub_request( $pre, $args, $url ) {
		++$this->requests;
		$answer = $this->responses[ $url ] ?? 404;

		if ( \is_int( $answer ) ) {
			return array(
				'response' => array( 'code' => $answer ),
				'body'     => '',
				'headers'  => array(),
			);
		}

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode( $answer ),
			'headers'  => array( 'content-type' => 'application/activity+json' ),
		);
	}

	/**
	 * A fetched object is served from the cache afterwards.
	 *
	 * @covers ::get
	 */
	public function test_get_fetches_once_and_then_serves_from_cache() {
		$id                     = 'https://example.com/notes/1';
		$this->responses[ $id ] = array(
			'id'      => $id,
			'type'    => 'Note',
			'content' => 'Hi',
		);

		$first  = Proxy::get( $id );
		$second = Proxy::get( $id );

		$this->assertSame( 'Hi', $first['content'] );
		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->requests );
	}

	/**
	 * A failed fetch is remembered for a short time instead of being retried on every call.
	 *
	 * @covers ::get
	 */
	public function test_get_caches_a_failure() {
		$id = 'https://example.com/notes/gone';

		$first  = Proxy::get( $id );
		$second = Proxy::get( $id );

		$this->assertWPError( $first );
		$this->assertSame( 404, $first->get_error_data()['status'] );
		$this->assertWPError( $second );
		$this->assertSame( 1, $this->requests );
	}

	/**
	 * The cache can be bypassed per call.
	 *
	 * @covers ::get
	 */
	public function test_get_can_bypass_the_cache() {
		$id                     = 'https://example.com/notes/1';
		$this->responses[ $id ] = array(
			'id'   => $id,
			'type' => 'Note',
		);

		Proxy::get( $id );
		Proxy::get( $id, array( 'cached' => false ) );

		$this->assertSame( 2, $this->requests );
	}

	/**
	 * Deleting an entry forces the next call to fetch again.
	 *
	 * @covers ::delete
	 * @covers ::refresh
	 */
	public function test_delete_and_refresh_fetch_again() {
		$id                     = 'https://example.com/notes/1';
		$this->responses[ $id ] = array(
			'id'      => $id,
			'type'    => 'Note',
			'content' => 'v1',
		);

		Proxy::get( $id );
		Proxy::delete( $id );
		$this->responses[ $id ]['content'] = 'v2';

		$this->assertSame( 'v2', Proxy::get( $id )['content'] );
		$this->assertSame( 2, $this->requests );

		$this->responses[ $id ]['content'] = 'v3';
		$this->assertSame( 'v3', Proxy::refresh( $id )['content'] );
		$this->assertSame( 3, $this->requests );
	}

	/**
	 * The existing pre filter still answers before any fetch or cache lookup.
	 *
	 * @covers ::get
	 */
	public function test_pre_filter_is_honored() {
		\add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $pre, $url ) {
				return array(
					'id'      => $url,
					'type'    => 'Note',
					'stubbed' => true,
				);
			},
			10,
			2
		);

		$object = Proxy::get( 'https://example.com/notes/1' );

		$this->assertTrue( $object['stubbed'] );
		$this->assertSame( 0, $this->requests );
	}

	/**
	 * An acct identifier is resolved through WebFinger first.
	 *
	 * @covers ::get
	 */
	public function test_get_resolves_an_acct() {
		$actor = 'https://example.com/users/alice';

		$this->responses['https://example.com/.well-known/webfinger?resource=acct%3Aalice%40example.com'] = array(
			'subject' => 'acct:alice@example.com',
			'links'   => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => $actor,
				),
			),
		);
		$this->responses[ $actor ] = array(
			'id'   => $actor,
			'type' => 'Person',
		);

		$this->assertSame( 'Person', Proxy::get( 'alice@example.com' )['type'] );
	}

	/**
	 * An object served under a different id than requested is stored under its own id too.
	 *
	 * @covers ::get
	 */
	public function test_get_caches_under_the_declared_id() {
		$requested = 'https://example.com/@alice/1';
		$declared  = 'https://example.com/users/alice/statuses/1';

		$this->responses[ $requested ] = array(
			'id'   => $declared,
			'type' => 'Note',
		);
		$this->responses[ $declared ]  = array(
			'id'   => $declared,
			'type' => 'Note',
		);

		Proxy::get( $requested );
		Proxy::get( $declared );

		$this->assertSame( 2, $this->requests, 'The re-fetch of the declared id is the only second request.' );
	}

	/**
	 * The old entry point goes through the proxy and its cache.
	 *
	 * @covers \Activitypub\Http::get_remote_object
	 */
	public function test_http_get_remote_object_forwards_to_the_proxy() {
		$id                     = 'https://example.com/notes/1';
		$this->responses[ $id ] = array(
			'id'   => $id,
			'type' => 'Note',
		);

		Http::get_remote_object( $id );
		Proxy::get( $id );

		$this->assertSame( 1, $this->requests );
	}
}
