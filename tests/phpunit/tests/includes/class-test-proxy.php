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
	use Remote_Request_Stub;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->stub_remote_requests();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		$this->unstub_remote_requests();
		\wp_using_ext_object_cache( false );

		parent::tear_down();
	}

	/**
	 * Serve a Note at a URL.
	 *
	 * @param string $id The id, also the URL.
	 *
	 * @return string The id.
	 */
	private function note( $id ) {
		$this->responses[ $id ] = array(
			'id'      => $id,
			'type'    => 'Note',
			'content' => 'Hi',
		);

		return $id;
	}

	/**
	 * A fetched object is served from the cache afterwards.
	 *
	 * @covers ::get
	 */
	public function test_get_fetches_once_and_then_serves_from_cache() {
		$id = $this->note( 'https://example.com/notes/1' );

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
	 * Bypassing the cache fetches again, and the fresh copy is stored.
	 *
	 * @covers ::get
	 */
	public function test_get_can_bypass_the_cache_and_still_stores() {
		$id = $this->note( 'https://example.com/notes/1' );

		Proxy::get( $id );
		Proxy::get( $id, array( 'cached' => false ) );
		Proxy::get( $id );

		$this->assertSame( 2, $this->requests );
	}

	/**
	 * Deleting an entry forces the next call to fetch again.
	 *
	 * @covers ::delete
	 */
	public function test_delete_fetches_again() {
		$id = $this->note( 'https://example.com/notes/1' );

		Proxy::get( $id );
		Proxy::delete( $id );
		$this->responses[ $id ]['content'] = 'v2';

		$this->assertSame( 'v2', Proxy::get( $id )['content'] );
		$this->assertSame( 2, $this->requests );
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
	 * A key id shares the entry of the actor it belongs to.
	 *
	 * @covers ::get
	 */
	public function test_get_ignores_the_fragment() {
		$actor = 'https://example.com/users/alice';

		$this->responses[ $actor ] = array(
			'id'   => $actor,
			'type' => 'Person',
		);

		Proxy::get( $actor . '#main-key' );
		Proxy::get( $actor );

		$this->assertSame( 1, $this->requests );
	}

	/**
	 * An object requested by another URL is stored under its declared id, with an alias
	 * for the requested URL, so dropping the id drops the alias too.
	 *
	 * @covers ::get
	 * @covers ::delete
	 */
	public function test_get_stores_under_the_declared_id_and_aliases_the_requested_url() {
		$requested = 'https://example.com/@alice/1';
		$declared  = $this->note( 'https://example.com/users/alice/statuses/1' );

		$this->responses[ $requested ] = $this->responses[ $declared ];

		Proxy::get( $requested );
		$this->assertSame( 2, $this->requests, 'The declared id confirms itself in a second request.' );

		Proxy::get( $declared );
		Proxy::get( $requested );
		$this->assertSame( 2, $this->requests, 'Both spellings are served from the cache.' );

		Proxy::delete( $declared );
		Proxy::get( $requested );
		$this->assertSame( 4, $this->requests, 'Dropping the id retires the alias.' );
	}

	/**
	 * An object reached through a cross-host redirect is cached under its own id only.
	 *
	 * @covers ::get
	 */
	public function test_get_does_not_alias_a_cross_host_redirect() {
		$requested = 'https://example.com/redirect';
		$declared  = 'https://example.org/notes/1';

		$this->responses[ $requested ] = $this->redirected(
			$declared,
			array(
				'id'   => $declared,
				'type' => 'Note',
			)
		);

		$this->assertSame( 'Note', Proxy::get( $requested )['type'] );
		Proxy::get( $requested );
		$this->assertSame( 2, $this->requests, 'The requested URL is fetched again.' );

		Proxy::get( $declared );
		$this->assertSame( 2, $this->requests, 'The declared id is served from the cache.' );
	}

	/**
	 * A failure reached through a cross-host redirect is not remembered for the requested URL.
	 *
	 * @covers ::get
	 */
	public function test_get_does_not_cache_a_cross_host_redirected_failure() {
		$requested = 'https://example.com/redirect';

		$this->responses[ $requested ] = $this->redirected( 'https://example.org/gone', 404 );

		$this->assertWPError( Proxy::get( $requested ) );
		$this->assertWPError( Proxy::get( $requested ) );
		$this->assertSame( 2, $this->requests );
	}

	/**
	 * Without a persistent object cache the entry lives in a transient.
	 *
	 * @covers ::get
	 */
	public function test_get_caches_in_a_transient_without_a_persistent_object_cache() {
		$id = $this->note( 'https://example.com/notes/1' );

		Proxy::get( $id );

		$this->assertNotFalse( \get_transient( 'activitypub_object:' . \hash( 'sha256', $id ) ) );
	}

	/**
	 * With a persistent object cache the entry lives there and no transient is written.
	 *
	 * @covers ::get
	 */
	public function test_get_caches_in_the_object_cache_when_there_is_one() {
		\wp_using_ext_object_cache( true );
		$id = $this->note( 'https://example.com/notes/1' );

		Proxy::get( $id );
		Proxy::get( $id );

		$this->assertSame( 1, $this->requests );
		$this->assertNotFalse( \wp_cache_get( 'object:' . \hash( 'sha256', $id ), 'activitypub' ) );
		$this->assertFalse( \get_option( '_transient_activitypub_object:' . \hash( 'sha256', $id ) ) );
	}

	/**
	 * The old entry point goes through the proxy and its cache.
	 *
	 * @covers \Activitypub\Http::get_remote_object
	 */
	public function test_http_get_remote_object_forwards_to_the_proxy() {
		$id = $this->note( 'https://example.com/notes/1' );

		Http::get_remote_object( $id );
		Proxy::get( $id );

		$this->assertSame( 1, $this->requests );
	}

	/**
	 * A document without an id reached through a cross-host redirect is not cached under the requested URL.
	 *
	 * @covers ::get
	 */
	public function test_get_does_not_cache_an_idless_document_from_another_host() {
		$requested = 'https://example.com/redirect';

		$this->responses[ $requested ] = $this->redirected( 'https://example.org/key.json', array( 'publicKeyPem' => 'PEM' ) );

		Proxy::get( $requested );
		Proxy::get( $requested );

		$this->assertSame( 2, $this->requests );
	}

	/**
	 * A remote document cannot pose as an alias, whatever keys it carries.
	 *
	 * @covers ::get
	 */
	public function test_a_document_cannot_forge_an_alias() {
		$id                     = 'https://example.com/notes/1';
		$this->responses[ $id ] = array(
			'id'      => $id,
			'type'    => 'Note',
			'__alias' => array( 'https://example.org/users/victim' ),
		);

		$first  = Proxy::get( $id );
		$second = Proxy::get( $id );

		$this->assertSame( $id, $first['id'] );
		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->requests );
	}

	/**
	 * A call that bypasses the cache never leaves a failure behind for the others.
	 *
	 * @covers ::get
	 */
	public function test_bypass_does_not_cache_a_failure() {
		$id = 'https://example.com/notes/gone';

		$this->assertWPError( Proxy::get( $id, array( 'cached' => false ) ) );
		$this->assertWPError( Proxy::get( $id ) );
		$this->assertSame( 2, $this->requests );
	}

	/**
	 * A media object is deleted by its id, not by the file it points at.
	 *
	 * @covers ::delete
	 */
	public function test_delete_keys_a_media_object_on_its_id() {
		$id                     = 'https://example.com/photos/1';
		$this->responses[ $id ] = array(
			'id'   => $id,
			'type' => 'Image',
			'url'  => 'https://cdn.example.com/1.jpg',
		);

		Proxy::get( $id );
		Proxy::delete( $this->responses[ $id ] );
		Proxy::get( $id );

		$this->assertSame( 2, $this->requests );
	}

	/**
	 * A document that is not JSON is remembered like any other client error, not retried every minute.
	 *
	 * @covers ::get
	 */
	public function test_invalid_json_is_remembered_like_a_client_error() {
		$id                     = 'https://example.com/notes/html';
		$this->responses[ $id ] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<html></html>',
			'headers'  => array(),
		);

		$this->assertWPError( Proxy::get( $id ) );

		$timeout = (int) \get_option( '_transient_timeout_activitypub_object:' . \hash( 'sha256', $id ) );
		$this->assertGreaterThan( \time() + 10 * MINUTE_IN_SECONDS, $timeout );
	}

	/**
	 * When the requested host serves a document that fails to confirm on its declared host,
	 * that failure is remembered for the requested URL.
	 *
	 * @covers ::get
	 */
	public function test_a_failed_second_hop_is_remembered_for_the_requested_url() {
		$requested = 'https://example.com/o';
		$declared  = 'https://example.org/users/v';

		$this->responses[ $requested ] = array(
			'id'   => $declared,
			'type' => 'Note',
		);

		$this->assertWPError( Proxy::get( $requested ) );
		$this->assertSame( 2, $this->requests, 'Both hops were fetched once.' );

		$this->assertWPError( Proxy::get( $requested ) );
		$this->assertSame( 2, $this->requests, 'The failure is served from the cache.' );
	}

	/**
	 * A lifetime of zero writes nothing rather than an entry that never expires.
	 *
	 * @covers ::get
	 */
	public function test_a_zero_ttl_writes_nothing() {
		$id = $this->note( 'https://example.com/notes/1' );
		\add_filter( 'activitypub_proxy_cache_ttl', '__return_zero' );

		Proxy::get( $id );
		Proxy::get( $id );

		$this->assertSame( 2, $this->requests );
		$this->assertFalse( \get_option( '_transient_activitypub_object:' . \hash( 'sha256', $id ) ) );
	}
}
