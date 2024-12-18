<?php
/**
 * Test file for Comment transformer.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Transformer\Comment;
use WP_UnitTestCase;

/**
 * Test class for Comment Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Comment
 */
class Test_Comment extends WP_UnitTestCase {
	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);

		// Mock the WebFinger wp_safe_remote_get.
		add_filter(
			'pre_http_request',
			function ( $data, $parsed_args, $url ) {
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
			},
			10,
			3
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
	}

	/**
	 * Test content generation with reply context.
	 *
	 * @covers ::to_object
	 */
	public function test_content_with_reply_context() {
		// Create a parent ActivityPub comment.
		$parent_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_content'      => 'Parent comment',
				'comment_type'         => 'comment',
				'comment_author'       => 'Remote Author',
				'comment_author_url'   => 'https://remote.example/@author',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		// Create a reply comment.
		$reply_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_parent'       => $parent_comment_id,
				'comment_content'      => 'Reply comment',
				'comment_type'         => 'comment',
				'comment_author'       => 'Local Author',
				'comment_author_url'   => 'https://example.net/@remote',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		// Create a reply comment.
		$test_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_parent'       => $reply_comment_id,
				'comment_content'      => 'Reply comment',
				'comment_type'         => 'comment',
				'comment_author'       => 'Local Author',
				'comment_author_url'   => 'https://example.com/@test',
				'comment_author_email' => '',
			)
		);

		// Transform comment to ActivityPub object.
		$comment     = get_comment( $test_comment_id );
		$transformer = new Comment( $comment );
		$object      = $transformer->to_object();

		// Get the content.
		$content = $object->get_content();

		// Test that reply context is added.
		$this->assertEquals( '<p><a class="u-mention mention" href="https://example.net/@remote">@remote@example.net</a> <a class="u-mention mention" href="https://remote.example/@author">@author@remote.example</a></p><p>Reply comment</p>', $content );

		// Clean up.
		wp_delete_comment( $reply_comment_id, true );
		wp_delete_comment( $parent_comment_id, true );
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
