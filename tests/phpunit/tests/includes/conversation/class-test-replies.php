<?php
/**
 * Test file for the Replies conversation source.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\Replies;

/**
 * Test class for Replies.
 *
 * @coversDefaultClass \Activitypub\Conversation\Replies
 */
class Test_Replies extends \WP_UnitTestCase {

	/**
	 * Documents the fixture server answers with, keyed by URL.
	 *
	 * @var array
	 */
	protected $documents = array();

	/**
	 * URLs that were fetched, in order.
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
}
