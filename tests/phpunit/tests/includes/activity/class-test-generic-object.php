<?php
/**
 * Test Generic Object.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Activity;

use Activitypub\Activity\Base_Object;
use Activitypub\Activity\Generic_Object;
use Activitypub\Collection\Actors;
use Activitypub\Model\Blog;
use WP_UnitTestCase;

/**
 * Test cases for the Generic_Object class.
 */
class Test_Generic_Object extends WP_UnitTestCase {
	/**
	 * Test if init_from_array correctly sets all attributes.
	 */
	public function test_init_from_array() {
		$test_data = array(
			'id'           => 'https://example.com/test',
			'type'         => 'Test',
			'name'         => 'Test Name',
			'summary'      => 'Test Summary',
			'content'      => 'Test Content',
			'published'    => '2024-03-20T12:00:00Z',
			'to'           => array( 'https://example.com/user1' ),
			'cc'           => array( 'https://example.com/user2' ),
			'attachment'   => array(
				array(
					'type' => 'Image',
					'url'  => 'https://example.com/image.jpg',
				),
			),
			'attributedTo' => 'https://example.com/author',
			'unsupported'  => 'unsupported',
		);

		$object = Generic_Object::init_from_array( $test_data );

		// Test if all attributes are set correctly.
		$this->assertEquals( $test_data['id'], $object->get_id() );
		$this->assertEquals( $test_data['type'], $object->get_type() );
		$this->assertEquals( $test_data['name'], $object->get_name() );
		$this->assertEquals( $test_data['summary'], $object->get_summary() );
		$this->assertEquals( $test_data['content'], $object->get_content() );
		$this->assertEquals( $test_data['published'], $object->get_published() );
		$this->assertEquals( $test_data['to'], $object->get_to() );
		$this->assertEquals( $test_data['cc'], $object->get_cc() );
		$this->assertEquals( $test_data['attachment'], $object->get_attachment() );
		$this->assertEquals( $test_data['attributedTo'], $object->get_attributed_to() );
		$this->assertEquals( $test_data['unsupported'], $object->get_unsupported() );
	}

	/**
	 * Test if init_from_array handles invalid input correctly.
	 */
	public function test_init_from_array_invalid_input() {
		$result = Generic_Object::init_from_array( 'not an array' );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_array', $result->get_error_code() );
	}

	/**
	 * Test if init_from_array handles empty values correctly.
	 */
	public function test_init_from_array_empty_values() {
		$test_data = array(
			'id'      => 'https://example.com/test',
			'type'    => 'Test',
			'name'    => '',
			'summary' => null,
			'content' => false,
		);

		$object = Generic_Object::init_from_array( $test_data );

		$this->assertEquals( $test_data['id'], $object->get_id() );
		$this->assertEquals( $test_data['type'], $object->get_type() );
		$this->assertEmpty( $object->get_name() );
		$this->assertNull( $object->get_summary() );
		$this->assertFalse( $object->get_content() );
	}

	/**
	 * Test if init_from_array correctly handles camelCase to snake_case conversion.
	 */
	public function test_init_from_array_case_conversion() {
		$test_data = array(
			'attributedTo' => 'https://example.com/author',
			'inReplyTo'    => 'https://example.com/post/1',
			'mediaType'    => 'text/html',
		);

		$object = Generic_Object::init_from_array( $test_data );

		$this->assertEquals( $test_data['attributedTo'], $object->get_attributed_to() );
		$this->assertEquals( $test_data['inReplyTo'], $object->get_in_reply_to() );
		$this->assertEquals( $test_data['mediaType'], $object->get_media_type() );
	}

	/**
	 * Test if init_from_array correctly handles camelCase to snake_case conversion.
	 */
	public function test_to_array() {
		$test_data = array(
			'attributedTo' => 'https://example.com/author',
			'inReplyTo'    => 'https://example.com/post/1',
			'mediaType'    => 'text/html',
		);

		$object = Generic_Object::init_from_array( $test_data );

		$array = $object->to_array();

		$this->assertEquals( $test_data['attributedTo'], $array['attributedTo'] );
		$this->assertEquals( $test_data['inReplyTo'], $array['inReplyTo'] );
		$this->assertEquals( $test_data['mediaType'], $array['mediaType'] );
	}

