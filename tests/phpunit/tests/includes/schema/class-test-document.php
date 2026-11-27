<?php
/**
 * Test file for the Document schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Document;

/**
 * Test class for the Document schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Document
 */
class Test_Document extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Document() )->validate( $data ) ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$image = array(
			'id'   => 'https://example.com/image/1',
			'type' => 'Image',
			'url'  => 'https://cdn.example/photo.jpg',
		);

		return array(
			'image'         => array( $image, true ),
			'url as link'   => array(
				\array_merge(
					$image,
					array(
						'url' => array(
							'type' => 'Link',
							'href' => 'https://cdn.example/photo.jpg',
						),
					)
				),
				true,
			),
			'without url'   => array( \array_diff_key( $image, array( 'url' => true ) ), false ),
			'url not a uri' => array( \array_merge( $image, array( 'url' => 'photo.jpg' ) ), false ),
		);
	}
}
