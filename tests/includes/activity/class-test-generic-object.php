<?php
/**
 * Test Generic Object.
 *
 * @package Activitypub
 */

namespace Activitypub\Activity;

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
}
