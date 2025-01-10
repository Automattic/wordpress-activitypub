<?php
/**
 * Test file for Comment transformer.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Transformer\Comment;

/**
 * Test class for Comment Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Comment
 */
class Test_Comment extends \WP_UnitTestCase {
	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$post_id = $factory->post->create();

		// Mock the WebFinger wp_safe_remote_get.
		add_filter( 'pre_http_request', array( self::class, 'pre_http_request' ), 10, 3 );
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
		remove_filter( 'pre_http_request', array( self::class, 'pre_http_request' ) );
	}

	/**
	 * Test content generation with reply context.
	 *
	 * @covers ::to_object
	 */
	public function test_content_with_reply_context() {
		// Create a parent ActivityPub comment.
		$parent_comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_author_url' => 'https://remote.example/@author',
				'comment_meta'       => array(
					'protocol' => 'activitypub',
				),
			)
		);

		// Create a reply comment.
		$reply_comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_parent'     => $parent_comment_id,
				'comment_author_url' => 'https://example.net/@remote',
				'comment_meta'       => array(
					'protocol' => 'activitypub',
				),
			)
		);

		// Create a reply comment.
		$test_comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_parent'     => $reply_comment_id,
				'comment_author_url' => 'https://example.com/@test',
			)
		);

		// Transform comment to ActivityPub object.
		$comment     = get_comment( $test_comment_id );
		$transformer = new Comment( $comment );
		$object      = $transformer->to_object();

		// Get the content.
		$content = $object->get_content();

		// Test that reply context is added.
		$this->assertSame( '<p><a rel="mention" class="u-url mention" href="https://example.net/@remote">@remote@example.net</a> <a rel="mention" class="u-url mention" href="https://remote.example/@author">@author@remote.example</a> This is a comment</p>', $content );

		// Clean up.
		wp_delete_comment( $reply_comment_id, true );
		wp_delete_comment( $parent_comment_id, true );
		wp_delete_comment( $test_comment_id, true );
	}

	/**
	 * Test content generation with reply context.
	 *
	 * @param mixed  $data        The response data.
	 * @param array  $parsed_args The request arguments.
	 * @param string $url         The request URL.
	 * @return mixed The response data.
	 */
	public static function pre_http_request( $data, $parsed_args, $url ) {
		if ( str_starts_with( $url, 'https://remote.example' ) ) {
			return self::dummy_response(
				wp_json_encode(
					array(
						'subject' => 'acct:author@remote.example',
						'links'   => array(
							'self' => array( 'href' => 'https://remote.example/@author' ),
						),
					)
				)
			);
		}

		if ( str_starts_with( $url, 'https://example.net/' ) ) {
			return self::dummy_response(
				wp_json_encode(
					array(
						'subject' => 'https://example.net/@remote',
						'aliases' => array(
							'acct:remote@example.net',
						),
						'links'   => array(
							'self' => array( 'href' => 'https://example.net/@remote' ),
						),
					)
				)
			);
		}

		return $data;
	}

	/**
	 * Create a dummy response.
	 *
	 * @param string $body The body of the response.
	 *
	 * @return array The dummy response.
	 */
	private static function dummy_response( $body ) {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array( 'code' => 200 ),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
