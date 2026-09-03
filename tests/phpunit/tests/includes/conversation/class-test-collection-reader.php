<?php
/**
 * Test file for the conversation Collection_Reader.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\Collection_Reader;

/**
 * Test class for Collection_Reader.
 *
 * @coversDefaultClass \Activitypub\Conversation\Collection_Reader
 */
class Test_Collection_Reader extends \WP_UnitTestCase {

	/**
	 * Documents the fixture server answers with, keyed by URL.
	 *
	 * @var array
	 */
	protected $documents = array();

	/**
	 * URLs the reader asked for, in order.
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
	 * Stop serving fixtures.
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
	 * @return array|null The fixture, or null when there is none.
	 */
	public function serve_fixture( $response, $url_or_object ) {
		if ( ! \is_string( $url_or_object ) ) {
			return $response;
		}

		$this->requested[] = $url_or_object;

		return $this->documents[ $url_or_object ] ?? $response;
	}

	/**
	 * A single-page collection hands back its items.
	 *
	 * @covers ::read
	 */
	public function test_reads_a_single_page_collection() {
		$this->documents['https://remote.example/context/1'] = array(
			'id'           => 'https://remote.example/context/1',
			'type'         => 'OrderedCollection',
			'orderedItems' => array(
				array( 'id' => 'https://remote.example/notes/1' ),
				array( 'id' => 'https://remote.example/notes/2' ),
			),
		);

		$items = Collection_Reader::read( 'https://remote.example/context/1' );

		$this->assertCount( 2, $items );
		$this->assertSame( 'https://remote.example/notes/1', $items[0]['id'] );
		$this->assertSame( 'https://remote.example/notes/2', $items[1]['id'] );
	}

	/**
	 * A collection that defers its items to pages is followed through `first` and `next`.
	 *
	 * @covers ::read
	 */
	public function test_follows_first_and_next_through_the_pages() {
		$this->documents['https://remote.example/context/2']        = array(
			'id'    => 'https://remote.example/context/2',
			'type'  => 'OrderedCollection',
			'first' => 'https://remote.example/context/2?page=1',
		);
		$this->documents['https://remote.example/context/2?page=1'] = array(
			'id'           => 'https://remote.example/context/2?page=1',
			'type'         => 'OrderedCollectionPage',
			'orderedItems' => array( array( 'id' => 'https://remote.example/notes/1' ) ),
			'next'         => 'https://remote.example/context/2?page=2',
		);
		$this->documents['https://remote.example/context/2?page=2'] = array(
			'id'           => 'https://remote.example/context/2?page=2',
			'type'         => 'OrderedCollectionPage',
			'orderedItems' => array( array( 'id' => 'https://remote.example/notes/2' ) ),
		);

		$items = Collection_Reader::read( 'https://remote.example/context/2' );

		$this->assertCount( 2, $items, 'Items from every page have to be collected.' );
		$this->assertSame( 'https://remote.example/notes/1', $items[0]['id'] );
		$this->assertSame( 'https://remote.example/notes/2', $items[1]['id'] );
	}

	/**
	 * A collection whose `next` points back at itself terminates.
	 *
	 * Nothing obliges a remote server to end a collection, so the reader has to stop on its own
	 * rather than fetch until the request times out.
	 *
	 * @covers ::read
	 */
	public function test_stops_on_a_collection_that_never_ends() {
		$this->documents['https://remote.example/loop'] = array(
			'id'           => 'https://remote.example/loop',
			'type'         => 'OrderedCollection',
			'orderedItems' => array( array( 'id' => 'https://remote.example/notes/1' ) ),
			'next'         => 'https://remote.example/loop',
		);

		$items = Collection_Reader::read( 'https://remote.example/loop' );

		$this->assertLessThanOrEqual(
			Collection_Reader::MAX_REQUESTS,
			\count( $this->requested ),
			'The reader must not fetch more than its own request cap.'
		);
		$this->assertNotEmpty( $items, 'What was gathered before the cap still counts.' );
	}

	/**
	 * A collection carrying items of its own does not also page through them.
	 *
	 * `first` is how a collection defers its items to pages. When the document already listed
	 * them, following it as well collects the same objects twice.
	 *
	 * @covers ::read
	 */
	public function test_does_not_repeat_items_of_a_collection_that_also_has_pages() {
		$this->documents['https://remote.example/both']        = array(
			'id'           => 'https://remote.example/both',
			'type'         => 'OrderedCollection',
			'orderedItems' => array(
				array( 'id' => 'https://remote.example/notes/1' ),
				array( 'id' => 'https://remote.example/notes/2' ),
			),
			'first'        => 'https://remote.example/both?page=1',
		);
		$this->documents['https://remote.example/both?page=1'] = array(
			'id'           => 'https://remote.example/both?page=1',
			'type'         => 'OrderedCollectionPage',
			'orderedItems' => array(
				array( 'id' => 'https://remote.example/notes/1' ),
				array( 'id' => 'https://remote.example/notes/2' ),
			),
		);

		$items = Collection_Reader::read( 'https://remote.example/both' );

		$this->assertCount( 2, $items, 'The same objects must not be collected twice.' );
	}

	/**
	 * A cycle between two pages stops at the repeat rather than at the request cap.
	 *
	 * A counter alone cannot tell a cycle from a long collection, so a short loop would cost the
	 * full budget on every read.
	 *
	 * @covers ::read
	 */
	public function test_stops_as_soon_as_a_page_repeats() {
		$this->documents['https://remote.example/cycle/a'] = array(
			'id'           => 'https://remote.example/cycle/a',
			'type'         => 'OrderedCollection',
			'orderedItems' => array( array( 'id' => 'https://remote.example/notes/1' ) ),
			'next'         => 'https://remote.example/cycle/b',
		);
		$this->documents['https://remote.example/cycle/b'] = array(
			'id'           => 'https://remote.example/cycle/b',
			'type'         => 'OrderedCollectionPage',
			'orderedItems' => array( array( 'id' => 'https://remote.example/notes/2' ) ),
			'next'         => 'https://remote.example/cycle/a',
		);

		$items = Collection_Reader::read( 'https://remote.example/cycle/a' );

		$this->assertCount( 2, $items, 'Both pages are read once.' );
		$this->assertCount( 2, $this->requested, 'A page already fetched must not be fetched again.' );
	}

	/**
	 * A collection using `items` rather than `orderedItems` reads the same way.
	 *
	 * @covers ::read
	 */
	public function test_reads_an_unordered_collection() {
		$this->documents['https://remote.example/unordered'] = array(
			'id'    => 'https://remote.example/unordered',
			'type'  => 'Collection',
			'items' => array( array( 'id' => 'https://remote.example/notes/1' ) ),
		);

		$items = Collection_Reader::read( 'https://remote.example/unordered' );

		$this->assertCount( 1, $items );
	}

	/**
	 * A collection that cannot be fetched yields nothing rather than an error.
	 *
	 * @covers ::read
	 */
	public function test_returns_nothing_for_an_unreachable_collection() {
		$this->assertSame( array(), Collection_Reader::read( 'https://remote.example/missing' ) );
	}

	/**
	 * An empty collection yields nothing.
	 *
	 * @covers ::read
	 */
	public function test_returns_nothing_for_an_empty_collection() {
		$this->documents['https://remote.example/empty'] = array(
			'id'           => 'https://remote.example/empty',
			'type'         => 'OrderedCollection',
			'orderedItems' => array(),
		);

		$this->assertSame( array(), Collection_Reader::read( 'https://remote.example/empty' ) );
	}
}
