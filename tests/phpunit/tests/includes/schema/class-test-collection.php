<?php
/**
 * Test file for the Collection schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Collection;

/**
 * Test class for the Collection schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Collection
 */
class Test_Collection extends \WP_UnitTestCase {

	/**
	 * Test validate.
	 *
	 * @dataProvider validate_provider
	 * @covers ::validate
	 * @covers ::get_schema
	 *
	 * @param array $data     The remote data.
	 * @param bool  $expected Whether it is valid.
	 */
	public function test_validate( $data, $expected ) {
		$this->assertSame( $expected, ! \is_wp_error( ( new Collection() )->validate( $data ) ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$collection = array(
			'id'         => 'https://example.com/followers',
			'type'       => 'OrderedCollection',
			'totalItems' => 2,
			'first'      => 'https://example.com/followers?page=1',
		);

		return array(
			'collection'       => array( $collection, true ),
			'with items'       => array( \array_merge( $collection, array( 'orderedItems' => array( 'https://example.org/actor' ) ) ), true ),
			'items not a list' => array( \array_merge( $collection, array( 'orderedItems' => array( 'first' => 'https://example.org/actor' ) ) ), false ),
			'negative total'   => array( \array_merge( $collection, array( 'totalItems' => -1 ) ), false ),
			'first not a uri'  => array( \array_merge( $collection, array( 'first' => 'page-1' ) ), false ),
		);
	}
}
