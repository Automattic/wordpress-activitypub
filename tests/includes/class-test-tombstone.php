<?php
/**
 * Test file for Activitypub HTTP Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Tombstone;

/**
 * Test class for ActivityPub HTTP Class
 *
 * @coversDefaultClass \Activitypub\Http
 */
class Test_Tombstone extends \WP_UnitTestCase {

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::check_remote_url
	 *
	 * @dataProvider data_check_remote_url
	 *
	 * @param array $request The request array.
	 * @param bool  $result  The expected result.
	 */
	public function test_check_remote_url( $request, $result ) {
		$fake_request = function () use ( $request ) {
			return $request;
		};
		add_filter( 'pre_http_request', $fake_request, 10, 3 );
		$response = Tombstone::check_remote_url( 'https://fake.test/object/123' );
		$this->assertEquals( $result, $response );
		remove_filter( 'pre_http_request', $fake_request, 10 );
	}

	/**
	 * Data provider for test_is_tombstone.
	 *
	 * @return array
	 */
	public function data_check_remote_url() {
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

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::check_wp_error
	 */
	public function test_check_wp_error() {
		$response = Tombstone::check_wp_error( new \WP_Error( 404 ) );
		$this->assertTrue( $response );

		$response = Tombstone::check_wp_error( new \WP_Error( 410 ) );
		$this->assertTrue( $response );

		$response = Tombstone::check_wp_error( new \WP_Error( 200 ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::check_array
	 */
	public function test_check_array() {
		$response = Tombstone::check_array( array( 'type' => 'Tombstone' ) );
		$this->assertTrue( $response );

		$response = Tombstone::check_array( array( 'type' => 'Note' ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::check_object
	 */
	public function test_check_object() {
		$response = Tombstone::check_object( (object) array( 'type' => 'Tombstone' ) );
		$this->assertTrue( $response );

		$response = Tombstone::check_object( (object) array( 'type' => 'Note' ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::check_local_url
	 */
	public function test_check_local_url() {
		$url = 'https://fake.test/object/123';

		$response = Tombstone::check_local_url( $url );
		$this->assertFalse( $response );

		Tombstone::bury( $url );

		$response = Tombstone::check_local_url( $url );
		$this->assertTrue( $response );

		\delete_option( 'activitypub_tombstone_urls' );
	}
}
