<?php
/**
 * Test file for Activitypub HTTP Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Http;

/**
 * Test class for ActivityPub HTTP Class
 *
 * @coversDefaultClass \Activitypub\Http
 */
class Test_Http extends \WP_UnitTestCase {

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::is_tombstone
	 *
	 * @dataProvider data_is_tombstone
	 *
	 * @param array $request The request array.
	 * @param bool  $result  The expected result.
	 */
	public function test_is_tombstone( $request, $result ) {
		$fake_request = function () use ( $request ) {
			return $request;
		};
		add_filter( 'pre_http_request', $fake_request, 10, 3 );
		$response = Http::is_tombstone( 'https://fake.test/object/123' );
		$this->assertEquals( $result, $response );
		remove_filter( 'pre_http_request', $fake_request, 10 );
	}

	/**
	 * Data provider for test_is_tombstone.
	 *
	 * @return array
	 */
	public function data_is_tombstone() {
		return array(
			array( array( 'response' => array( 'code' => 404 ) ), true ),
			array( array( 'response' => array( 'code' => 410 ) ), true ),
			array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => '',
				),
				false,
			),
			array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => '{}',
				),
				false,
			),
			array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"type": "Note"}',
				),
				false,
			),
			array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"type": "Tombstone"}',
				),
				true,
			),
			array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"foo": "bar"}',
				),
				false,
			),
		);
	}
}
