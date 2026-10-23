<?php
/**
 * Comment Media Cache Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cache;

use Activitypub\Cache\Comment_Media;
use WP_UnitTestCase;

use function Activitypub\generate_image_block;

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

		Comment_Media::register_cleanup();
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
	 * Comment images have their own cache type, so hooks can tell them from post media.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type() {
		$this->assertSame( 'comment_media', Comment_Media::get_type() );
	}

	/**
	 * Download an image for a comment, with the download stubbed.
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return string The URL the cache returns.
	 */
	private function cache_image( $comment_id ) {
		$download = static function () {
			$file = \wp_tempnam( 'comment-image.jpg' );
			\copy( AP_TESTS_DIR . '/data/assets/test.jpg', $file );
			return array(
				'file'      => $file,
				'mime_type' => 'image/jpeg',
			);
		};

		// Comment IDs repeat across tests, start without a cached file.
		Comment_Media::invalidate_entity( $comment_id );
		\add_filter( 'activitypub_pre_download_url', $download );
		$url = Comment_Media::maybe_cache( 'https://example.com/photo.jpg', 'comment_media', $comment_id );
		\remove_filter( 'activitypub_pre_download_url', $download );

		return $url;
	}

	/**
	 * An image downloaded while its comment was removed is not kept on the server.
	 *
	 * @covers ::cache
	 */
	public function test_maybe_cache_removes_images_of_removed_comment() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 'trash',
				'comment_content'  => generate_image_block( 'https://example.com/photo.jpg', '' ),
			)
		);

		$this->assertSame( 'https://example.com/photo.jpg', $this->cache_image( $comment_id ) );
		$this->assertDirectoryDoesNotExist( Comment_Media::get_storage_paths( $comment_id )['basedir'] );
	}

	/**
	 * An approved comment keeps the image it shows.
	 *
	 * @covers ::cache
	 */
	public function test_maybe_cache_keeps_image_of_approved_comment() {
		$comment_id = self::factory()->comment->create(
			array( 'comment_content' => generate_image_block( 'https://example.com/photo.jpg', '' ) )
		);

		$this->assertNotSame( 'https://example.com/photo.jpg', $this->cache_image( $comment_id ) );
		$this->assertDirectoryExists( Comment_Media::get_storage_paths( $comment_id )['basedir'] );
		Comment_Media::invalidate_entity( $comment_id );
	}

	/**
	 * An image an edit removed while it downloaded is not kept, also when the request holds the old comment.
	 *
	 * @covers ::cache
	 */
	public function test_maybe_cache_removes_image_dropped_by_edit() {
		global $wpdb;

		$comment_id = self::factory()->comment->create(
			array( 'comment_content' => generate_image_block( 'https://example.com/photo.jpg', '' ) )
		);
		\get_comment( $comment_id );
		// Another request edits the comment, this request still has the old one cached.
		$wpdb->update( $wpdb->comments, array( 'comment_content' => 'Edited' ), array( 'comment_ID' => $comment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->assertSame( 'https://example.com/photo.jpg', $this->cache_image( $comment_id ) );
		$this->assertDirectoryDoesNotExist( Comment_Media::get_storage_paths( $comment_id )['basedir'] );
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
	 * @covers ::register_cleanup
	 */
	public function test_delete_comment_removes_images() {
		list( $comment_id, $file ) = $this->create_cached_comment();

		\wp_delete_comment( $comment_id, true );

		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Deleting the post removes the images of its comments.
	 *
	 * @covers ::register_cleanup
	 */
	public function test_delete_post_removes_comment_images() {
		list( $comment_id, $file ) = $this->create_cached_comment();

		\wp_delete_post( \get_comment( $comment_id )->comment_post_ID, true );

		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Editing a comment removes its images, they are cached again on the next render.
	 *
	 * @covers ::register_cleanup
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
	 * Turning comment images off removes all cached comment images, turning them on keeps them.
	 *
	 * @covers ::maybe_clear_all_images
	 */
	public function test_turning_option_off_removes_all_images() {
		\update_option( 'activitypub_allow_comment_images', '1' );
		list( , $file ) = $this->create_cached_comment();

		// A change that keeps the option on keeps the images.
		\update_option( 'activitypub_allow_comment_images', 'on' );
		$this->assertFileExists( $file );

		\update_option( 'activitypub_allow_comment_images', '0' );
		$this->assertDirectoryDoesNotExist( \wp_upload_dir()['basedir'] . Comment_Media::BASE_DIR );
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
}
