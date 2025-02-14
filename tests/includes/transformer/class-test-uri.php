<?php
/**
 * URI Transformer Test Class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Http;
use Activitypub\Transformer\Uri;

/**
 * URI Transformer Test Class.
 */
class Test_Uri extends \WP_UnitTestCase {
	/**
	 * Test successful URI transformation.
	 */
	public function test_successful_uri_transformation() {
		// Mock-Daten für die HTTP-Antwor;
		$fake_request = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'id' => 'https://example.com/activity/1',
						'type' => 'Note',
						'content' => 'Test Content',
					),
				),
			);
		};

		add_filter( 'pre_http_request', $fake_request, 10 );

		$uri_transformer = new Uri( 'https://example.com/activity/1' );
		$result = $uri_transformer->to_object();

		$this->assertIsObject( $result );
		$this->assertEquals( 'https://example.com/activity/1', $result->get_id() );
		$this->assertEquals( 'Note', $result->get_type() );
		$this->assertEquals( 'Test Content', $result->get_content() );

		remove_filter( 'pre_http_request', $fake_request, 10 );
	}

	/**
	 * Test URI transformation with error.
	 */
	public function test_uri_transformation_error() {
		// WP_Error für fehlgeschlagene Anfrage erstellen
		$fake_request = function () {
			return new \WP_Error( 'fetch_error', 'Failed to fetch remote object' );
		};

		add_filter( 'pre_http_request', $fake_request, 10 );

		$uri_transformer = new Uri( 'https://example.com/invalid' );
		$result = $uri_transformer->to_object();

		//$this->assertInstanceOf( \WP_Error::class, $result );

		remove_filter( 'pre_http_request', $fake_request, 10 );
	}
}
