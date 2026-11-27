<?php
/**
 * Test file for the Registry schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Activity;
use Activitypub\Schema\Actor;
use Activitypub\Schema\Base_Object;
use Activitypub\Schema\Intransitive_Activity;
use Activitypub\Schema\Registry;
use Activitypub\Schema\Request;

/**
 * Test class for the Registry schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Registry
 */
class Test_Registry extends \WP_UnitTestCase {

	/**
	 * A type gets its own schema object, else the one of its kind.
	 *
	 * @dataProvider get_provider
	 * @covers ::get
	 *
	 * @param array  $data  The remote data.
	 * @param string $class The expected schema class.
	 */
	public function test_get( $data, $class ) {
		$this->assertSame( $class, \get_class( Registry::get( $data ) ) );
	}

	/**
	 * Data provider for test_get.
	 *
	 * @return array The remote data and the expected schema class.
	 */
	public function get_provider() {
		return array(
			'accept'          => array( array( 'type' => 'Accept' ), Activity::class ),
			'feature request' => array( array( 'type' => 'FeatureRequest' ), Request::class ),
			'quote request'   => array( array( 'type' => 'QuoteRequest' ), Request::class ),
			'arrive'          => array( array( 'type' => 'Arrive' ), Intransitive_Activity::class ),
			'like'            => array( array( 'type' => 'Like' ), Activity::class ),
			'person'          => array( array( 'type' => 'Person' ), Actor::class ),
			'note'            => array( array( 'type' => 'Note' ), Base_Object::class ),
			'unknown'         => array( array( 'type' => 'Unknown' ), Base_Object::class ),
			'no type'         => array( array(), Base_Object::class ),
			'type not string' => array( array( 'type' => array( 'Accept' ) ), Base_Object::class ),
		);
	}

	/**
	 * A plugin registers the schema object of its type.
	 *
	 * @covers ::get_schemas
	 */
	public function test_plugins_register_types() {
		$register = static function ( $schemas ) {
			$schemas['Like'] = new Request();
			return $schemas;
		};
		\add_filter( 'activitypub_schemas', $register );

		$schema = Registry::get( array( 'type' => 'Like' ) );

		\remove_filter( 'activitypub_schemas', $register );

		$this->assertInstanceOf( Request::class, $schema );
	}
}
