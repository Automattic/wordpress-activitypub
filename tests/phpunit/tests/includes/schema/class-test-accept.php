<?php
/**
 * Test file for the Accept schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Accept;

/**
 * Test class for the Accept schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Accept
 */
class Test_Accept extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Accept() )->validate( $data ) ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$accept = array(
			'id'     => 'https://example.com/accept/1',
			'type'   => 'Accept',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'id'     => 'https://local.example/follow/1',
				'type'   => 'Follow',
				'actor'  => 'https://local.example/actor',
				'object' => 'https://example.com/actor',
			),
		);

		return array(
			'accept'                => array( $accept, true ),
			'object only an id'     => array( \array_merge( $accept, array( 'object' => 'https://local.example/follow/1' ) ), false ),
			'object without actor'  => array(
				\array_merge(
					$accept,
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
					$accept,
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
					$accept,
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
