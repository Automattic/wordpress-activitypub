<?php
/**
 * Test file for the Request schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Request;

/**
 * Test class for the Request schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Request
 */
class Test_Request extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new class( $data ) extends Request {} )->validate() ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$request = array(
			'id'         => 'https://example.com/request/1',
			'type'       => 'QuoteRequest',
			'actor'      => 'https://example.com/actor',
			'object'     => 'https://local.example/post/1',
			'instrument' => 'https://example.com/note/1',
		);

		return array(
			'quote request'         => array( $request, true ),
			'feature request'       => array( \array_merge( $request, array( 'type' => 'FeatureRequest' ) ), true ),
			'instrument as object'  => array( \array_merge( $request, array( 'instrument' => array( 'id' => 'https://example.com/note/1' ) ) ), true ),
			'without instrument'    => array( \array_diff_key( $request, array( 'instrument' => true ) ), false ),
			'instrument other host' => array( \array_merge( $request, array( 'instrument' => 'https://example.org/note/1' ) ), false ),
		);
	}
}
