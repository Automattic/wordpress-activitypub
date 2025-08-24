<?php
/**
 * Test file for Activitypub Tombstone Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Tombstone;

/**
 * Test class for ActivityPub Tombstone Class
 *
 * @coversDefaultClass \Activitypub\Tombstone
 */
class Test_Tombstone extends \WP_UnitTestCase {

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::is_remote_url_gone
	 *
	 * @dataProvider data_is_remote_url_gone
	 *
	 * @param array $request The request array.
	 * @param bool  $result  The expected result.
	 */
	public function test_is_remote_url_gone( $request, $result ) {
		$fake_request = function () use ( $request ) {
			return $request;
		};
		add_filter( 'pre_http_request', $fake_request, 10, 3 );
		$response = Tombstone::is_remote_url_gone( 'https://fake.test/object/123' );
		$this->assertEquals( $result, $response );
		remove_filter( 'pre_http_request', $fake_request, 10 );
	}

	/**
	 * Data provider for test_is_tombstone.
	 *
	 * @return array
	 */
	public function data_is_remote_url_gone() {
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
	 * @covers ::is_wp_error
	 */
	public function test_is_wp_error() {
		$response = Tombstone::is_wp_error( new \WP_Error( 404 ) );
		$this->assertTrue( $response );

		$response = Tombstone::is_wp_error( new \WP_Error( 410 ) );
		$this->assertTrue( $response );

		$response = Tombstone::is_wp_error( new \WP_Error( 200 ) );
		$this->assertFalse( $response );

		$response = Tombstone::is_wp_error( new \WP_Error( 'foo', '', array( 'status' => 404 ) ) );
		$this->assertTrue( $response );

		$response = Tombstone::is_wp_error( new \WP_Error( 'bar', '', array( 'status' => 410 ) ) );
		$this->assertTrue( $response );

		$response = Tombstone::is_wp_error( new \WP_Error( 'baz', '', array( 'status' => 200 ) ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::is_array_gone
	 */
	public function test_is_array_gone() {
		$response = Tombstone::is_array_gone( array( 'type' => 'Tombstone' ) );
		$this->assertTrue( $response );

		$response = Tombstone::is_array_gone( array( 'type' => 'Note' ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::is_object_gone
	 */
	public function test_is_object_gone() {
		$response = Tombstone::is_object_gone( (object) array( 'type' => 'Tombstone' ) );
		$this->assertTrue( $response );

		$response = Tombstone::is_object_gone( (object) array( 'type' => 'Note' ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::is_local_url_gone
	 */
	public function test_is_local_url_gone() {
		$url = 'https://fake.test/object/123';

		$response = Tombstone::is_local_url_gone( $url );
		$this->assertFalse( $response );

		Tombstone::bury( $url );

		$response = Tombstone::is_local_url_gone( $url );
		$this->assertTrue( $response );

		\delete_option( 'activitypub_tombstone_urls' );
	}
}
