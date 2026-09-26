<?php
/**
 * Test file for the Http class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Http;

/**
 * Test class for Http.
 *
 * @coversDefaultClass \Activitypub\Http
 */
class Test_Http extends \WP_UnitTestCase {

	/**
	 * When caching is not requested (the default), the response is not stored in a transient — a
	 * one-off large fetch must not end up in the options table.
	 *
	 * @covers ::get
	 */
	public function test_get_does_not_store_transient_by_default() {
		$url           = 'https://social.example.com/starter-kit';
		$transient_key = Http::generate_cache_key( $url );
		$mock          = static function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"type":"Collection"}',
				'headers'  => array(),
			);
		};

		\delete_transient( $transient_key );
		\add_filter( 'pre_http_request', $mock, 1 );
		$result = Http::get( $url );
		\remove_filter( 'pre_http_request', $mock, 1 );

		$this->assertNotWPError( $result );
		$this->assertFalse( \get_transient( $transient_key ) );
	}

	/**
	 * When caching is requested, a successful response is stored in a transient.
	 *
	 * @covers ::get
	 */
	public function test_get_stores_transient_when_caching_is_requested() {
		$url           = 'https://social.example.com/actor';
		$transient_key = Http::generate_cache_key( $url );
		$mock          = static function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"type":"Person"}',
				'headers'  => array(),
			);
		};

		\delete_transient( $transient_key );
		\add_filter( 'pre_http_request', $mock, 1 );
		$result = Http::get( $url, array(), true );
		\remove_filter( 'pre_http_request', $mock, 1 );

		$this->assertNotWPError( $result );
		$this->assertNotFalse( \get_transient( $transient_key ) );

		\delete_transient( $transient_key );
	}

	/**
	 * When caching is requested, a failed response is stored in a transient so repeated requests to
	 * an unreachable host back off instead of waiting for the timeout every time.
	 *
	 * @covers ::get
	 */
	public function test_get_stores_error_transient_when_caching_is_requested() {
		$url           = 'https://social.example.com/gone';
		$transient_key = Http::generate_cache_key( $url );
		$mock          = static function () {
			return array(
				'response' => array( 'code' => 404 ),
				'body'     => '',
				'headers'  => array(),
			);
		};

		\delete_transient( $transient_key );
		\add_filter( 'pre_http_request', $mock, 1 );
		$result = Http::get( $url, array(), true );
		\remove_filter( 'pre_http_request', $mock, 1 );

		$this->assertWPError( $result );
		$this->assertWPError( \get_transient( $transient_key ) );

		\delete_transient( $transient_key );
	}

	/**
	 * A transport failure has no response code, so its own message is the only thing that says what
	 * went wrong. It must survive, or the request fails with nothing to report.
	 *
	 * @covers ::get
	 */
	public function test_get_keeps_the_transport_error() {
		$url  = 'https://social.example.com/unreachable';
		$mock = static function () {
			return new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out.' );
		};

		\add_filter( 'pre_http_request', $mock, 1 );
		$result = Http::get( $url );
		\remove_filter( 'pre_http_request', $mock, 1 );

		$this->assertWPError( $result );
		$this->assertSame( 'http_request_failed', $result->get_error_code() );
		$this->assertSame( 'cURL error 28: Operation timed out.', $result->get_error_message() );
		$this->assertSame( 0, $result->get_error_data()['status'] );
	}

	/**
	 * A failing HTTP response keeps using its status as the error code, which callers read as one.
	 *
	 * @covers ::get
	 */
	public function test_get_uses_the_response_code_as_the_error_code() {
		$url  = 'https://social.example.com/missing';
		$mock = static function () {
			return array(
				'response' => array( 'code' => 404 ),
				'body'     => '',
				'headers'  => array(),
			);
		};

		\add_filter( 'pre_http_request', $mock, 1 );
		$result = Http::get( $url );
		\remove_filter( 'pre_http_request', $mock, 1 );

		$this->assertWPError( $result );
		$this->assertSame( 404, $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}
}
