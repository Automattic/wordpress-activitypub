<?php
/**
 * Test file for the Reject schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Reject;

/**
 * Test class for the Reject schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Reject
 */
class Test_Reject extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Reject() )->validate( $data ) ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$reject = array(
			'id'     => 'https://example.com/reject/1',
			'type'   => 'Reject',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'id'     => 'https://local.example/follow/1',
				'type'   => 'Follow',
				'actor'  => 'https://local.example/actor',
				'object' => 'https://example.com/actor',
			),
		);

		return array(
			'reject'                => array( $reject, true ),
			'object only an id'     => array( \array_merge( $reject, array( 'object' => 'https://local.example/follow/1' ) ), false ),
			'object without actor'  => array(
				\array_merge(
					$reject,
					array(
						'object' => array(
							'id'     => 'https://local.example/follow/1',
							'type'   => 'Follow',
							'object' => 'https://example.com/actor',
						),
					)
				),
				false,
			),
			'object without object' => array(
				\array_merge(
					$reject,
					array(
						'object' => array(
							'id'    => 'https://local.example/follow/1',
							'type'  => 'Follow',
							'actor' => 'https://local.example/actor',
						),
					)
				),
				false,
			),
			'object without type'   => array(
				\array_merge(
					$reject,
					array(
						'object' => array(
							'id'     => 'https://local.example/follow/1',
							'actor'  => 'https://local.example/actor',
							'object' => 'https://example.com/actor',
						),
					)
				),
				false,
			),
		);
	}
}
