<?php
/**
 * Test file for the Replies conversation source.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\Replies;
use Activitypub\Tests\Remote_Object_Stub;

/**
 * Test class for Replies.
 *
 * @coversDefaultClass \Activitypub\Conversation\Replies
 */
class Test_Replies extends \WP_UnitTestCase {

	use Remote_Object_Stub;

	/**
	 * Embedded replies are read directly, including anonymous nested collections.
	 *
	 * @dataProvider embedded_collection_id_provider
	 * @covers ::parse
	 *
	 * @param string|null $id Optional collection ID.
	 */
	public function test_reads_embedded_replies_without_refetching( $id ) {
		$grandchild = array( 'id' => 'https://remote.example/notes/3' );
		$reply      = array(
			'id'      => 'https://remote.example/notes/2',
			'replies' => array(
				'type'  => 'Collection',
				'items' => array( $grandchild ),
			),
		);
		$collection = array(
			'type'  => 'Collection',
			'first' => array(
				'type'  => 'CollectionPage',
				'items' => array( $reply ),
			),
		);
		if ( $id ) {
			$collection['id'] = $id;
		}

		$this->assertSame( array( $reply, $grandchild ), ( new Replies() )->parse( array( 'replies' => $collection ) ) );
		$this->assertSame( array(), $this->requested );
	}

	/**
	 * Embedded collections with and without an identifier.
	 *
	 * @return array Test cases.
	 */
	public function embedded_collection_id_provider() {
		return array(
			'with id'    => array( 'https://remote.example/notes/1/replies' ),
			'without id' => array( null ),
		);
	}

	/**
	 * Anonymous collections still count toward the collection budget.
	 *
	 * @covers ::parse
	 */
	public function test_bounds_anonymous_replies_collections() {
		$children = array();
		for ( $i = 0; $i < Replies::MAX_COLLECTIONS + 5; ++$i ) {
			$children[] = array(
				'id'      => "https://remote.example/notes/$i",
				'replies' => array(
					'type'  => 'Collection',
					'items' => array( array( 'id' => "https://remote.example/notes/$i/child" ) ),
				),
			);
		}
		$items = ( new Replies() )->parse(
			array(
				'replies' => array(
					'type'  => 'Collection',
					'items' => $children,
				),
			)
		);

		$this->assertCount( \count( $children ) + Replies::MAX_COLLECTIONS - 1, $items );
		$this->assertSame( array(), $this->requested );
	}

	/**
	 * Embedded collection IDs use the same cycle guard as URI references.
	 *
	 * @covers ::parse
	 */
	public function test_stops_on_an_embedded_collection_cycle() {
		$id    = 'https://remote.example/notes/1/replies';
		$reply = array(
			'id'      => 'https://remote.example/notes/2',
			'replies' => $id,
		);
		$items = ( new Replies() )->parse(
			array(
				'replies' => array(
					'id'    => $id,
					'type'  => 'Collection',
					'items' => array( $reply ),
				),
			)
		);

		$this->assertSame( array( $reply ), $items );
		$this->assertSame( array(), $this->requested );
	}

	/**
	 * Malformed scalar references are unsupported and never fetched.
	 *
	 * @covers ::supports
	 * @covers ::parse
	 */
	public function test_rejects_scalar_references() {
		$source = new Replies();
		foreach ( array( true, 1, 1.5, false, 0, null, '', array() ) as $value ) {
			$object = array( 'replies' => $value );
			$this->assertFalse( $source->supports( $object ) );
			$this->assertSame( array(), $source->parse( $object ) );
		}
		$this->assertSame( array(), $this->requested );
	}

	/**
	 * URI items must be resolved to discover their own replies collections.
	 *
	 * @covers ::parse
	 */
	public function test_descends_into_uri_replies() {
		$reply      = 'https://remote.example/notes/2';
		$grandchild = 'https://remote.example/notes/3';
		$this->documents['https://remote.example/notes/1/replies'] = array(
			'type'  => 'Collection',
			'first' => array(
				'type'  => 'CollectionPage',
				'items' => array( $reply ),
			),
		);
		$this->documents[ $reply ]                                 = array(
			'id'      => $reply,
			'replies' => 'https://remote.example/notes/2/replies',
		);
		$this->documents['https://remote.example/notes/2/replies'] = array(
			'type'  => 'Collection',
			'items' => array( $grandchild ),
		);
		$this->documents[ $grandchild ]                            = array( 'id' => $grandchild );

		$objects = ( new Replies() )->parse( array( 'replies' => 'https://remote.example/notes/1/replies' ) );

		$this->assertSame( array( $reply, $grandchild ), \wp_list_pluck( $objects, 'id' ) );
		$this->assertSame(
			array( 'https://remote.example/notes/1/replies', $reply, 'https://remote.example/notes/2/replies', $grandchild ),
			$this->requested
		);
	}

	/**
	 * An object with no replies collection is not something this source can use.
	 *
	 * @covers ::supports
	 */
	public function test_does_not_support_an_object_without_replies() {
		$source = new Replies();

		$this->assertFalse( $source->supports( array( 'id' => 'https://remote.example/notes/1' ) ) );
	}

	/**
	 * An object carrying one is.
	 *
	 * @covers ::supports
	 */
	public function test_supports_an_object_with_replies() {
		$source = new Replies();

		$this->assertTrue(
			$source->supports(
				array(
					'id'      => 'https://remote.example/notes/1',
					'replies' => 'https://remote.example/notes/1/replies',
				)
			)
		);
	}

