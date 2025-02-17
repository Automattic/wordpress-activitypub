<?php
/**
 * Test file for Base_Object.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Activity;

use Activitypub\Activity\Base_Object;

/**
 * Test class for Base_Object.
 *
 * @coversDefaultClass \Activitypub\Activity\Base_Object
 */
class Test_Base_Object extends \WP_UnitTestCase {

	/**
	 * Test the to_string method.
	 *
	 * @covers ::to_string
	 */
	public function test_to_string() {
		$base_object = new Base_Object();
		$base_object->set_id( 'https://example.com/test' );

		$this->assertEquals( 'https://example.com/test', $base_object->to_string() );
	}

	/**
	 * Test the magic add method.
	 *
	 * @covers ::add_* Magic function.
	 *
	 * @dataProvider data_magic_add
	 *
	 * @param array $value    The value to add.
	 * @param array $expected The expected value.
	 */
	public function test_magic_add( $value, $expected ) {
		$base_object = new Base_Object();
		$base_object->add_to( $value );

		$this->assertEquals( $expected, $base_object->get_to() );
	}

	/**
	 * Data provider for the magic add method.
	 *
	 * @return array The data provider.
	 */
	public function data_magic_add() {
		return array(
			array( 'value', array( 'value' ) ),
			array( array( 'value' ), array( 'value' ) ),
			array( array( 'value', 'value2' ), array( 'value', 'value2' ) ),
			array( array( 'value', 'value' ), array( 'value' ) ),
		);
	}

	/**
	 * Test init_from_json method.
	 *
	 * @covers ::init_from_json
	 */
	public function test_init_from_json() {
		$invalid_json = '{"@context":https:\/\/www.w3.org\/ns\/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive"}],"id":"https:\/\/example.com\/2","type":"Note","content":"\u003Cp\u003EThis is another note\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EThis is another note\u003C\/p\u003E"},"tag":[],"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"cc":[],"mediaType":"text\/html","sensitive":false}';
		$base_object  = Base_Object::init_from_json( $invalid_json );

		$this->assertInstanceOf( 'WP_Error', $base_object );
	}
}
