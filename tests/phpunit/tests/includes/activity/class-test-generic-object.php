<?php
/**
 * Test Generic Object.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Activity;

use Activitypub\Activity\Base_Object;
use Activitypub\Activity\Generic_Object;
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
}
