<?php
/**
 * Comment Media Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Comment_Media;
use WP_UnitTestCase;

/**
 * Test class for Comment Media cache.
 *
 * @coversDefaultClass \Activitypub\Cache\Comment_Media
 */
class Test_Comment_Media extends WP_UnitTestCase {

	/**
	 * Set up the hooks.
	 */
	public function set_up() {
		parent::set_up();

		Comment_Media::init();
	}

	/**
	 * Create a comment with a cached file.
	 *
	 * @param int $approved Optional. The comment approval status. Default 1.
	 *
	 * @return array The comment ID and the cached file path.
	 */
	private function create_cached_comment( $approved = 1 ) {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::factory()->post->create(),
				'comment_approved' => $approved,
			)
		);
		$paths      = Comment_Media::get_storage_paths( $comment_id );
		\wp_mkdir_p( $paths['basedir'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $paths['basedir'] . '/test.txt', 'test' );

		return array( $comment_id, $paths['basedir'] . '/test.txt' );
	}

	/**
	 * Only comment image URLs are cached, post media stays with Media.
	 *
	 * @covers ::maybe_cache
	 */
	public function test_maybe_cache_ignores_post_media() {
		$downloads = 0;
		$download  = static function () use ( &$downloads ) {
			++$downloads;
			return new \WP_Error( 'cache_skipped', 'Test only counts downloads.' );
		};
		\add_filter( 'activitypub_pre_download_url', $download );
		$url = Comment_Media::maybe_cache( 'https://example.com/photo.jpg', 'media', 123 );
		\remove_filter( 'activitypub_pre_download_url', $download );

		$this->assertSame( 'https://example.com/photo.jpg', $url );
		$this->assertSame( 0, $downloads );
	}

	/**
	 * Deleting a comment removes its images.
	 *
	 * @covers ::init
	 */
	public function test_delete_comment_removes_images() {
		list( $comment_id, $file ) = $this->create_cached_comment();

		\wp_delete_comment( $comment_id, true );

		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Deleting the post removes the images of its comments.
	 *
	 * @covers ::init
	 */
	public function test_delete_post_removes_comment_images() {
		list( $comment_id, $file ) = $this->create_cached_comment();

		\wp_delete_post( \get_comment( $comment_id )->comment_post_ID, true );

		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Editing a comment removes its images, they are cached again on the next render.
	 *
	 * @covers ::init
	 */
	public function test_edit_comment_removes_images() {
		list( $comment_id, $file ) = $this->create_cached_comment();

		\wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => 'Edited',
			)
		);

		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * A comment that is no longer approved loses its images.
	 *
	 * @dataProvider data_unapproved_status
	 * @covers ::maybe_cleanup_comment
	 *
	 * @param string $status The new comment status.
	 */
	public function test_unapproved_comment_removes_images( $status ) {
		list( $comment_id, $file ) = $this->create_cached_comment();

		\wp_set_comment_status( $comment_id, $status );

		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Statuses that hide a comment.
	 *
	 * @return array Test data.
	 */
	public function data_unapproved_status() {
		return array(
			'trash' => array( 'trash' ),
			'spam'  => array( 'spam' ),
			'hold'  => array( 'hold' ),
		);
	}

	/**
	 * Approving a comment keeps its images.
	 *
	 * @covers ::maybe_cleanup_comment
	 */
	public function test_approved_comment_keeps_images() {
		list( $comment_id, $file ) = $this->create_cached_comment( 0 );

		\wp_set_comment_status( $comment_id, 'approve' );

		$this->assertFileExists( $file );
		Comment_Media::invalidate_entity( $comment_id );
	}

	/**
	 * Cleanup also runs when caching is disabled, files cached before must still go.
	 *
	 * @covers ::init
	 */
	public function test_cleanup_without_caching() {
		\remove_action( 'transition_comment_status', array( Comment_Media::class, 'maybe_cleanup_comment' ) );
		\add_filter( 'activitypub_cache_media_enabled', '__return_false' );
		Comment_Media::init();
		\remove_filter( 'activitypub_cache_media_enabled', '__return_false' );
		list( $comment_id, $file ) = $this->create_cached_comment();

		\wp_delete_comment( $comment_id, true );

		$this->assertFileDoesNotExist( $file );
	}
}
