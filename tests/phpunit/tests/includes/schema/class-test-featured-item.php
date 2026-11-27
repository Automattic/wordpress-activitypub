<?php
/**
 * Test file for the Featured_Item schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Featured_Item;

/**
 * Test class for the Featured_Item schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Featured_Item
 */
class Test_Featured_Item extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Featured_Item() )->validate( $data ) ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$item = array(
			'id'             => 'https://example.com/featured/1',
			'type'           => 'FeaturedItem',
			'featuredObject' => 'https://example.com/actor',
		);

		return array(
			'item'             => array( $item, true ),
			'without object'   => array( \array_diff_key( $item, array( 'featuredObject' => true ) ), false ),
			'object not a uri' => array( \array_merge( $item, array( 'featuredObject' => 'actor' ) ), false ),
		);
	}
}
