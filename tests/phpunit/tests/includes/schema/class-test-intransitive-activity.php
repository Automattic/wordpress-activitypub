<?php
/**
 * Test file for the Intransitive_Activity schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Intransitive_Activity;

/**
 * Test class for the Intransitive_Activity schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Intransitive_Activity
 */
class Test_Intransitive_Activity extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Intransitive_Activity( $data ) )->validate() ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$arrive = array(
			'id'    => 'https://example.com/arrive/1',
			'type'  => 'Arrive',
			'actor' => 'https://example.com/actor',
		);

		return array(
			'without object' => array( $arrive, true ),
			'without actor'  => array( \array_diff_key( $arrive, array( 'actor' => true ) ), false ),
		);
	}
}
