<?php
/**
 * Application REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Application;
use Activitypub\Migration;
use Activitypub\Rest\Application_Controller;

/**
 * Tests for Application REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Application_Controller
 */
class Test_Application_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/application', $routes );
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/application/outbox', $routes );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/application' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertArrayHasKey( 'schema', $response );
		$schema = $response['schema'];

		// Test specific property types.
		$this->assertEquals( 'array', $schema['properties']['@context']['type'] );
		$this->assertEquals( 'string', $schema['properties']['id']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['id']['format'] );
		$this->assertEquals( array( 'Application' ), $schema['properties']['type']['enum'] );
		$this->assertEquals( 'object', $schema['properties']['icon']['type'] );
		$this->assertEquals( 'date-time', $schema['properties']['published']['format'] );
	}

	/**
	 * Test get_item response.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/application' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/activity+json', $response->get_headers()['Content-Type'] );

		$data = $response->get_data();

		// Test required properties.
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'inbox', $data );
		$this->assertArrayHasKey( 'outbox', $data );

		// Test property values.
		$this->assertEquals( 'Application', $data['type'] );
		$this->assertStringContainsString( '/activitypub/1.0/application', $data['id'] );
		$this->assertStringContainsString( '/activitypub/1.0/inbox', $data['inbox'] );
		$this->assertStringContainsString( '/activitypub/1.0/application/outbox', $data['outbox'] );

		// Test that Application is not discoverable.
		$this->assertFalse( $data['discoverable'] );
		$this->assertFalse( $data['indexable'] );
		$this->assertTrue( $data['invisible'] );
		$this->assertTrue( $data['manuallyApprovesFollowers'] );
	}

	/**
	 * Test that the Application response matches its schema.
	 *
	 * @covers ::get_item
	 * @covers ::get_item_schema
	 */
	public function test_response_matches_schema() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/application' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new Application_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}

	/**
	 * Test get_outbox response.
	 *
	 * @covers ::get_outbox
	 */
	public function test_get_outbox() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/application/outbox' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/activity+json', $response->get_headers()['Content-Type'] );

		$data = $response->get_data();
		$this->assertEquals( 'OrderedCollection', $data['type'] );
		$this->assertEquals( 0, $data['totalItems'] );
		$this->assertIsArray( $data['orderedItems'] );
		$this->assertEmpty( $data['orderedItems'] );
		$this->assertStringContainsString( '/activitypub/1.0/application/outbox', $data['id'] );
	}

	/**
	 * Test key management methods.
	 *
	 * @covers \Activitypub\Application::get_key_id
	 * @covers \Activitypub\Application::get_public_key
	 * @covers \Activitypub\Application::get_private_key
	 */
	public function test_key_management() {
		\delete_option( Application::KEYPAIR_OPTION_KEY );

		$key_id      = Application::get_key_id();
		$public_key  = Application::get_public_key();
		$private_key = Application::get_private_key();

		$this->assertStringContainsString( '#main-key', $key_id );
		$this->assertStringContainsString( '/activitypub/1.0/application', $key_id );
		$this->assertNotEmpty( $public_key );
		$this->assertNotEmpty( $private_key );

		// Keys should be consistent across calls.
		$this->assertEquals( $public_key, Application::get_public_key() );
		$this->assertEquals( $private_key, Application::get_private_key() );
	}

	/**
	 * Test that legacy key pairs are readable after migration.
	 *
	 * @covers \Activitypub\Application::get_public_key
	 * @covers \Activitypub\Application::get_private_key
	 */
	public function test_legacy_key_pair() {
		\delete_option( Application::KEYPAIR_OPTION_KEY );

		$public_key  = 'legacy-public-key';
		$private_key = 'legacy-private-key';

		\add_option( 'activitypub_application_user_public_key', $public_key );
		\add_option( 'activitypub_application_user_private_key', $private_key );

		Migration::migrate_legacy_application_keys();

		$this->assertEquals( $public_key, Application::get_public_key() );
		$this->assertEquals( $private_key, Application::get_private_key() );

		\delete_option( 'activitypub_application_user_public_key' );
		\delete_option( 'activitypub_application_user_private_key' );
		\delete_option( Application::KEYPAIR_OPTION_KEY );
	}
}
