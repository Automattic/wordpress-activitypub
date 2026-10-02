<?php
/**
 * Test file for the conversation Builder.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Conversation;

use Activitypub\Conversation\Builder;
use Activitypub\Conversation\Source;
use Activitypub\Tests\Remote_Object_Stub;

/**
 * Test class for Builder.
 *
 * @coversDefaultClass \Activitypub\Conversation\Builder
 */
class Test_Builder extends \WP_UnitTestCase {

	use Remote_Object_Stub;

	/**
	 * Embedded traversal data never replaces the object fetched under its own ID.
	 *
	 * @dataProvider embedded_source_provider
	 * @covers ::build
	 *
	 * @param string $source The source to run.
	 */
	public function test_validates_objects_discovered_through_embedded_sources( $source ) {
		$seed                     = 'https://remote.example/notes/1';
		$id                       = 'https://victim.example/notes/2';
		$embedded                 = array(
			'id'           => $id,
			'type'         => 'Note',
			'attributedTo' => 'https://victim.example/users/bob',
			'content'      => 'Forged content.',
		);
		$this->documents[ $seed ] = array(
			'id'           => $seed,
			'attributedTo' => 'https://remote.example/users/alice',
		);
		if ( 'replies' === $source ) {
			$this->documents[ $seed ]['replies'] = array(
				'type'  => 'Collection',
				'items' => array( $embedded ),
			);
		} else {
			$this->documents[ $seed ]['inReplyTo'] = $embedded;
		}
		$this->documents[ $id ] = \array_merge( $embedded, array( 'content' => 'Canonical content.' ) );

		$objects = ( new Builder( $seed ) )->build( array( $source ) );
		$by_id   = \array_column( $objects, null, 'id' );
		$this->assertCount( 2, $objects );
		$this->assertSame( 'Canonical content.', $by_id[ $id ]['content'] );
		$this->assertSame( array( $seed, $id ), $this->requested );
	}

	/**
	 * Sources that can carry embedded objects.
	 *
	 * @return array Test cases.
	 */
	public function embedded_source_provider() {
		return array(
			'replies'     => array( 'replies' ),
			'in_reply_to' => array( 'in_reply_to' ),
		);
	}

	/**
	 * Malformed authors are rejected, and malformed parents do not break ordering.
	 *
	 * @covers ::build
	 */
	public function test_ignores_scalar_author_and_parent_references() {
		$id                     = 'https://remote.example/notes/1';
		$this->documents[ $id ] = array(
			'id'           => $id,
			'attributedTo' => true,
		);
		$this->assertSame( array(), ( new Builder( $id ) )->build( array() ) );

		$this->documents[ $id ]['attributedTo'] = 'https://remote.example/users/alice';
		$this->documents[ $id ]['inReplyTo']    = true;
		$this->assertCount( 1, ( new Builder( $id ) )->build( array() ) );
	}

