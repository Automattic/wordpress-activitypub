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
	 * Transport failures back off briefly and are retried after expiry.
	 *
	 * @dataProvider transport_failure_provider
	 * @covers ::get
	 *
	 * @param string $message The transport error message.
	 */
	public function test_get_caches_transport_failures( $message ) {
		$id                     = 'https://example.com/notes/unreachable';
		$this->responses[ $id ] = new \WP_Error( 'http_request_failed', $message );
		$started                = \time();

		$first  = Proxy::get( $id );
		$second = Proxy::get( $id );

		$this->assertWPError( $first );
		$this->assertWPError( $second );
		$this->assertSame( $message, $second->get_error_message() );
		$this->assertSame( 1, $this->requests );

		$key     = 'activitypub_object_' . \hash( 'sha256', $id );
		$expires = (int) \get_option( '_transient_timeout_' . $key );
		$this->assertGreaterThanOrEqual( $started + MINUTE_IN_SECONDS, $expires );
		$this->assertLessThanOrEqual( \time() + MINUTE_IN_SECONDS, $expires );

		\update_option( '_transient_timeout_' . $key, \time() - 1 );
		$this->note( $id );

		$this->assertSame( 'Hi', Proxy::get( $id )['content'] );
		$this->assertSame( 2, $this->requests, 'An expired failure is retried.' );
	}

	/**
	 * Transport failures exposed by the WordPress HTTP API.
	 *
	 * @return array The error messages.
	 */
	public function transport_failure_provider() {
		return array(
			'DNS'     => array( 'Could not resolve host.' ),
			'TLS'     => array( 'SSL certificate problem.' ),
			'timeout' => array( 'Connection timed out.' ),
		);
	}

	/**
	 * Bypassing the cache does not remember a transport failure.
	 *
	 * @covers ::get
	 */
	public function test_bypass_does_not_cache_a_transport_failure() {
		$id                     = 'https://example.com/notes/unreachable';
		$this->responses[ $id ] = new \WP_Error( 'http_request_failed', 'Connection timed out.' );

		$this->assertWPError( Proxy::get( $id, array( 'cached' => false ) ) );
		$this->note( $id );

		$this->assertSame( 'Hi', Proxy::get( $id )['content'] );
		$this->assertSame( 2, $this->requests );
	}

	/**
	 * A transport failure after a redirect is remembered only on the same host.
	 *
	 * @dataProvider redirected_transport_failure_provider
	 * @covers ::get
	 *
	 * @param string $target   The redirect target.
	 * @param int    $requests The expected number of requests after two calls.
	 */
	public function test_get_tracks_redirected_transport_failures( $target, $requests ) {
		$id                     = 'https://example.com/notes/redirect';
		$this->responses[ $id ] = new \WP_Error( 'http_request_failed', 'Connection timed out.' );
		$redirect               = static function ( $pre, $args, $url ) use ( $id, $target ) {
			if ( $url === $id ) {
				$response      = new \WpOrg\Requests\Response();
				$response->url = $url;
				\do_action( 'requests-requests.before_redirect', $target, array(), null, array(), $response ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress bridges this Requests hook verbatim.
			}
			return $pre;
		};
		\add_filter( 'pre_http_request', $redirect, 11, 3 );

		$first  = Proxy::get( $id );
		$second = Proxy::get( $id );
		\remove_filter( 'pre_http_request', $redirect, 11 );

		$this->assertWPError( $first );
		$this->assertWPError( $second );
		$this->assertSame( $requests, $this->requests );
		$this->assertFalse( \has_action( 'requests-requests.before_redirect' ), 'The request observer is removed after the fetch.' );
	}

	/**
	 * Same-host and cross-host redirect targets.
	 *
	 * @return array The redirect targets and expected request counts.
	 */
	public function redirected_transport_failure_provider() {
		return array(
			'same host'  => array( 'https://example.com/notes/unreachable', 1 ),
			'other host' => array( 'https://example.org/notes/unreachable', 2 ),
		);
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
	 * @covers ::purge
	 */
	public function test_purge_fetches_again() {
		$id = $this->note( 'https://example.com/notes/1' );

		Proxy::get( $id );
		Proxy::purge( $id );
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

		Proxy::purge( $declared );
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
	 * An alias requires known, same-host origins for both fetches.
	 *
	 * @dataProvider verification_origin_provider
	 * @covers ::get
	 *
	 * @param string $first_origin The first response's origin, or empty when unknown.
	 * @param string $declared     The canonical object id.
	 * @param bool   $final_known  Whether the canonical response names its origin.
	 * @param bool   $alias        Whether the requested URL may be aliased.
	 */
	public function test_get_preserves_both_verification_origins( $first_origin, $declared, $final_known, $alias ) {
		$requested                     = 'https://example.com/redirect';
		$object                        = array(
			'id'   => $declared,
			'type' => 'Note',
		);
		$this->responses[ $requested ] = $this->redirected( $first_origin, $object );
		$this->responses[ $declared ]  = $this->redirected( $declared, $object );
		if ( '' === $first_origin ) {
			unset( $this->responses[ $requested ]['http_response'] );
		}
		if ( ! $final_known ) {
			unset( $this->responses[ $declared ]['http_response'] );
		}

		$this->assertSame( $object, Proxy::get( $requested ) );
		$this->assertSame( 2, $this->requests, 'The declared id confirms itself.' );
		$this->assertSame( $alias ? $declared : false, \get_transient( 'activitypub_object_' . \hash( 'sha256', $requested ) ) );
		$this->assertSame( $final_known ? $object : false, \get_transient( 'activitypub_object_' . \hash( 'sha256', $declared ) ) );

		$this->assertSame( $object, Proxy::get( $requested ) );
		$this->assertSame( $alias ? 2 : 4, $this->requests, 'Only a safe alias can bypass fetching the requested URL.' );
		$this->assertSame( $object, Proxy::get( $declared ) );
		$this->assertSame( ( $alias ? 2 : 4 ) + ( $final_known ? 0 : 1 ), $this->requests );
	}

	/**
	 * First and confirming response origins.
	 *
	 * @return array The origins and expected alias decisions.
	 */
	public function verification_origin_provider() {
		return array(
			'cross-host first, same-host confirmation' => array( 'https://example.org/document', 'https://example.com/notes/1', true, false ),
			'unknown first, known confirmation'        => array( '', 'https://example.com/notes/1', true, false ),
			'same-host first, unknown confirmation'    => array( 'https://example.com/document', 'https://example.com/notes/1', false, false ),
			'same-host first and confirmation'         => array( 'https://example.com/document', 'https://example.com/notes/1', true, true ),
			'same-host first, cross-host confirmation' => array( 'https://example.com/document', 'https://example.org/notes/1', true, false ),
		);
	}

	/**
	 * Failed confirmation belongs to the first response's origin, not the declared id.
	 *
	 * @dataProvider verification_failure_origin_provider
	 * @covers ::get
	 *
	 * @param string $first_origin The first response's origin, or empty when unknown.
	 * @param bool   $mismatch     Whether confirmation returns a mismatched object instead of an error.
	 * @param int    $requests     The expected request count after two calls.
	 */
	public function test_failed_confirmation_preserves_first_origin( $first_origin, $mismatch, $requests ) {
		$requested                     = 'https://example.com/redirect';
		$declared                      = 'https://example.com/notes/1';
		$this->responses[ $requested ] = $this->redirected( $first_origin, array( 'id' => $declared ) );
		if ( '' === $first_origin ) {
			unset( $this->responses[ $requested ]['http_response'] );
		}
		$this->responses[ $declared ] = $mismatch ? array( 'id' => 'https://example.com/notes/other' ) : 404;

		$this->assertWPError( Proxy::get( $requested ) );
		$this->assertWPError( Proxy::get( $requested ) );
		$this->assertSame( $requests, $this->requests );
	}

	/**
	 * Confirmation errors and mismatches for known and unknown first origins.
	 *
	 * @return array The origins, failure modes, and expected request counts.
	 */
	public function verification_failure_origin_provider() {
		return array(
			'same-host HTTP error'  => array( 'https://example.com/document', false, 2 ),
			'cross-host HTTP error' => array( 'https://example.org/document', false, 4 ),
			'unknown HTTP error'    => array( '', false, 4 ),
			'same-host mismatch'    => array( 'https://example.com/document', true, 2 ),
			'cross-host mismatch'   => array( 'https://example.org/document', true, 4 ),
			'unknown mismatch'      => array( '', true, 4 ),
		);
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

		$this->assertNotFalse( \get_transient( 'activitypub_object_' . \hash( 'sha256', $id ) ) );
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

		$this->assertSame( 1, $this->requests, 'The second call is answered from the cache.' );

		// With a persistent object cache, a transient lives there and not in the options table.
		$this->assertFalse( \get_option( '_transient_activitypub_object_' . \hash( 'sha256', $id ) ) );
	}

	/**
	 * Inline actors can retire their own host's entries, but not another host's.
	 *
	 * @dataProvider inline_actor_provider
	 * @covers ::delete
	 *
	 * @param array $actor    The embedded actor reference.
	 * @param bool  $expected Whether it may retire the entry.
	 */
	public function test_delete_validates_inline_actors( $actor, $expected ) {
		$id = $this->note( 'https://example.com/notes/inline-actor' );
		Proxy::get( $id );

		$this->assertSame( $expected, Proxy::delete( $id, $actor ) );
		Proxy::get( $id );
		$this->assertSame( $expected ? 2 : 1, $this->requests );
	}

	/**
	 * Embedded actors used to authorize cache invalidation.
	 *
	 * @return array The actor references and expected decisions.
	 */
	public function inline_actor_provider() {
		return array(
			'same host'    => array(
				array(
					'id'   => 'https://example.com/users/alice',
					'type' => 'Person',
				),
				true,
			),
			'foreign host' => array(
				array(
					'id'   => 'https://example.org/users/mallory',
					'type' => 'Person',
				),
				false,
			),
			'missing id'   => array( array( 'type' => 'Person' ), false ),
		);
	}

	/**
	 * A media-typed object cannot retire an entry on another host.
	 *
	 * `object_to_uri()` answers with `url` for an `Image` while the cache is keyed on `id`, so a
	 * gate that compared one and evicted the other could be handed two different hosts. The check
	 * and the eviction share one value for that reason.
	 *
	 * @covers ::delete
	 */
	public function test_delete_refuses_a_media_object_from_another_host() {
		$id                     = 'https://example.com/notes/1';
		$this->responses[ $id ] = array(
			'id'   => $id,
			'type' => 'Note',
		);

		Proxy::get( $id );
		$before = $this->requests;

		$refused = Proxy::delete(
			array(
				'type' => 'Image',
				'id'   => $id,
				'url'  => 'https://example.org/mallory.png',
			),
			'https://example.org/users/mallory'
		);

		Proxy::get( $id );

		$this->assertFalse( $refused, 'The eviction is refused.' );
		$this->assertSame( $before, $this->requests, "The victim's entry is still served from the cache." );
	}

	/**
	 * An id that carries a fragment is stored under the name the lookups use.
	 *
	 * A key id like `…#main-key` dereferences to the actor document, so the entry has to be
	 * reachable, and retirable, under the actor's own name rather than under a second key that
	 * only the fragment spelling would find.
	 *
	 * @covers ::get
	 * @covers ::delete
	 */
	public function test_an_id_with_a_fragment_is_stored_under_one_name() {
		$actor                     = 'https://example.com/actor';
		$key_id                    = $actor . '#main-key';
		$this->responses[ $actor ] = array(
			'id'   => $key_id,
			'type' => 'Person',
		);

		Proxy::get( $key_id );

		global $wpdb;
		$entries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_activitypub_object_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( 1, $entries, 'The document is stored once.' );

		$before = $this->requests;
		Proxy::get( $key_id );
		$this->assertSame( $before, $this->requests, 'The second call is answered from the cache.' );

		// Retiring the actor retires the entry the key id shares with it.
		Proxy::purge( $actor );
		Proxy::get( $key_id );
		$this->assertSame( $before + 1, $this->requests, 'The entry is gone after the actor was retired.' );
	}

	/**
	 * A document whose origin the response does not name is returned but never cached.
	 *
	 * `Http::effective_url()` cannot always tell where a response came from, most often because
	 * another plugin answered `pre_http_request`. Caching a document under the id it claims
	 * would then rest on the requested URL alone, which a redirect may have left behind.
	 *
	 * @covers ::get
	 */
	public function test_a_document_of_unknown_origin_is_not_cached() {
		$id = 'https://example.com/notes/1';

		// A response without the `http_response` object the HTTP API normally carries.
		$this->responses[ $id ] = array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-type' => 'application/activity+json' ),
			'body'     => \wp_json_encode(
				array(
					'id'   => $id,
					'type' => 'Note',
				)
			),
		);

		$first  = Proxy::get( $id );
		$before = $this->requests;
		$second = Proxy::get( $id );

		$this->assertSame( $id, $first['id'] ?? null, 'The document is still returned.' );
		$this->assertSame( $first, $second );
		$this->assertSame( $before + 1, $this->requests, 'Nothing was cached, so it is fetched again.' );
	}

	/**
	 * The deprecated entry point goes through the proxy and its cache.
	 *
	 * @expectedDeprecated Activitypub\Http::get_remote_object
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
	 * @covers ::purge
	 */
	public function test_purge_keys_a_media_object_on_its_id() {
		$id                     = 'https://example.com/photos/1';
		$this->responses[ $id ] = array(
			'id'   => $id,
			'type' => 'Image',
			'url'  => 'https://cdn.example.com/1.jpg',
		);

		Proxy::get( $id );
		Proxy::purge( $this->responses[ $id ] );
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
		$this->responses[ $id ] = $this->served_from(
			$id,
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '<html></html>',
				'headers'  => array(),
			)
		);

		$this->assertWPError( Proxy::get( $id ) );

		$timeout = (int) \get_option( '_transient_timeout_activitypub_object_' . \hash( 'sha256', $id ) );
		$this->assertGreaterThan( \time() + 10 * MINUTE_IN_SECONDS, $timeout );
	}

	/**
	 * JSON scalars, lists, and empty documents are rejected and remembered as invalid JSON.
	 *
	 * @dataProvider invalid_json_document_provider
	 * @covers ::get
	 *
	 * @param string $body The JSON response body.
	 */
	public function test_get_rejects_non_object_json( $body ) {
		$id                     = 'https://example.com/notes/invalid';
		$this->responses[ $id ] = $this->served_from(
			$id,
			array(
				'response' => array( 'code' => 200 ),
				'body'     => $body,
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			)
		);

		$first = Proxy::get( $id );
		$this->assertWPError( $first );
		$this->assertSame( 'activitypub_invalid_json', $first->get_error_code() );
		$this->assertSame( 400, $first->get_error_data()['status'] );
		$this->assertEquals( $first, Proxy::get( $id ) );
		$this->assertSame( 1, $this->requests );
	}

	/**
	 * Non-object JSON and empty documents.
	 *
	 * @return array The response bodies.
	 */
	public function invalid_json_document_provider() {
		return array(
			'string'       => array( '"Person"' ),
			'integer'      => array( '1' ),
			'float'        => array( '1.5' ),
			'true'         => array( 'true' ),
			'false'        => array( 'false' ),
			'null'         => array( 'null' ),
			'empty array'  => array( '[]' ),
			'scalar list'  => array( '[1, 2]' ),
			'object list'  => array( '[{"id":"https://example.com/notes/invalid","type":"Note"}]' ),
			'empty object' => array( '{}' ),
		);
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
		$this->assertFalse( \get_option( '_transient_activitypub_object_' . \hash( 'sha256', $id ) ) );
		$this->assertFalse( \get_option( '_transient_timeout_activitypub_object_' . \hash( 'sha256', $id ) ) );
	}
}