	/**
	 * Test that to_array strips `bto` and `bcc` per ActivityPub spec Section 6.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_to_array_strips_private_addressing() {
		$test_data = array(
			'id'     => 'https://example.com/note/123',
			'type'   => 'Note',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'     => array( 'https://example.com/users/test/followers' ),
			'bto'    => array( 'https://example.com/users/secret' ),
			'bcc'    => array( 'https://example.com/users/hidden' ),
			'object' => array(
				'type'    => 'Note',
				'content' => 'Hello',
				'bto'     => array( 'https://example.com/users/secret' ),
				'bcc'     => array( 'https://example.com/users/hidden' ),
			),
		);

		$object = Generic_Object::init_from_array( $test_data );

		$array = $object->to_array( false );

		$this->assertArrayNotHasKey( 'bto', $array, 'bto should be stripped from activity' );
		$this->assertArrayNotHasKey( 'bcc', $array, 'bcc should be stripped from activity' );
		$this->assertArrayNotHasKey( 'bto', $array['object'], 'bto should be stripped from embedded object' );
		$this->assertArrayNotHasKey( 'bcc', $array['object'], 'bcc should be stripped from embedded object' );

		/* Other fields should be preserved. */
		$this->assertArrayHasKey( 'to', $array );
		$this->assertArrayHasKey( 'cc', $array );
		$this->assertSame( 'Hello', $array['object']['content'] );
	}

	/**
	 * Test that to_array does not error when no `bto`/`bcc` are present.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_to_array_without_private_addressing() {
		$test_data = array(
			'id'   => 'https://example.com/note/123',
			'type' => 'Note',
			'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		$object = Generic_Object::init_from_array( $test_data );

		$array = $object->to_array( false );

		$this->assertSame( 'Note', $array['type'] );
		$this->assertArrayHasKey( 'to', $array );
	}

	/**
	 * Test that `$include_blind_audience = true` preserves `bto` and `bcc` at both levels.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_to_array_preserves_blind_audience_when_opted_in() {
		$test_data = array(
			'id'     => 'https://example.com/note/123',
			'type'   => 'Note',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'bto'    => array( 'https://example.com/users/secret' ),
			'bcc'    => array( 'https://example.com/users/hidden' ),
			'object' => array(
				'type' => 'Note',
				'bto'  => array( 'https://example.com/users/secret-object' ),
				'bcc'  => array( 'https://example.com/users/hidden-object' ),
			),
		);

		$object = Generic_Object::init_from_array( $test_data );

		$array = $object->to_array( false, true );

		$this->assertSame( $test_data['bto'], $array['bto'], 'bto preserved at activity level' );
		$this->assertSame( $test_data['bcc'], $array['bcc'], 'bcc preserved at activity level' );
		$this->assertSame( $test_data['object']['bto'], $array['object']['bto'], 'bto preserved in embedded object' );
		$this->assertSame( $test_data['object']['bcc'], $array['object']['bcc'], 'bcc preserved in embedded object' );
	}

	/**
	 * Test that `bto`/`bcc` survive a JSON storage roundtrip when opted in.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_json
	 * @covers Activitypub\Activity\Generic_Object::init_from_json
	 */
	public function test_to_json_roundtrip_preserves_blind_audience_when_opted_in() {
		$test_data = array(
			'id'   => 'https://example.com/note/123',
			'type' => 'Note',
			'to'   => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'bto'  => array( 'https://example.com/users/secret' ),
			'bcc'  => array( 'https://example.com/users/hidden' ),
		);

		$json     = Generic_Object::init_from_array( $test_data )->to_json( true, true );
		$reloaded = Generic_Object::init_from_json( $json );

		$this->assertSame( $test_data['bto'], $reloaded->get_bto(), 'bto survives JSON roundtrip' );
		$this->assertSame( $test_data['bcc'], $reloaded->get_bcc(), 'bcc survives JSON roundtrip' );
	}

	/**
	 * Test if init_from_array correctly handles quote property.
	 *
	 * Tests that the quote property can be set from array.
	 * Uses Base_Object which has the quote property defined.
	 *
	 * @covers Activitypub\Activity\Generic_Object::init_from_array
	 */
	public function test_init_from_array_quote_property() {
		$test_data = array(
			'id'    => 'https://example.com/note/123',
			'type'  => 'Note',
			'quote' => 'https://example.com/post/456',
		);

		$object = Base_Object::init_from_array( $test_data );

		// Verify quote property is accessible.
		$this->assertEquals( $test_data['quote'], $object->get_quote() );
	}

	/**
	 * Test if init_from_array correctly handles underscore-prefixed properties.
	 *
	 * Uses Base_Object which has the _misskey_quote property defined.
	 *
	 * @covers Activitypub\Activity\Generic_Object::init_from_array
	 */
	public function test_init_from_array_underscore_properties() {
		$test_data = array(
			'id'             => 'https://example.com/note/123',
			'type'           => 'Note',
			'_misskey_quote' => 'https://example.com/post/789',
		);

		$object = Base_Object::init_from_array( $test_data );

		// Test that underscore property is accessible.
		$this->assertEquals( $test_data['_misskey_quote'], $object->get__misskey_quote() );
	}

	/**
	 * Test quote properties round-trip through set/get.
	 *
	 * Uses Base_Object to verify quote properties can be set and retrieved.
	 *
	 * @covers Activitypub\Activity\Generic_Object::__call
	 */
	public function test_quote_properties_set_and_get() {
		$object = new Base_Object();

		$object->set_quote( 'https://example.com/post/456' );
		$object->set_quote_url( 'https://example.com/post/789' );
		$object->set_quote_uri( 'https://example.com/post/101' );

		$this->assertEquals( 'https://example.com/post/456', $object->get_quote() );
		$this->assertEquals( 'https://example.com/post/789', $object->get_quote_url() );
		$this->assertEquals( 'https://example.com/post/101', $object->get_quote_uri() );
	}

	/**
	 * Test underscore-prefixed properties round-trip through set/get.
	 *
	 * Uses Base_Object to verify _misskey_quote property can be set and retrieved.
	 *
	 * @covers Activitypub\Activity\Generic_Object::__call
	 */
	public function test_underscore_properties_set_and_get() {
		$object = new Base_Object();

		$object->set__misskey_quote( 'https://example.com/post/789' );

		$this->assertEquals( 'https://example.com/post/789', $object->get__misskey_quote() );
	}

	/**
	 * Underscore properties are emitted only when the context declares them.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 * @covers Activitypub\Activity\Generic_Object::is_context_term
	 */
	public function test_underscore_properties_follow_context() {
		$object = new class() extends Generic_Object {
			const JSON_LD_CONTEXT = array(
				'https://www.w3.org/ns/activitystreams',
				array(
					'_misskey_quote' => 'https://misskey-hub.net/ns#_misskey_quote',
				),
			);

			/**
			 * A wire term declared in the context above.
			 *
			 * @var string
			 */
			protected $_misskey_quote = 'https://remote.example/notes/1'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

			/**
			 * An internal property not declared in the context.
			 *
			 * @var string
			 */
			protected $_internal = 'hidden'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
		};

		$array = $object->to_array();

		$this->assertSame( 'https://remote.example/notes/1', $array['_misskey_quote'] );
		$this->assertArrayNotHasKey( '_internal', $array );
	}

	/**
	 * Without a context declaration every underscore property stays internal.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_underscore_properties_hidden_without_context() {
		$object = new class() extends Generic_Object {
			/**
			 * Internal id, not declared in the (default) context.
			 *
			 * @var int
			 */
			protected $_id = 5; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
		};

		$this->assertArrayNotHasKey( '_id', $object->to_array() );
	}

	/**
	 * The actor models' internal `_id` never reaches the wire.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_actor_internal_id_stays_hidden() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', $user_id )->add_cap( 'activitypub' );

		$user = Actors::get_by_id( $user_id );
		$this->assertNotWPError( $user );

		$array = $user->to_array();
		$this->assertArrayNotHasKey( '_id', $array );
		$this->assertArrayNotHasKey( 'id_', $array );
		$this->assertSame( $user->get_id(), $array['id'] );

		// Instantiate directly: the blog actor may be disabled by site config, which is unrelated to this check.
		$blog = new Blog();
		$this->assertArrayNotHasKey( '_id', $blog->to_array() );
	}

	/**
	 * Regular snake_case properties are still camelCased on the wire.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_regular_properties_still_camel_cased() {
		$object = new Base_Object();
		$object->set_type( 'Note' );
		$object->set_attributed_to( 'https://example.com/users/alice' );
		$object->set_in_reply_to( 'https://example.com/notes/1' );
		$object->set_interaction_policy( array( 'canQuote' => array( 'automaticApproval' => 'https://www.w3.org/ns/activitystreams#Public' ) ) );
		$object->set_quote_uri( 'https://example.com/notes/2' );

		$array = $object->to_array();

		$this->assertSame( 'https://example.com/users/alice', $array['attributedTo'] );
		$this->assertSame( 'https://example.com/notes/1', $array['inReplyTo'] );
		$this->assertArrayHasKey( 'interactionPolicy', $array );
		$this->assertSame( 'https://example.com/notes/2', $array['quoteUri'] );
		foreach ( array( 'attributed_to', 'in_reply_to', 'interaction_policy', 'quote_uri' ) as $snake ) {
			$this->assertArrayNotHasKey( $snake, $array );
		}
	}

	/**
	 * Quote terms survive a from_array to to_array round trip with their wire names.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_quote_terms_round_trip() {
		$input = array(
			'type'           => 'Note',
			'id'             => 'https://example.com/notes/3',
			'quote'          => 'https://remote.example/notes/1',
			'quoteUri'       => 'https://remote.example/notes/1',
			'_misskey_quote' => 'https://remote.example/notes/1',
		);

		$array = Base_Object::init_from_array( $input )->to_array();

		$this->assertSame( 'https://remote.example/notes/1', $array['quote'] );
		$this->assertSame( 'https://remote.example/notes/1', $array['quoteUri'] );
		$this->assertSame( 'https://remote.example/notes/1', $array['_misskey_quote'] );
		$this->assertArrayNotHasKey( 'misskeyQuote', $array );
	}

	/**
	 * Namespaced array properties are emitted as prefix:term keys (FEP-b2b8 dcterms:subject).
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_namespaced_array_property_expands_to_prefixed_keys() {
		$object = new Base_Object();
		$object->set_type( 'Note' );
		$object->set_dcterms( array( 'subject' => 'Content warning' ) );

		$array = $object->to_array();

		$this->assertSame( 'Content warning', $array['dcterms:subject'] );
		$this->assertArrayNotHasKey( 'dcterms', $array );
	}

	/**
	 * Every sub-key of a namespaced array property gets its own prefixed key.
	 *
	 * @covers Activitypub\Activity\Generic_Object::to_array
	 */
	public function test_namespaced_array_property_expands_all_sub_keys() {
		$object = new Base_Object();
		$object->set_type( 'Note' );
		$object->set_dcterms(
			array(
				'subject' => 'A',
				'rights'  => 'B',
			)
		);

		$array = $object->to_array();

		$this->assertSame( 'A', $array['dcterms:subject'] );
		$this->assertSame( 'B', $array['dcterms:rights'] );
		$this->assertArrayNotHasKey( 'dcterms', $array );
	}
}
