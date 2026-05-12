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

		\update_option( 'activitypub_api', true );
		\do_action( 'rest_api_init' );
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

	/**
	 * Build a 1x1 PNG and write it to a temp file. Returns the path.
	 *
	 * @return string Path to the temp PNG.
	 */
	private function create_png_temp_file() {
		// 1x1 transparent PNG.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Test data, not obfuscation.
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
		$tmp = \wp_tempnam( 'media-upload-test.png' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
		\file_put_contents( $tmp, $png );
		return $tmp;
	}

	/**
	 * Test POST /actors/{id}/uploadMedia with file + object parts.
	 *
	 * @covers ::upload_item
	 */
	public function test_upload_item_creates_attachment_and_returns_location() {
		$tmp = $this->create_png_temp_file();
		\wp_set_current_user( self::$user_id );
		$request = new \WP_REST_Request( 'POST', sprintf( '/%s/actors/%d/uploadMedia', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );

		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'pixel.png',
					'type'     => 'image/png',
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => \filesize( $tmp ),
				),
			)
		);
		$request->set_body_params(
			array(
				'object' => \wp_json_encode(
					array(
						'type' => 'Image',
						'name' => 'Test pixel',
					)
				),
			)
		);

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status(), \wp_json_encode( $response->get_data() ) );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );
		$this->assertStringContainsString( '/activitypub/1.0/media/', $headers['Location'] );

		$data = $response->get_data();
		$this->assertEquals( 'Image', $data['type'] );
		$this->assertEquals( 'image/png', $data['mediaType'] );
		$this->assertEquals( 'Test pixel', $data['name'] );
		$this->assertSame( $headers['Location'], $data['id'] );

		// Parse attachment id out of the Location URL and verify the DB state.
		\preg_match( '#/media/(\d+)#', $headers['Location'], $matches );
		$attachment_id = isset( $matches[1] ) ? (int) $matches[1] : 0;
		$this->assertGreaterThan( 0, $attachment_id );
		$this->assertEquals( 'attachment', \get_post_type( $attachment_id ) );
		$this->assertEquals( 'Test pixel', \get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Test POST /uploadMedia with no file returns 400.
	 *
	 * @covers ::upload_item
	 */
	public function test_upload_item_missing_file() {
		\wp_set_current_user( self::$user_id );
		$request  = new \WP_REST_Request( 'POST', sprintf( '/%s/actors/%d/uploadMedia', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'activitypub_missing_file', $response->get_data()['code'] );
	}

	/**
	 * Test POST /uploadMedia Pleroma-style: file only, with `description` form field.
	 *
	 * @covers ::upload_item
	 */
	public function test_upload_item_pleroma_style() {
		$tmp = $this->create_png_temp_file();
		\wp_set_current_user( self::$user_id );

		$request = new \WP_REST_Request( 'POST', sprintf( '/%s/actors/%d/uploadMedia', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'pixel.png',
					'type'     => 'image/png',
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => \filesize( $tmp ),
				),
			)
		);
		$request->set_body_params( array( 'description' => 'Pleroma alt text' ) );

		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 'Pleroma alt text', $response->get_data()['name'] );
	}

	/**
	 * Test that explicit object.name beats a competing description form field.
	 *
	 * @covers ::upload_item
	 */
	public function test_upload_item_object_name_beats_description() {
		$tmp = $this->create_png_temp_file();
		\wp_set_current_user( self::$user_id );

		$request = new \WP_REST_Request( 'POST', sprintf( '/%s/actors/%d/uploadMedia', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'pixel.png',
					'type'     => 'image/png',
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => \filesize( $tmp ),
				),
			)
		);
		$request->set_body_params(
			array(
				'object'      => \wp_json_encode(
					array(
						'type' => 'Image',
						'name' => 'From object.name',
					)
				),
				'description' => 'From description',
			)
		);

		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 'From object.name', $response->get_data()['name'] );
	}

	/**
	 * Test POST /uploadMedia with malformed object JSON returns 400.
	 *
	 * @covers ::upload_item
	 */
	public function test_upload_item_malformed_object_json() {
		$tmp = $this->create_png_temp_file();
		\wp_set_current_user( self::$user_id );
		$request = new \WP_REST_Request( 'POST', sprintf( '/%s/actors/%d/uploadMedia', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'pixel.png',
					'type'     => 'image/png',
					'tmp_name' => $tmp,
					'error'    => 0,
					'size'     => \filesize( $tmp ),
				),
			)
		);
		$request->set_body_params( array( 'object' => '{not json' ) );

		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'activitypub_invalid_object', $response->get_data()['code'] );
	}

	/**
	 * Test that the User actor advertises uploadMedia in endpoints.
	 */
	public function test_user_actor_advertises_upload_media_endpoint() {
		$user      = \Activitypub\Collection\Actors::get_by_id( self::$user_id );
		$endpoints = $user->get_endpoints();

		$this->assertArrayHasKey( 'uploadMedia', $endpoints );
		$this->assertStringContainsString(
			sprintf( '/activitypub/1.0/actors/%d/uploadMedia', self::$user_id ),
			$endpoints['uploadMedia']
		);
	}

	/**
	 * Test that the Blog actor advertises uploadMedia in endpoints.
	 */
	public function test_blog_actor_advertises_upload_media_endpoint() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		$blog = \Activitypub\Collection\Actors::get_by_id( \Activitypub\Collection\Actors::BLOG_USER_ID );
		\delete_option( 'activitypub_actor_mode' );
		$endpoints = $blog->get_endpoints();

		$this->assertArrayHasKey( 'uploadMedia', $endpoints );
		$this->assertStringContainsString(
			'/activitypub/1.0/actors/0/uploadMedia',
			$endpoints['uploadMedia']
		);
	}

	/**
	 * Test that the route is only registered when activitypub_api is enabled.
	 */
	public function test_route_gated_behind_activitypub_api_option() {
		global $wp_rest_server;

		$previous = \get_option( 'activitypub_api', false );
		\update_option( 'activitypub_api', false );

		// Reset the server so previously-registered routes are cleared.
		$wp_rest_server = new \WP_REST_Server();
		\do_action( 'rest_api_init' );

		$routes = \rest_get_server()->get_routes();
		$this->assertArrayNotHasKey(
			'/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)/(?P<user_id>[-]?\d+)/uploadMedia',
			$routes
		);

		// Restore state.
		\update_option( 'activitypub_api', $previous );
		$wp_rest_server = new \WP_REST_Server();
		\do_action( 'rest_api_init' );
	}
}
