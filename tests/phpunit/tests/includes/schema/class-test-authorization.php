<?php
/**
 * Test file for the Authorization schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Authorization;

/**
 * Test class for the Authorization schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Authorization
 */
class Test_Authorization extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Authorization() )->validate( $data ) ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$stamp = array(
			'id'                => 'https://example.com/stamp/1',
			'type'              => 'QuoteAuthorization',
			'attributedTo'      => 'https://example.com/actor',
			'interactingObject' => 'https://example.org/note/1',
			'interactionTarget' => 'https://example.com/note/1',
		);

		return array(
			'stamp'                      => array( $stamp, true ),
			'feature authorization'      => array( \array_merge( $stamp, array( 'type' => 'FeatureAuthorization' ) ), true ),
			'without issuer'             => array( \array_diff_key( $stamp, array( 'attributedTo' => true ) ), false ),
			'without interacting object' => array( \array_diff_key( $stamp, array( 'interactingObject' => true ) ), false ),
			'without target'             => array( \array_diff_key( $stamp, array( 'interactionTarget' => true ) ), false ),
			'target not a uri'           => array( \array_merge( $stamp, array( 'interactionTarget' => 'note' ) ), false ),
		);
	}
}
