<?php
/**
 * Test file for the Create schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Create;

/**
 * Test class for the Create schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Create
 */
class Test_Create extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Create() )->validate( $data ) ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$create = array(
			'id'     => 'https://example.com/create/1',
			'type'   => 'Create',
			'actor'  => 'https://example.com/actor',
			'object' => array(
				'id'      => 'https://example.com/note/1',
				'type'    => 'Note',
				'content' => 'Hi',
			),
		);

		return array(
			'create'                 => array( $create, true ),
			'object only an id'      => array( \array_merge( $create, array( 'object' => 'https://example.com/note/1' ) ), false ),
			'object without id'      => array(
				\array_merge(
					$create,
					array(
						'object' => array(
							'type'    => 'Note',
							'content' => 'Hi',
						),
					)
				),
				false,
			),
			'object without content' => array(
				\array_merge(
					$create,
					array(
						'object' => array(
							'id'   => 'https://example.com/note/1',
							'type' => 'Note',
						),
					)
				),
				false,
			),
			'empty content'          => array(
				\array_merge(
					$create,
					array(
						'object' => array(
							'id'      => 'https://example.com/note/1',
							'type'    => 'Note',
							'content' => '',
						),
					)
				),
				true,
			),
		);
	}
}