	/**
	 * A malformed identifier must not become an array key.
	 *
	 * @covers ::build
	 */
	public function test_ignores_malformed_identifiers() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
		);
		$this->register_source(
			array(
				array(
					'id'           => array( 'https://remote.example/notes/2' ),
					'attributedTo' => 'https://remote.example/users/alice',
				),
			)
		);
		$this->assertCount( 1, ( new Builder( 'https://remote.example/notes/1' ) )->build() );
	}

	/**
	 * A malformed date is treated as missing instead of passed to strtotime().
	 *
	 * @covers ::build
	 */
	public function test_ignores_malformed_publication_dates() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
			'published'    => array( '2026-01-01T00:00:00Z' ),
		);
		$this->register_source(
			array(
				array(
					'id'           => 'https://remote.example/notes/2',
					'attributedTo' => 'https://remote.example/users/alice',
					'published'    => '2026-02-01T00:00:00Z',
				),
			)
		);
		$this->assertCount( 2, ( new Builder( 'https://remote.example/notes/1' ) )->build() );
	}

	/**
	 * Drop any source registered by a test before the next one runs.
	 */
	public function tear_down() {
		\remove_all_filters( 'activitypub_conversation_sources' );

		parent::tear_down();
	}


	/**
	 * Register a single stub source that returns the given objects.
	 *
	 * @param array  $objects The objects the source reports.
	 * @param string $name    Optional. The name to register it under. Default 'stub'.
	 */
	protected function register_source( $objects, $name = 'stub' ) {
		foreach ( $objects as $object ) {
			$id = \is_array( $object ) ? ( $object['id'] ?? null ) : null;
			if ( \is_string( $id ) ) {
				$this->documents[ $id ] = $object;
			}
		}

		\add_filter(
			'activitypub_conversation_sources',
			function ( $sources ) use ( $objects, $name ) {
				$source = $this->createMock( Source::class );
				$source->method( 'supports' )->willReturn( true );
				$source->method( 'parse' )->willReturn( $objects );

				$sources[ $name ] = $source;

				return $sources;
			}
		);
	}

	/**
	 * Collections can list object URIs rather than embedded objects.
	 *
	 * @covers ::build
	 */
	public function test_resolves_uri_items_in_a_context_collection() {
		$seed                      = 'https://remote.example/notes/1';
		$reply                     = 'https://other.example/notes/2';
		$this->documents[ $seed ]  = array(
			'id'           => $seed,
			'attributedTo' => 'https://remote.example/users/alice',
			'context'      => array(
				'type'  => 'Collection',
				'items' => array( $reply ),
			),
		);
		$this->documents[ $reply ] = array(
			'id'           => $reply,
			'attributedTo' => 'https://other.example/users/bob',
			'inReplyTo'    => $seed,
		);

		$objects = ( new Builder( $seed ) )->build( array( 'context' ) );

		$this->assertSame( array( $seed, $reply ), \wp_list_pluck( $objects, 'id' ) );
		$this->assertSame( array( $seed, $reply ), $this->requested );
	}

	/**
	 * A collection cannot substitute forged content for an object from another host.
	 *
	 * @covers ::build
	 */
	public function test_uses_the_canonical_object_instead_of_embedded_content() {
		$seed                     = 'https://remote.example/notes/1';
		$id                       = 'https://victim.example/notes/2';
		$this->documents[ $seed ] = array(
			'id'           => $seed,
			'attributedTo' => 'https://remote.example/users/alice',
		);
		$this->register_source(
			array(
				array(
					'id'           => $id,
					'attributedTo' => 'https://victim.example/users/bob',
					'content'      => 'Forged content.',
				),
			)
		);
		$this->documents[ $id ]['content'] = 'Canonical content.';

		$objects = ( new Builder( $seed ) )->build();

		$this->assertCount( 2, $objects );
		$this->assertSame( 'Canonical content.', $objects[1]['content'] );
		$this->assertSame( array( $seed, $id ), $this->requested );
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

	/**
	 * An object naming no author is dropped.
	 *
	 * The id binding is the only thing standing between us and a context owner listing whatever
	 * it likes. An object with no `attributedTo` cannot be bound at all, so keeping it would make
	 * omitting the property the way around the check.
	 *
	 * @covers ::build
	 */
	public function test_drops_an_object_with_no_author() {
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
		);

		$this->register_source( array( array( 'id' => 'https://victim.example/notes/9' ) ) );

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build();
		$ids     = \wp_list_pluck( $objects, 'id' );

		$this->assertNotContains( 'https://victim.example/notes/9', $ids );
	}

	/**
	 * Dates are compared as instants, not as strings.
	 *
	 * `published` is RFC3339, so the same instant has several spellings and an offset sorts
	 * lexically against a `Z` in whichever way the digits happen to fall.
	 *
	 * @covers ::build
	 */
	public function test_orders_by_instant_rather_than_by_spelling() {
		// 07:00 UTC, but sorts after the 08:00Z below as a string.
		$this->documents['https://remote.example/notes/1'] = array(
			'id'           => 'https://remote.example/notes/1',
			'attributedTo' => 'https://remote.example/users/alice',
			'published'    => '2026-01-01T09:00:00+02:00',
		);

		$this->register_source(
			array(
				array(
					'id'           => 'https://remote.example/notes/2',
					'attributedTo' => 'https://remote.example/users/alice',
					'published'    => '2026-01-01T08:00:00Z',
				),
			)
		);

		$objects = ( new Builder( 'https://remote.example/notes/1' ) )->build();
		$ids     = \wp_list_pluck( $objects, 'id' );

		$this->assertSame(
			array( 'https://remote.example/notes/1', 'https://remote.example/notes/2' ),
			$ids,
			'09:00+02:00 is 07:00 UTC, so it comes before 08:00Z.'
		);
	}
}
