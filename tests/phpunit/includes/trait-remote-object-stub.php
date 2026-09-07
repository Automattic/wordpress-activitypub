<?php
/**
 * Remote object stub trait.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Serves remote ActivityPub documents from an in-memory table.
 *
 * `ActivityPub_TestCase_Cache_HTTP` answers the same filter, but from JSON files on disk keyed by
 * host and path, which cannot express "this URL returns that document" per test. Anything walking
 * a graph of remote documents needs the table, so it lives here rather than in each test class.
 */
trait Remote_Object_Stub {

	/**
	 * The documents the fixture server answers with, keyed by URL.
	 *
	 * @var array
	 */
	protected $documents = array();

	/**
	 * The URLs that were fetched, in order.
	 *
	 * @var array
	 */
	protected $requested = array();

	/**
	 * Serve the fixtures instead of the network.
	 */
	public function set_up() {
		parent::set_up();

		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'serve_fixture' ), 10, 2 );
	}

	/**
	 * Stop serving the fixtures.
	 */
	public function tear_down() {
		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'serve_fixture' ), 10 );

		parent::tear_down();
	}

	/**
	 * Answer a remote fetch from the fixture table.
	 *
	 * @param mixed $response      The pre-empted response.
	 * @param mixed $url_or_object The URL or object requested.
	 *
	 * @return array|null The fixture, or the untouched response when there is none.
	 */
	public function serve_fixture( $response, $url_or_object ) {
		if ( ! \is_string( $url_or_object ) ) {
			return $response;
		}

		$this->requested[] = $url_or_object;

		return $this->documents[ $url_or_object ] ?? $response;
	}
}
