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
		$this->assertSame( $expected, ! \is_wp_error( ( new Collection( $data ) )->validate() ) );
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
			'collection'          => array( $collection, true ),
			'with items'          => array( \array_merge( $collection, array( 'orderedItems' => array( 'https://example.org/actor' ) ) ), true ),
			'items not a list'    => array( \array_merge( $collection, array( 'orderedItems' => array( 'first' => 'https://example.org/actor' ) ) ), false ),
			'item false'          => array( \array_merge( $collection, array( 'orderedItems' => false ) ), false ),
			'item empty string'   => array( \array_merge( $collection, array( 'orderedItems' => '' ) ), false ),
			'item whitespace'     => array( \array_merge( $collection, array( 'orderedItems' => '  ' ) ), false ),
			'no items'            => array( \array_merge( $collection, array( 'orderedItems' => array() ) ), true ),
			'items with false'    => array( \array_merge( $collection, array( 'orderedItems' => array( false ) ) ), false ),
			'single item'         => array( \array_merge( $collection, array( 'orderedItems' => 'https://example.org/actor' ) ), true ),
			'items as objects'    => array(
				\array_merge(
					$collection,
					array(
						'orderedItems' => array(
							array(
								'type' => 'Hashtag',
								'name' => '#a',
							),
						),
					)
				),
				true,
			),
			'embedded first page' => array(
				\array_merge(
					$collection,
					array(
						'first' => array(
							'type' => 'CollectionPage',
							'next' => 'https://example.com/followers?page=2',
						),
					)
				),
				true,
			),
			'first page no type'  => array( \array_merge( $collection, array( 'first' => array( 'next' => 'https://example.com/followers?page=2' ) ) ), false ),
			'negative total'      => array( \array_merge( $collection, array( 'totalItems' => -1 ) ), false ),
			'first not a uri'     => array( \array_merge( $collection, array( 'first' => 'page-1' ) ), false ),
		);
	}

	/**
	 * A single item is handed on as a list, as the follower sync reads it.
	 *
	 * @covers ::sanitize
	 */
	public function test_sanitize_single_item() {
		$collection = ( new Collection(
			array(
				'id'           => 'https://example.com/followers',
				'type'         => 'OrderedCollection',
				'orderedItems' => 'https://example.org/actor',
			)
		) )->sanitize();

		$this->assertSame( array( 'https://example.org/actor' ), $collection['orderedItems'] );
	}
}
