<?php
/**
 * Test file for the conversation Collection_Reader.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\Collection_Reader;
use Activitypub\Tests\Remote_Object_Stub;

/**
 * Test class for Collection_Reader.
 *
 * @coversDefaultClass \Activitypub\Conversation\Collection_Reader
 */
class Test_Collection_Reader extends \WP_UnitTestCase {

	use Remote_Object_Stub;

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

	/**
	 * A page listing more items than the cap does not hand all of them back.
	 *
	 * Page size is the remote server's choice, so a single response can be arbitrarily large.
	 * Without a ceiling the whole list is materialised in memory before any caller sees it.
	 *
	 * @covers ::read
	 */
	public function test_stops_collecting_at_the_item_cap() {
		$items = array();
		for ( $i = 0; $i < Collection_Reader::MAX_ITEMS + 50; $i++ ) {
			$items[] = array( 'id' => "https://remote.example/notes/$i" );
		}

		$this->documents['https://remote.example/huge'] = array(
			'id'           => 'https://remote.example/huge',
			'type'         => 'OrderedCollection',
			'orderedItems' => $items,
		);

		$this->assertCount( Collection_Reader::MAX_ITEMS, Collection_Reader::read( 'https://remote.example/huge' ) );
	}
}