	/**
	 * The direct replies of an object are collected.
	 *
	 * @covers ::parse
	 */
	public function test_collects_direct_replies() {
		$this->documents['https://remote.example/notes/1/replies'] = array(
			'id'           => 'https://remote.example/notes/1/replies',
			'type'         => 'OrderedCollection',
			'orderedItems' => array(
				array( 'id' => 'https://remote.example/notes/2' ),
				array( 'id' => 'https://remote.example/notes/3' ),
			),
		);

		$source = new Replies();
		$items  = $source->parse(
			array(
				'id'      => 'https://remote.example/notes/1',
				'replies' => 'https://remote.example/notes/1/replies',
			)
		);

		$this->assertCount( 2, $items );
	}

	/**
	 * Replies of replies are collected too.
	 *
	 * A thread is a tree, and only the root's collection is reachable from the starting object,
	 * so the walk has to descend to see anything past the first level.
	 *
	 * @covers ::parse
	 */
	public function test_descends_into_replies_of_replies() {
		$this->documents['https://remote.example/notes/1/replies'] = array(
			'id'           => 'https://remote.example/notes/1/replies',
			'type'         => 'OrderedCollection',
			'orderedItems' => array(
				array(
					'id'      => 'https://remote.example/notes/2',
					'replies' => 'https://remote.example/notes/2/replies',
				),
			),
		);
		$this->documents['https://remote.example/notes/2/replies'] = array(
			'id'           => 'https://remote.example/notes/2/replies',
			'type'         => 'OrderedCollection',
			'orderedItems' => array( array( 'id' => 'https://remote.example/notes/3' ) ),
		);

		$source = new Replies();
		$items  = $source->parse(
			array(
				'id'      => 'https://remote.example/notes/1',
				'replies' => 'https://remote.example/notes/1/replies',
			)
		);

		$ids = \wp_list_pluck( $items, 'id' );

		$this->assertContains( 'https://remote.example/notes/2', $ids );
		$this->assertContains( 'https://remote.example/notes/3', $ids, 'The grandchild has to be reached.' );
	}

	/**
	 * A reply pointing back at an ancestor does not send the walk round forever.
	 *
	 * @covers ::parse
	 */
	public function test_stops_when_a_reply_points_back_at_an_ancestor() {
		$this->documents['https://remote.example/notes/1/replies'] = array(
			'id'           => 'https://remote.example/notes/1/replies',
			'type'         => 'OrderedCollection',
			'orderedItems' => array(
				array(
					'id'      => 'https://remote.example/notes/2',
					'replies' => 'https://remote.example/notes/2/replies',
				),
			),
		);
		$this->documents['https://remote.example/notes/2/replies'] = array(
			'id'           => 'https://remote.example/notes/2/replies',
			'type'         => 'OrderedCollection',
			'orderedItems' => array(
				array(
					'id'      => 'https://remote.example/notes/1',
					'replies' => 'https://remote.example/notes/1/replies',
				),
			),
		);

		$source = new Replies();
		$items  = $source->parse(
			array(
				'id'      => 'https://remote.example/notes/1',
				'replies' => 'https://remote.example/notes/1/replies',
			)
		);

		$this->assertLessThanOrEqual( 4, \count( $this->requested ), 'A collection already read must not be read again.' );
		$this->assertNotEmpty( $items );
	}

	/**
	 * The walk does not descend past its depth limit.
	 *
	 * @covers ::parse
	 */
	public function test_does_not_descend_past_the_depth_limit() {
		// A chain longer than the limit, each object replying to the one before it.
		$depth = Replies::MAX_DEPTH + 3;
		for ( $i = 1; $i <= $depth; $i++ ) {
			$this->documents[ "https://remote.example/notes/$i/replies" ] = array(
				'id'           => "https://remote.example/notes/$i/replies",
				'type'         => 'OrderedCollection',
				'orderedItems' => array(
					array(
						'id'      => 'https://remote.example/notes/' . ( $i + 1 ),
						'replies' => 'https://remote.example/notes/' . ( $i + 1 ) . '/replies',
					),
				),
			);
		}

		$source = new Replies();
		$items  = $source->parse(
			array(
				'id'      => 'https://remote.example/notes/1',
				'replies' => 'https://remote.example/notes/1/replies',
			)
		);

		$this->assertLessThanOrEqual( Replies::MAX_DEPTH, \count( $items ), 'One object per level, so the count is the depth reached.' );
	}

	/**
	 * A wide thread does not cost an unbounded number of requests.
	 *
	 * Depth alone does not bound the walk: one level of a thread can name any number of replies,
	 * each with a collection of its own. Without a budget across the whole walk a remote server
	 * chooses how many requests we make.
	 *
	 * @covers ::parse
	 */
	public function test_does_not_fetch_more_collections_than_its_budget() {
		$children = array();
		for ( $i = 2; $i < 60; $i++ ) {
			$children[] = array(
				'id'      => "https://remote.example/notes/$i",
				'replies' => "https://remote.example/notes/$i/replies",
			);
			$this->documents[ "https://remote.example/notes/$i/replies" ] = array(
				'id'           => "https://remote.example/notes/$i/replies",
				'type'         => 'OrderedCollection',
				'orderedItems' => array( array( 'id' => "https://remote.example/notes/$i/child" ) ),
			);
		}

		$this->documents['https://remote.example/notes/1/replies'] = array(
			'id'           => 'https://remote.example/notes/1/replies',
			'type'         => 'OrderedCollection',
			'orderedItems' => $children,
		);

		$source = new Replies();
		$source->parse(
			array(
				'id'      => 'https://remote.example/notes/1',
				'replies' => 'https://remote.example/notes/1/replies',
			)
		);

		$this->assertLessThanOrEqual(
			Replies::MAX_COLLECTIONS,
			\count( $this->requested ),
			'The number of requests must be ours to decide, not the remote server\'s.'
		);
	}
}
