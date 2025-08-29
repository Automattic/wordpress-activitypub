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
	 * @covers ::exists_remote
	 *
	 * @dataProvider data_exists_remote
	 *
	 * @param array $request The request array.
	 * @param bool  $result  The expected result.
	 */
	public function test_exists_remote( $request, $result ) {
		$fake_request = function () use ( $request ) {
			return $request;
		};
		add_filter( 'pre_http_request', $fake_request, 10, 3 );
		$response = Tombstone::exists_remote( 'https://fake.test/object/123' );
		$this->assertEquals( $result, $response );
		remove_filter( 'pre_http_request', $fake_request, 10 );
	}

	/**
	 * Data provider for test_exists_remote.
	 *
	 * @return array
	 */
	public function data_exists_remote() {
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
	 * @covers ::exists_in_error
	 */
	public function test_exists_in_error() {
		$response = Tombstone::exists_in_error( new \WP_Error( 404 ) );
		$this->assertFalse( $response );

		$response = Tombstone::exists_in_error( new \WP_Error( 410 ) );
		$this->assertFalse( $response );

		$response = Tombstone::exists_in_error( new \WP_Error( 200 ) );
		$this->assertFalse( $response );

		$response = Tombstone::exists_in_error( new \WP_Error( 'foo', '', array( 'status' => 404 ) ) );
		$this->assertTrue( $response );

		$response = Tombstone::exists_in_error( new \WP_Error( 'bar', '', array( 'status' => 410 ) ) );
		$this->assertTrue( $response );

		$response = Tombstone::exists_in_error( new \WP_Error( 'baz', '', array( 'status' => 200 ) ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::check_array
	 */
	public function test_check_array() {
		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Tombstone::class );
		$method     = $reflection->getMethod( 'check_array' );
		$method->setAccessible( true );

		$response = $method->invokeArgs( null, array( array( 'type' => 'Tombstone' ) ) );
		$this->assertTrue( $response );

		$response = $method->invokeArgs( null, array( array( 'type' => 'Note' ) ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::check_object
	 */
	public function test_check_object() {
		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( Tombstone::class );
		$method     = $reflection->getMethod( 'check_object' );
		$method->setAccessible( true );

		$response = $method->invokeArgs( null, array( (object) array( 'type' => 'Tombstone' ) ) );
		$this->assertTrue( $response );

		$response = $method->invokeArgs( null, array( (object) array( 'type' => 'Note' ) ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::exists_local
	 */
	public function test_exists_local() {
		$url = 'https://fake.test/object/123';

		$response = Tombstone::exists_local( $url );
		$this->assertFalse( $response );

		Tombstone::bury( $url );

		$response = Tombstone::exists_local( $url );
		$this->assertTrue( $response );

		\delete_option( 'activitypub_tombstone_urls' );
	}

	/**
	 * Tests that the remove method removes a URL from the tombstone list,
	 * so that exists_local returns false after removing.
	 *
	 * @covers ::remove
	 */
	public function test_remove() {
		$url = 'https://fake.test/object/123';

		Tombstone::bury( $url );

		$response = Tombstone::exists_local( $url );
		$this->assertTrue( $response );

		Tombstone::remove( $url );

		$response = Tombstone::exists_local( $url );
		$this->assertFalse( $response );
	}
}
