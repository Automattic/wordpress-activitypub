<?php
/**
 * Test file for JSON transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Activity\Base_Object;
use Activitypub\Transformer\Json;
use WP_UnitTestCase;

/**
 * Test class for JSON Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Json
 */
class Test_Json extends WP_UnitTestCase {
	/**
	 * Test constructor with JSON string.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_with_json_string() {
		$json_string = wp_json_encode(
			array(
				'type'    => 'Note',
				'content' => 'Test Content',
				'id'      => 'https://example.com/test',
			)
		);

		$transformer = new Json( $json_string );
		$object      = $transformer->to_object();

		$this->assertInstanceOf( Base_Object::class, $object );
		$this->assertEquals( 'Note', $object->get_type() );
		$this->assertEquals( 'Test Content', $object->get_content() );
		$this->assertEquals( 'https://example.com/test', $object->get_id() );
	}

	/**
	 * Test constructor with array.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_with_array() {
		$array = array(
			'type'    => 'Article',
			'name'    => 'Test Title',
			'content' => 'Test Content',
			'url'     => 'https://example.com/article',
		);

		$transformer = new Json( $array );
		$object      = $transformer->to_object();

		$this->assertInstanceOf( Base_Object::class, $object );
		$this->assertEquals( 'Article', $object->get_type() );
		$this->assertEquals( 'Test Title', $object->get_name() );
		$this->assertEquals( 'Test Content', $object->get_content() );
		$this->assertEquals( 'https://example.com/article', $object->get_url() );
	}

	/**
	 * Test constructor with invalid JSON string.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_with_invalid_json() {
		$invalid_json = '{invalid json string}';

		$transformer = new Json( $invalid_json );
		$object      = $transformer->to_object();

		$this->assertInstanceOf( 'WP_Error', $object );
	}

	/**
	 * Test constructor with empty input.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_with_empty_input() {
		$transformer = new Json( '' );
		$object      = $transformer->to_object();

		$this->assertInstanceOf( 'WP_Error', $object );
	}

	/**
	 * Test constructor with complex nested data.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_with_nested_data() {
		$data = array(
			'type'       => 'Note',
			'content'    => 'Test Content',
			'attachment' => array(
				array(
					'type'      => 'Image',
					'mediaType' => 'image/jpeg',
					'url'       => 'https://example.com/image.jpg',
				),
			),
			'tag'        => array(
				array(
					'type' => 'Mention',
					'name' => '@test',
					'href' => 'https://example.com/@test',
				),
			),
		);

		$transformer = new Json( $data );
		$object      = $transformer->to_object();

		$this->assertInstanceOf( Base_Object::class, $object );
		$this->assertEquals( 'Note', $object->get_type() );
		$this->assertEquals( 'Test Content', $object->get_content() );

		$attachment = $object->get_attachment();
		$this->assertIsArray( $attachment );
		$this->assertEquals( 'Image', $attachment[0]['type'] );

		$tags = $object->get_tag();
		$this->assertIsArray( $tags );
		$this->assertEquals( 'Mention', $tags[0]['type'] );
	}
}
