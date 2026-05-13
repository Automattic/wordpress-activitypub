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
		remove_filter( 'pre_http_request', $fake_request );
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
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

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
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$response = $method->invokeArgs( null, array( (object) array( 'type' => 'Tombstone' ) ) );
		$this->assertTrue( $response );

		$response = $method->invokeArgs( null, array( (object) array( 'type' => 'Note' ) ) );
		$this->assertFalse( $response );
	}

	/**
	 * Response code is 404 -> is_tombstone returns true
	 *
	 * @covers ::bury
	 */
	public function test_bury() {
		$url = 'https://fake.test/object/123';

		$response = Tombstone::exists_local( $url );
		$this->assertFalse( $response );

		Tombstone::bury( $url );

		$response = Tombstone::exists_local( $url );
		$this->assertTrue( $response );
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

	/**
	 * Tests that bury does not add empty strings to the tombstone registry.
	 *
	 * @covers ::bury
	 */
	public function test_bury_empty_string() {
		Tombstone::bury( '' );

		$this->assertFalse( Tombstone::exists_local( '' ) );
	}

	/**
	 * Tests that bury does not add invalid URLs to the tombstone registry.
	 *
	 * @covers ::bury
	 */
	public function test_bury_invalid_url() {
		Tombstone::bury( 'not-a-valid-url' );
		Tombstone::bury( 'also not valid' );
		Tombstone::bury( '/relative/path' );

		$this->assertFalse( Tombstone::exists_local( 'not-a-valid-url' ) );
		$this->assertFalse( Tombstone::exists_local( 'also not valid' ) );
		$this->assertFalse( Tombstone::exists_local( '/relative/path' ) );
	}

	/**
	 * Tests that bury handles duplicate URLs properly.
	 *
	 * @covers ::bury
	 */
	public function test_bury_duplicate_urls() {
		$url = 'https://fake.test/object/duplicate';

		Tombstone::bury( $url );
		Tombstone::bury( $url );
		Tombstone::bury( $url );

		$ids = \get_posts(
			array(
				'post_type'      => Tombstone::POST_TYPE,
				'name'           => \md5( \Activitypub\normalize_url( $url ) ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$this->assertCount( 1, $ids );
	}

	/**
	 * Tests that remove handles empty strings gracefully.
	 *
	 * @covers ::remove
	 */
	public function test_remove_empty_string() {
		$url = 'https://fake.test/object/remove-test';
		Tombstone::bury( $url );

		Tombstone::remove( '' );

		$this->assertTrue( Tombstone::exists_local( $url ) );
	}

	/**
	 * Tests that remove handles invalid URLs gracefully.
	 *
	 * @covers ::remove
	 */
	public function test_remove_invalid_url() {
		$url = 'https://fake.test/object/remove-invalid';
		Tombstone::bury( $url );

		Tombstone::remove( 'not-a-valid-url' );

		$this->assertTrue( Tombstone::exists_local( $url ) );
	}

	/**
	 * Tests that remove handles non-existent URLs gracefully.
	 *
	 * @covers ::remove
	 */
	public function test_remove_nonexistent_url() {
		$url = 'https://fake.test/object/exists';
		Tombstone::bury( $url );

		// Remove a URL that was never added.
		Tombstone::remove( 'https://fake.test/object/never-added' );

		// Original URL should still be there.
		$this->assertTrue( Tombstone::exists_local( $url ) );
	}

	/**
	 * Tests that exists_local normalizes URLs with ActivityPub query params.
	 *
	 * @covers ::exists_local
	 */
	public function test_exists_local_normalizes_activitypub_param() {
		$url = 'https://fake.test/object/normalize';

		Tombstone::bury( $url );

		// Should match even with activitypub query param.
		$url_with_param = $url . '?activitypub=1';
		$this->assertTrue( Tombstone::exists_local( $url_with_param ) );
	}

	/**
	 * Tests that exists_local normalizes URLs with preview query params.
	 *
	 * @covers ::exists_local
	 */
	public function test_exists_local_normalizes_preview_param() {
		$url = 'https://fake.test/object/preview-test';

		Tombstone::bury( $url );

		// Should match even with preview query param.
		$url_with_param = $url . '?preview=1';
		$this->assertTrue( Tombstone::exists_local( $url_with_param ) );
	}

	/**
	 * Tests that exists_local handles URLs with multiple query params.
	 *
	 * @covers ::exists_local
	 */
	public function test_exists_local_normalizes_multiple_params() {
		$url = 'https://fake.test/object/multi-param';

		Tombstone::bury( $url );

		// Should match even with both activitypub and preview query params.
		$url_with_params = $url . '?activitypub=1&preview=1';
		$this->assertTrue( Tombstone::exists_local( $url_with_params ) );
	}

	/**
	 * Tests that bury accepts multiple URLs at once.
	 *
	 * @covers ::bury
	 */
	public function test_bury_multiple_urls() {
		$url1 = 'https://fake.test/object/multi-1';
		$url2 = 'https://fake.test/object/multi-2';
		$url3 = 'https://fake.test/object/multi-3';

		// Bury multiple URLs in a single call.
		Tombstone::bury( $url1, $url2, $url3 );

		// All URLs should be buried.
		$this->assertTrue( Tombstone::exists_local( $url1 ) );
		$this->assertTrue( Tombstone::exists_local( $url2 ) );
		$this->assertTrue( Tombstone::exists_local( $url3 ) );
	}

	/**
	 * Tests that bury with multiple URLs filters out invalid ones.
	 *
	 * @covers ::bury
	 */
	public function test_bury_multiple_urls_with_invalid() {
		$valid_url   = 'https://fake.test/object/valid';
		$invalid_url = 'not-a-valid-url';

		Tombstone::bury( $valid_url, $invalid_url );

		$this->assertTrue( Tombstone::exists_local( $valid_url ) );
		$this->assertFalse( Tombstone::exists_local( $invalid_url ) );
	}

	/**
	 * Tests that remove accepts multiple URLs at once.
	 *
	 * @covers ::remove
	 */
	public function test_remove_multiple_urls() {
		$url1 = 'https://fake.test/object/remove-multi-1';
		$url2 = 'https://fake.test/object/remove-multi-2';
		$url3 = 'https://fake.test/object/remove-multi-3';

		// Bury all URLs first.
		Tombstone::bury( $url1, $url2, $url3 );

		// Verify they are all buried.
		$this->assertTrue( Tombstone::exists_local( $url1 ) );
		$this->assertTrue( Tombstone::exists_local( $url2 ) );
		$this->assertTrue( Tombstone::exists_local( $url3 ) );

		// Remove multiple URLs in a single call.
		Tombstone::remove( $url1, $url2, $url3 );

		// All URLs should be removed.
		$this->assertFalse( Tombstone::exists_local( $url1 ) );
		$this->assertFalse( Tombstone::exists_local( $url2 ) );
		$this->assertFalse( Tombstone::exists_local( $url3 ) );
	}

	/**
	 * Tests that remove with multiple URLs filters out invalid ones.
	 *
	 * @covers ::remove
	 */
	public function test_remove_multiple_urls_with_invalid() {
		$url1 = 'https://fake.test/object/remove-valid-1';
		$url2 = 'https://fake.test/object/remove-valid-2';

		// Bury URLs first.
		Tombstone::bury( $url1, $url2 );

		// Remove with a mix of valid and invalid URLs.
		Tombstone::remove( $url1, 'not-a-valid-url', $url2 );

		// Both valid URLs should be removed.
		$this->assertFalse( Tombstone::exists_local( $url1 ) );
		$this->assertFalse( Tombstone::exists_local( $url2 ) );
	}
}
