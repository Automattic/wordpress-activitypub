<?php
/**
 * Test file for the conversation Builder.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\Builder;

/**
 * Test class for Builder.
 *
 * @coversDefaultClass \Activitypub\Conversation\Builder
 */
class Test_Builder extends \WP_UnitTestCase {

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
		\remove_all_filters( 'activitypub_conversation_sources' );

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
	 * Register a single stub source that returns the given objects.
	 *
	 * @param array  $objects The objects the source reports.
	 * @param string $name    Optional. The name to register it under. Default 'stub'.
	 */
	protected function register_source( $objects, $name = 'stub' ) {
		\add_filter(
			'activitypub_conversation_sources',
			function ( $sources ) use ( $objects, $name ) {
				$sources[ $name ] = new Stub_Source( $objects );

				return $sources;
			}
		);
	}

	/**
	 * The object the walk started from is part of the conversation.
	 *
	 * @covers ::build
	 */
	public function test_includes_the_object_it_started_from() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
		);

		$this->register_source( array() );

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build();

		$this->assertCount( 1, $objects );
		$this->assertSame( 'https://remote.example/notes/1', $objects[0]['id'] );
	}

	/**
	 * An object reported by more than one source appears once.
	 *
	 * @covers ::build
	 */
	public function test_collects_each_object_once() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
		);

		$duplicate = array(
			'id'           => 'https://remote.example/notes/2',
			'attributedTo' => 'https://remote.example/users/alice',
		);

		$this->register_source( array( $duplicate ), 'one' );
		$this->register_source( array( $duplicate ), 'two' );

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build();

		$this->assertCount( 2, $objects, 'The duplicate is collapsed.' );
	}

	/**
	 * Objects come back oldest first.
	 *
	 * @covers ::build
	 */
	public function test_orders_by_publication_date() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
			'published'    => '2026-01-01T00:00:00Z',
		);

		$this->register_source(
			array(
				array(
					'id'           => 'https://remote.example/notes/3',
					'attributedTo' => 'https://remote.example/users/alice',
					'published'    => '2026-03-01T00:00:00Z',
				),
				array(
					'id'           => 'https://remote.example/notes/2',
					'attributedTo' => 'https://remote.example/users/alice',
					'published'    => '2026-02-01T00:00:00Z',
				),
			)
		);

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build();
		$ids     = \wp_list_pluck( $objects, 'id' );

		$this->assertSame(
			array(
				'https://remote.example/notes/1',
				'https://remote.example/notes/2',
				'https://remote.example/notes/3',
			),
			$ids
		);
	}

	/**
	 * A parent always precedes its reply, whatever the dates claim.
	 *
	 * This is the property that makes the output usable: `Interactions::add_comment()` resolves
	 * `inReplyTo` against what already exists, so a child arriving first cannot be filed.
	 *
	 * @covers ::build
	 */
	public function test_a_parent_always_precedes_its_reply() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
			'published'    => '2026-05-01T00:00:00Z',
		);

		// The reply claims to predate the object it replies to.
		$this->register_source(
			array(
				array(
					'id'           => 'https://remote.example/notes/2',
					'attributedTo' => 'https://remote.example/users/alice',
					'inReplyTo'    => 'https://remote.example/notes/1',
					'published'    => '2026-01-01T00:00:00Z',
				),
			)
		);

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build();
		$ids     = \wp_list_pluck( $objects, 'id' );

		$this->assertSame(
			array( 'https://remote.example/notes/1', 'https://remote.example/notes/2' ),
			$ids,
			'A reply must not be handed over before the object it replies to.'
		);
	}

	/**
	 * An object filed under an id its author's host does not own is dropped.
	 *
	 * FEP-11dd makes context membership the owner's claim, and warns it cannot be relied on. So a
	 * collection is a list of candidates: each object still has to satisfy the same host binding
	 * `Remote_Posts::add()` enforces, or a context owner could launder someone else's id.
	 *
	 * @covers ::build
	 */
	public function test_drops_an_object_whose_id_is_not_on_its_authors_host() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
		);

		$this->register_source(
			array(
				array(
					'id'           => 'https://victim.example/notes/9',
					'attributedTo' => 'https://remote.example/users/mallory',
				),
			)
		);

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build();
		$ids     = \wp_list_pluck( $objects, 'id' );

		$this->assertNotContains( 'https://victim.example/notes/9', $ids );
	}

	/**
	 * Only the named sources run.
	 *
	 * @covers ::build
	 */
	public function test_runs_only_the_named_sources() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
		);

		$this->register_source(
			array(
				array(
					'id'           => 'https://remote.example/notes/2',
					'attributedTo' => 'https://remote.example/users/alice',
				),
			),
			'wanted'
		);
		$this->register_source(
			array(
				array(
					'id'           => 'https://remote.example/notes/3',
					'attributedTo' => 'https://remote.example/users/alice',
				),
			),
			'unwanted'
		);

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build( array( 'wanted' ) );
		$ids     = \wp_list_pluck( $objects, 'id' );

		$this->assertContains( 'https://remote.example/notes/2', $ids );
		$this->assertNotContains( 'https://remote.example/notes/3', $ids );
	}

	/**
	 * No more objects are returned than the cap allows.
	 *
	 * @covers ::build
	 */
	public function test_stops_at_the_object_cap() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
		);

		$many = array();
		for ( $i = 2; $i < Builder::MAX_OBJECTS + 20; $i++ ) {
			$many[] = array(
				'id'           => "https://remote.example/notes/$i",
				'attributedTo' => 'https://remote.example/users/alice',
			);
		}

		$this->register_source( $many );

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build();

		$this->assertLessThanOrEqual( Builder::MAX_OBJECTS, \count( $objects ) );
	}

	/**
	 * An object that cannot be fetched yields an empty conversation.
	 *
	 * @covers ::build
	 */
	public function test_returns_nothing_when_the_starting_object_is_unreachable() {
		$this->assertSame( array(), ( new Builder( 'https://remote.example/missing' ) )->build() );
	}
}
