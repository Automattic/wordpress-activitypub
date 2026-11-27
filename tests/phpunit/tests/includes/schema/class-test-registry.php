<?php
/**
 * Test file for the Registry schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Activity;
use Activitypub\Schema\Activity_Object;
use Activitypub\Schema\Actor;
use Activitypub\Schema\Arrive;
use Activitypub\Schema\Collection;
use Activitypub\Schema\Cryptographic_Key;
use Activitypub\Schema\Image;
use Activitypub\Schema\Key;
use Activitypub\Schema\Note;
use Activitypub\Schema\Page;
use Activitypub\Schema\Question;
use Activitypub\Schema\Quote_Authorization;
use Activitypub\Schema\Quote_Request;
use Activitypub\Schema\Registry;
use Activitypub\Schema\Request;
use Activitypub\Schema\Unexpected;


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
	 * @param string $expected The expected schema class.
	 */
	public function test_get( $data, $expected ) {
		$this->assertSame( $expected, \get_class( Registry::get( $data ) ) );
	}

	/**
	 * A limited registry only finds the schemas of its kind.
	 *
	 * @dataProvider limit_provider
	 * @covers ::limit
	 * @covers ::get
	 *
	 * @param array  $data     The remote data.
	 * @param string $expected The expected schema class.
	 */
	public function test_limit( $data, $expected ) {
		$this->assertSame( $expected, \get_class( Registry::get( $data )->limit( Activity::class ) ) );
	}

	/**
	 * Data provider for test_limit.
	 *
	 * @return array The remote data and the expected schema class.
	 */
	public function limit_provider() {
		return array(
			'activity'            => array( array( 'type' => 'Like' ), Activity::class ),
			'activity of its own' => array( array( 'type' => 'QuoteRequest' ), Quote_Request::class ),
			'post'                => array( array( 'type' => 'Note' ), Unexpected::class ),
			'collection'          => array( array( 'type' => 'OrderedCollection' ), Unexpected::class ),
			'actor'               => array( array( 'type' => 'Person' ), Unexpected::class ),
			'unknown'             => array( array( 'type' => 'Unknown' ), Unexpected::class ),
		);
	}

	/**
	 * Data provider for test_get.
	 *
	 * @return array The remote data and the expected schema class.
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
			'unknown'             => array( array( 'type' => 'Unknown' ), Unexpected::class ),
			'names another class' => array( array( 'type' => 'Registry' ), Unexpected::class ),
			'names the root'      => array( array( 'type' => 'BaseObject' ), Unexpected::class ),
			'names a group'       => array( array( 'type' => 'Request' ), Unexpected::class ),
			'not letters'         => array( array( 'type' => 'Schema\\Activity' ), Unexpected::class ),
			'no type'             => array( array(), Unexpected::class ),
			'key without type'    => array(
				array(
					'id'           => 'https://example.com/actor#main-key',
					'publicKeyPem' => 'pem',
				),
				Key::class,
			),
			'typed, with a pem'   => array(
				array(
					'type'         => 'Unknown',
					'publicKeyPem' => 'pem',
				),
				Unexpected::class,
			),
			'type not string'     => array( array( 'type' => array( 'Accept' ) ), Unexpected::class ),
		);
	}

	/**
	 * A plugin returns the schema object of its type.
	 *
	 * @covers ::get
	 */
	public function test_filter() {
		$filter = static function ( $schema, $data, $type ) {
			return 'Like' === $type ? new Quote_Request( $data ) : $schema;
		};
		\add_filter( 'activitypub_schema', $filter, 10, 3 );

		$like   = Registry::get( array( 'type' => 'Like' ) );
		$follow = Registry::get( array( 'type' => 'Follow' ) );

		\remove_filter( 'activitypub_schema', $filter );

		$this->assertInstanceOf( Request::class, $like );
		$this->assertSame( Activity::class, \get_class( $follow ) );
	}
}
