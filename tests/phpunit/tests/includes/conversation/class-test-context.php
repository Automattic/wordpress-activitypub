<?php
/**
 * Test file for the Context conversation source.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\Context;

/**
 * Test class for Context.
 *
 * @coversDefaultClass \Activitypub\Conversation\Context
 */
class Test_Context extends \WP_UnitTestCase {

	/**
	 * Documents the fixture server answers with, keyed by URL.
	 *
	 * @var array
	 */
	protected $documents = array();

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

		return $this->documents[ $url_or_object ] ?? $response;
	}

	/**
	 * An object with no context is not something this source can do anything with.
	 *
	 * @covers ::supports
	 */
	public function test_does_not_support_an_object_without_a_context() {
		$source = new Context();

		$this->assertFalse( $source->supports( array( 'id' => 'https://remote.example/notes/1' ) ) );
	}

	/**
	 * An object carrying a context is.
	 *
	 * @covers ::supports
	 */
	public function test_supports_an_object_with_a_context() {
		$source = new Context();

		$this->assertTrue(
			$source->supports(
				array(
					'id'      => 'https://remote.example/notes/1',
					'context' => 'https://remote.example/context/1',
				)
			)
		);
	}

	/**
	 * A context that dereferences to a collection yields the objects in it.
	 *
	 * FEP-2931: when `context` dereferences to a Collection, that is the canonical context
	 * collection, and a consumer iterates its items for backfill.
	 *
	 * @covers ::parse
	 */
	public function test_reads_the_objects_of_a_context_collection() {
		$this->documents['https://remote.example/context/1'] = array(
			'id'           => 'https://remote.example/context/1',
			'type'         => 'OrderedCollection',
			'attributedTo' => 'https://remote.example/users/owner',
			'orderedItems' => array(
				array( 'id' => 'https://remote.example/notes/1' ),
				array( 'id' => 'https://remote.example/notes/2' ),
			),
		);

		$source = new Context();
		$items  = $source->parse(
			array(
				'id'      => 'https://remote.example/notes/1',
				'context' => 'https://remote.example/context/1',
			)
		);

		$this->assertCount( 2, $items );
		$this->assertSame( 'https://remote.example/notes/2', $items[1]['id'] );
	}

	/**
	 * A context that resolves to something other than a collection yields nothing.
	 *
	 * FEP-7888 allows a context to be any resolvable object. Only FEP-2931's collection shape
	 * tells us what belongs to the conversation, so anything else is not a source of objects.
	 *
	 * @covers ::parse
	 */
	public function test_ignores_a_context_that_is_not_a_collection() {
		$this->documents['https://remote.example/context/2'] = array(
			'id'           => 'https://remote.example/context/2',
			'type'         => 'Group',
			'attributedTo' => 'https://remote.example/users/owner',
		);

		$source = new Context();
		$items  = $source->parse(
			array(
				'id'      => 'https://remote.example/notes/1',
				'context' => 'https://remote.example/context/2',
			)
		);

		$this->assertSame( array(), $items );
	}
}
