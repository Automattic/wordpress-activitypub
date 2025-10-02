<?php
/**
 * Test file for Transformer Factory.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Transformer\Activity_Object;
use Activitypub\Transformer\Attachment;
use Activitypub\Transformer\Comment;
use Activitypub\Transformer\Factory;
use Activitypub\Transformer\Json;
use Activitypub\Transformer\Post;

/**
 * Test class for Transformer Factory.
 *
 * @coversDefaultClass \Activitypub\Transformer\Factory
 */
class Test_Factory extends \WP_UnitTestCase {
	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test attachment ID.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	/**
	 * Test comment ID.
	 *
	 * @var int
	 */
	protected static $comment_id;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$post_id = $factory->post->create();

		// Create test attachment.
		self::$attachment_id = $factory->attachment->create_object(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);

		self::$user_id = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		// Create test comment.
		self::$comment_id = $factory->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'user_id'         => self::$user_id,
				'comment_meta'    => array(
					'activitypub_status' => 'pending',
				),
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
		wp_delete_post( self::$attachment_id, true );
		wp_delete_comment( self::$comment_id, true );
		wp_delete_user( self::$user_id, true );
	}

	/**
	 * Test get_transformer with invalid input.
	 *
	 * @covers ::get_transformer
	 */
	public function test_get_transformer_invalid_input() {
		$result = Factory::get_transformer( null );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_object', $result->get_error_code() );
	}

	/**
	 * Test get_transformer with post.
	 *
	 * @covers ::get_transformer
	 */
	public function test_get_transformer_post() {
		$post        = get_post( self::$post_id );
		$transformer = Factory::get_transformer( $post );

		$this->assertInstanceOf( \WP_Error::class, $transformer );

		\add_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$post        = get_post( self::$post_id );
		$transformer = Factory::get_transformer( $post );

		$this->assertInstanceOf( Post::class, $transformer );

		\add_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$post        = get_post( self::$post_id );
		$transformer = Factory::get_transformer( $post );

		$this->assertInstanceOf( Post::class, $transformer );
	}

	/**
	 * Test get_transformer with attachment.
	 *
	 * @covers ::get_transformer
	 */
	public function test_get_transformer_attachment() {
		// Allow attachment to be federated.
		\add_post_type_support( 'attachment', 'activitypub' );

		$attachment  = get_post( self::$attachment_id );
		$transformer = Factory::get_transformer( $attachment );

		$this->assertInstanceOf( Attachment::class, $transformer );

		// Remove support for attachment.
		\remove_post_type_support( 'attachment', 'activitypub' );
	}

	/**
	 * Test get_transformer with comment.
	 *
	 * @covers ::get_transformer
	 */
	public function test_get_transformer_comment() {
		$comment     = get_comment( self::$comment_id );
		$transformer = Factory::get_transformer( $comment );

		$this->assertInstanceOf( Comment::class, $transformer );
	}

	/**
	 * Test get_transformer with JSON data.
	 *
	 * @covers ::get_transformer
	 */
	public function test_get_transformer_json() {
		$json_string = '{"type": "Note", "content": "Test"}';
		$transformer = Factory::get_transformer( $json_string );

		$this->assertInstanceOf( Json::class, $transformer );

		$json_array  = array(
			'type'    => 'Note',
			'content' => 'Test',
		);
		$transformer = Factory::get_transformer( $json_array );

		$this->assertInstanceOf( Json::class, $transformer );
	}

	/**
	 * Test get_transformer with custom filter.
	 *
	 * @covers ::get_transformer
	 */
	public function test_get_transformer_filter() {
		add_filter(
			'activitypub_transformer',
			// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
			function ( $transformer, $data, $class ) {
				if ( 'WP_Post' === $class && 'post' === $data->post_type ) {
					return new Activity_Object( $data );
				}
				return $transformer;
			},
			10,
			3
		);

		$post        = get_post( self::$post_id );
		$transformer = Factory::get_transformer( $post );

		$this->assertInstanceOf( Activity_Object::class, $transformer );

		remove_all_filters( 'activitypub_transformer' );
	}

	/**
	 * Test get_transformer with invalid filter return.
	 *
	 * @covers ::get_transformer
	 */
	public function test_get_transformer_invalid_filter() {
		add_filter(
			'activitypub_transformer',
			function () {
				return 'invalid';
			}
		);

		$post   = get_post( self::$post_id );
		$result = Factory::get_transformer( $post );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_transformer', $result->get_error_code() );

		remove_all_filters( 'activitypub_transformer' );
	}

	/**
	 * Test successful URI transformation.
	 */
	public function test_successful_uri_transformation() {
		// Mock-Daten für die HTTP-Antwort.
		$fake_request = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'id'      => 'https://example.com/activity/1',
						'type'    => 'Note',
						'content' => 'Test Content',
					)
				),
			);
		};

		add_filter( 'pre_http_request', $fake_request, 10 );

		$uri_transformer = Factory::get_transformer( 'https://example.com/activity/1' );
		$result          = $uri_transformer->to_object();

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
		// WP_Error für fehlgeschlagene Anfrage erstellen.
		$fake_request = function () {
			return new \WP_Error( 'fetch_error', 'Failed to fetch remote object' );
		};

		add_filter( 'pre_http_request', $fake_request, 10 );

		$uri_transformer = Factory::get_transformer( 'https://example.com/invalid' );

		$this->assertWPError( $uri_transformer );

		remove_filter( 'pre_http_request', $fake_request, 10 );
	}
}
