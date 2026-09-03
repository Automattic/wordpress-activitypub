<?php
/**
 * Test file for the In_Reply_To conversation source.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\In_Reply_To;

/**
 * Test class for In_Reply_To.
 *
 * @coversDefaultClass \Activitypub\Conversation\In_Reply_To
 */
class Test_In_Reply_To extends \WP_UnitTestCase {

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
	 * A root object replies to nothing, so there is nothing above it.
	 *
	 * @covers ::supports
	 */
	public function test_does_not_support_a_root_object() {
		$source = new In_Reply_To();

		$this->assertFalse( $source->supports( array( 'id' => 'https://remote.example/notes/1' ) ) );
	}

	/**
	 * A reply does.
	 *
	 * @covers ::supports
	 */
	public function test_supports_a_reply() {
		$source = new In_Reply_To();

		$this->assertTrue(
			$source->supports(
				array(
					'id'        => 'https://remote.example/notes/2',
					'inReplyTo' => 'https://remote.example/notes/1',
				)
			)
		);
	}

	/**
	 * Every ancestor up to the root is collected.
	 *
	 * This is the half of a conversation that exists before we ever hear about it, which is what
	 * "comments made before the post was indexed" means in the issue.
	 *
	 * @covers ::parse
	 */
	public function test_climbs_to_the_root() {
		$this->documents['https://remote.example/notes/2'] = array(
			'id'        => 'https://remote.example/notes/2',
			'inReplyTo' => 'https://remote.example/notes/1',
		);
		$this->documents['https://remote.example/notes/1'] = array(
			'id' => 'https://remote.example/notes/1',
		);

		$source = new In_Reply_To();
		$items  = $source->parse(
			array(
				'id'        => 'https://remote.example/notes/3',
				'inReplyTo' => 'https://remote.example/notes/2',
			)
		);

		$ids = \wp_list_pluck( $items, 'id' );

		$this->assertSame(
			array( 'https://remote.example/notes/2', 'https://remote.example/notes/1' ),
			$ids,
			'Both ancestors are collected, nearest first.'
		);
	}

	/**
	 * An ancestor that cannot be fetched ends the climb rather than failing it.
	 *
	 * @covers ::parse
	 */
	public function test_stops_at_an_unreachable_ancestor() {
		$source = new In_Reply_To();
		$items  = $source->parse(
			array(
				'id'        => 'https://remote.example/notes/2',
				'inReplyTo' => 'https://remote.example/missing',
			)
		);

		$this->assertSame( array(), $items );
	}

	/**
	 * Two objects claiming to reply to each other do not climb forever.
	 *
	 * @covers ::parse
	 */
	public function test_stops_on_a_cycle() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'        => 'https://remote.example/notes/1',
			'inReplyTo' => 'https://remote.example/notes/2',
		);
		$this->documents['https://remote.example/notes/2'] = array(
			'id'        => 'https://remote.example/notes/2',
			'inReplyTo' => 'https://remote.example/notes/1',
		);

		$source = new In_Reply_To();
		$items  = $source->parse(
			array(
				'id'        => 'https://remote.example/notes/3',
				'inReplyTo' => 'https://remote.example/notes/1',
			)
		);

		$this->assertCount( 2, $items, 'Each object in the cycle is collected once.' );
		$this->assertCount( 2, $this->requested, 'An object already fetched must not be fetched again.' );
	}

	/**
	 * The climb does not go past its depth limit.
	 *
	 * @covers ::parse
	 */
	public function test_does_not_climb_past_the_depth_limit() {
		$depth = In_Reply_To::MAX_DEPTH + 3;
		for ( $i = 1; $i <= $depth; $i++ ) {
			$this->documents[ "https://remote.example/notes/$i" ] = array(
				'id'        => "https://remote.example/notes/$i",
				'inReplyTo' => 'https://remote.example/notes/' . ( $i + 1 ),
			);
		}

		$source = new In_Reply_To();
		$items  = $source->parse(
			array(
				'id'        => 'https://remote.example/notes/0',
				'inReplyTo' => 'https://remote.example/notes/1',
			)
		);

		$this->assertCount( In_Reply_To::MAX_DEPTH, $items );
	}
}
