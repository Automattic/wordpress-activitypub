<?php
/**
 * Test file for Comment transformer.
 *
 * @package Activitypub
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
		$this->assertSame( '<p><a rel="mention" class="u-url mention" href="https://example.net/@remote" title="@remote@example.net">@remote</a> <a rel="mention" class="u-url mention" href="https://remote.example/@author" title="@author@remote.example">@author</a> This is a comment</p>', $content );

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
	 * Test Comment image attachment extraction.
	 *
	 * @covers ::get_attachment
	 */
	public function test_comment_image_attachments() {
		// Create a test image attachment.
		$attachment_id = self::factory()->attachment->create_upload_object(
			AP_TESTS_DIR . '/data/assets/test.jpg',
			self::$post_id
		);

		$attachment_url = \wp_get_attachment_url( $attachment_id );

		// Create a comment with HTML image tag.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_content' => sprintf(
					'This is a test comment with an image: <img src="%s" alt="Test image" />',
					$attachment_url
				),
			)
		);

		$comment = \get_comment( $comment_id );

		// Test the transformer.
		$transformer        = new Comment( $comment );
		$activitypub_object = $transformer->to_object();

		// Check if attachments are present.
		$attachments = $activitypub_object->get_attachment();

		$this->assertIsArray( $attachments, 'Attachments should be an array' );
		$this->assertNotEmpty( $attachments, 'Comment should have attachments when HTML images are present' );
		$this->assertCount( 1, $attachments, 'Comment should have exactly one attachment' );

		// Verify the attachment structure.
		$attachment = $attachments[0];
		$this->assertEquals( 'Image', $attachment['type'] );
		$this->assertNotEmpty( $attachment['url'] );
		$this->assertEquals( 'Test image', $attachment['name'] );

		// Clean up.
		\wp_delete_comment( $comment_id );
		\wp_delete_attachment( $attachment_id );
	}

	/**
	 * Test Comment without images.
	 *
	 * @covers ::get_attachment
	 */
	public function test_comment_without_images() {
		// Create a comment without images.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_content' => 'This is a test comment without any images.',
			)
		);

		$comment = \get_comment( $comment_id );

		// Test the transformer.
		$transformer        = new Comment( $comment );
		$activitypub_object = $transformer->to_object();

		// Check if attachments are empty.
		$attachments = $activitypub_object->get_attachment();

		$this->assertIsArray( $attachments, 'Attachments should be an array' );
		$this->assertEmpty( $attachments, 'Comment should have no attachments when no images are present' );

		// Clean up.
		\wp_delete_comment( $comment_id );
	}

	/**
	 * Test Comment with external images (should be ignored).
	 *
	 * @covers ::get_attachment
	 */
	public function test_comment_external_images() {
		// Create a comment with external image (should be ignored).
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_content' => 'This has an external image: <img src="https://external-site.com/image.jpg" alt="External image" />',
			)
		);

		$comment = \get_comment( $comment_id );

		// Test the transformer.
		$transformer        = new Comment( $comment );
		$activitypub_object = $transformer->to_object();

		// Check if attachments are empty (external images should be ignored).
		$attachments = $activitypub_object->get_attachment();

		$this->assertIsArray( $attachments, 'Attachments should be an array' );
		$this->assertEmpty( $attachments, 'Comment should ignore external images' );

		// Clean up.
		\wp_delete_comment( $comment_id );
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
