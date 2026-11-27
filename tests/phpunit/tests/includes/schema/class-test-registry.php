<?php
/**
 * Test file for the Registry schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Activity\Activity as Activity_Model;
use Activitypub\Schema\Activity;
use Activitypub\Schema\Activity_Object;
use Activitypub\Schema\Actor;
use Activitypub\Schema\Arrive;
use Activitypub\Schema\Collection;
use Activitypub\Schema\Cryptographic_Key;
use Activitypub\Schema\Image;
use Activitypub\Schema\Note;
use Activitypub\Schema\Page;
use Activitypub\Schema\Question;
use Activitypub\Schema\Quote_Authorization;
use Activitypub\Schema\Quote_Request;
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
	 * @param array  $data     The remote data.
	 * @param string $expected The expected schema class, or the error code.
	 */
	public function test_get( $data, $expected ) {
		$schema = ( new Registry() )->get( $data );

		$this->assertSame( $expected, \is_wp_error( $schema ) ? $schema->get_error_code() : \get_class( $schema ) );
	}

	/**
	 * A limited registry only finds the schemas of its kind.
	 *
	 * @dataProvider limit_provider
	 * @covers ::limit
	 * @covers ::get
	 *
	 * @param array  $data     The remote data.
	 * @param string $expected The expected schema class, or the error code.
	 */
	public function test_limit( $data, $expected ) {
		$schema = Registry::limit( Activity::class )->get( $data );

		$this->assertSame( $expected, \is_wp_error( $schema ) ? $schema->get_error_code() : \get_class( $schema ) );
	}

	/**
	 * Data provider for test_limit.
	 *
	 * @return array The remote data and the expected schema class or error code.
	 */
	public function limit_provider() {
		return array(
			'activity'            => array( array( 'type' => 'Like' ), Activity::class ),
			'activity of its own' => array( array( 'type' => 'QuoteRequest' ), Quote_Request::class ),
			'post'                => array( array( 'type' => 'Note' ), 'activitypub_unexpected_type' ),
			'collection'          => array( array( 'type' => 'OrderedCollection' ), 'activitypub_unexpected_type' ),
			'actor'               => array( array( 'type' => 'Person' ), 'activitypub_unexpected_type' ),
			'unknown'             => array( array( 'type' => 'Unknown' ), 'activitypub_unknown_type' ),
		);
	}

	/**
	 * Data provider for test_get.
	 *
	 * @return array The remote data and the expected schema class or error code.
	 */
	public function get_provider() {
		return array(
			'own class'           => array( array( 'type' => 'QuoteRequest' ), Quote_Request::class ),
			'own class, vocab'    => array( array( 'type' => 'Arrive' ), Arrive::class ),
			'own class, object'   => array( array( 'type' => 'Image' ), Image::class ),
			'own class, poll'     => array( array( 'type' => 'Question' ), Question::class ),
			'own class, stamp'    => array( array( 'type' => 'QuoteAuthorization' ), Quote_Authorization::class ),
			'own class, key'      => array( array( 'type' => 'CryptographicKey' ), Cryptographic_Key::class ),
			'activity kind'       => array( array( 'type' => 'Like' ), Activity::class ),
			'actor kind'          => array( array( 'type' => 'Person' ), Actor::class ),
			'collection kind'     => array( array( 'type' => 'OrderedCollection' ), Collection::class ),
			'own class, post'     => array( array( 'type' => 'Note' ), Note::class ),
			'own class, page'     => array( array( 'type' => 'Page' ), Page::class ),
			'object kind'         => array( array( 'type' => 'Tombstone' ), Activity_Object::class ),
			'unknown'             => array( array( 'type' => 'Unknown' ), 'activitypub_unknown_type' ),
			'names another class' => array( array( 'type' => 'Registry' ), 'activitypub_unknown_type' ),
			'not letters'         => array( array( 'type' => 'Schema\\Activity' ), 'activitypub_unknown_type' ),
			'no type'             => array( array(), 'activitypub_unknown_type' ),
			'type not string'     => array( array( 'type' => array( 'Accept' ) ), 'activitypub_unknown_type' ),
		);
	}

	/**
	 * A plugin returns the schema object of its type.
	 *
	 * @covers ::get
	 */
	public function test_filter() {
		$filter = static function ( $schema, $data, $type ) {
			return 'Like' === $type ? new Request() : $schema;
		};
		\add_filter( 'activitypub_schema', $filter, 10, 3 );

		$like   = ( new Registry() )->get( array( 'type' => 'Like' ) );
		$follow = ( new Registry() )->get( array( 'type' => 'Follow' ) );

		\remove_filter( 'activitypub_schema', $filter );

		$this->assertInstanceOf( Request::class, $like );
		$this->assertSame( Activity::class, \get_class( $follow ) );
	}

	/**
	 * A property a type adds is defined alike by every activity type, so the inbox can check and
	 * normalize it with one route arg. A type may narrow a property of `Activity`, such as the
	 * embedded object of a `Create`: the route arg is the one of `Activity`.
	 *
	 * @covers ::get
	 */
	public function test_activity_schemas_define_a_property_alike() {
		$definitions = array();
		$activity    = ( new Activity() )->get_schema()['properties'];

		foreach ( Activity_Model::TYPES as $type ) {
			$schema = ( new Registry() )->get( array( 'type' => $type ) );

			$this->assertInstanceOf( Activity::class, $schema, $type );

			foreach ( \array_diff_key( $schema->get_schema()['properties'], $activity ) as $name => $property ) {
				// A callback on the schema itself is compared by its method, not by the instance.
				$definition = \wp_json_encode( $property );

				if ( ! isset( $definitions[ $name ] ) ) {
					$definitions[ $name ] = $definition;
				}

				$this->assertSame( $definitions[ $name ], $definition, \sprintf( '%s defines "%s" differently.', $type, $name ) );
			}
		}
	}
}
