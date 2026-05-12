<?php
/**
 * Media REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Media_Controller;
use Activitypub\Tests\Test_REST_Controller_Testcase;

/**
 * Tests for Media REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Media_Controller
 */
class Test_Media_Controller extends Test_REST_Controller_Testcase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	public static $user_id;

	/**
	 * Set up class test fixtures.
	 */
	public static function set_up_before_class() {
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'ID', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );
		( new Media_Controller() )->register_routes();
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		\remove_filter( 'activitypub_oauth_check_permission', '__return_true' );
		parent::tear_down();
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = \rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)/(?P<user_id>[-]?\d+)/uploadMedia', $routes );
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/media/(?P<attachment_id>\d+)', $routes );
	}

	/**
	 * Test GET /media/{id} returns the AP representation of an image.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$attachment_id = self::factory()->attachment->create_object(
			'image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		\update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'A red square' );

		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/media/%d', ACTIVITYPUB_REST_NAMESPACE, $attachment_id ) );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'Image', $data['type'] );
		$this->assertEquals( 'image/jpeg', $data['mediaType'] );
		$this->assertEquals( 'A red square', $data['name'] );
		$this->assertStringContainsString( '/activitypub/1.0/media/' . $attachment_id, $data['id'] );
	}

	/**
	 * Test that the controller does not expose a JSON schema.
	 *
	 * Media objects are ActivityPub objects, not WP REST schema-typed resources.
	 * The schema endpoint is therefore intentionally absent.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$controller = new Media_Controller();
		$this->assertEmpty( $controller->get_item_schema() );
	}

	/**
	 * Test GET /media/{id} for a non-attachment post returns 404.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_404_for_non_attachment() {
		$post_id  = self::factory()->post->create();
		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/media/%d', ACTIVITYPUB_REST_NAMESPACE, $post_id ) );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test GET /media/{id} returns the AP representation of an audio file.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_returns_audio() {
		$attachment_id = self::factory()->attachment->create_object(
			'song.mp3',
			0,
			array(
				'post_mime_type' => 'audio/mpeg',
				'post_type'      => 'attachment',
			)
		);

		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/media/%d', ACTIVITYPUB_REST_NAMESPACE, $attachment_id ) );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Audio', $data['type'] );
		$this->assertEquals( 'audio/mpeg', $data['mediaType'] );
	}

	/**
	 * Test GET /media/{id} returns the AP representation of a video file.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_returns_video() {
		$attachment_id = self::factory()->attachment->create_object(
			'clip.mp4',
			0,
			array(
				'post_mime_type' => 'video/mp4',
				'post_type'      => 'attachment',
			)
		);

		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/media/%d', ACTIVITYPUB_REST_NAMESPACE, $attachment_id ) );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Video', $data['type'] );
		$this->assertEquals( 'video/mp4', $data['mediaType'] );
	}

	/**
	 * Test GET /media/{id} returns 415 for an unsupported MIME type.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_415_for_unsupported_mime() {
		$attachment_id = self::factory()->attachment->create_object(
			'document.pdf',
			0,
			array(
				'post_mime_type' => 'application/pdf',
				'post_type'      => 'attachment',
			)
		);

		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/media/%d', ACTIVITYPUB_REST_NAMESPACE, $attachment_id ) );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 415, $response->get_status() );
	}
}
