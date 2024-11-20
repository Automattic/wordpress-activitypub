<?php
/**
 * Test file for Activitypub Post.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub Post.
 *
 * @coversDefaultClass \Activitypub\Transformer\Post
 */
class Test_Activitypub_Post extends WP_UnitTestCase {

	/**
	 * Test the to_array method.
	 *
	 * @covers ::to_object
	 */
	public function test_to_object() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test',
			)
		);

		$permalink = \get_permalink( $post );

		$activitypub_post = \Activitypub\Transformer\Post::transform( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		\wp_trash_post( $post );

		$activitypub_post = \Activitypub\Transformer\Post::transform( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		$cached = \get_post_meta( $post, 'activitypub_canonical_url', true );

		$this->assertEquals( $cached, $activitypub_post->get_id() );
	}

	/**
	 * Test content visibility.
	 *
	 * @covers ::to_object
	 */
	public function test_content_visibility() {
		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test content visibility',
			)
		);

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		$this->assertFalse( \Activitypub\is_post_disabled( $post_id ) );
		$object = \Activitypub\Transformer\Post::transform( get_post( $post_id ) )->to_object();
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_to() );

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );

		$this->assertFalse( \Activitypub\is_post_disabled( $post_id ) );
		$object = \Activitypub\Transformer\Post::transform( get_post( $post_id ) )->to_object();
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_cc() );

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );

		$this->assertTrue( \Activitypub\is_post_disabled( $post_id ) );
		$object = \Activitypub\Transformer\Post::transform( get_post( $post_id ) )->to_object();
		$this->assertEquals( array(), $object->get_to() );
		$this->assertEquals( array(), $object->get_cc() );
	}

	/**
	 * Test different variations of Attachment parsing.
	 */
	public function test_block_attachments_with_fallback() {
		$attachment_id  = $this->create_upload_object( __DIR__ . '/assets/test.jpg' );
		$attachment_src = \wp_get_attachment_image_src( $attachment_id );

		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => sprintf(
					'<!-- wp:image {"id": %1$d,"sizeSlug":"large"} --><figure class="wp-block-image"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure><!-- /wp:image -->',
					$attachment_id,
					$attachment_src[0]
				),
				'post_status'  => 'publish',
			)
		);

		$object = \Activitypub\Transformer\Post::transform( get_post( $post_id ) )->to_object();

		$this->assertEquals(
			array(
				array(
					'type'      => 'Image',
					'url'       => $attachment_src[0],
					'mediaType' => 'image/jpeg',
				),
			),
			$object->get_attachment()
		);

		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => sprintf(
					'<p>this is a photo</p><p><img src="%2$s" alt="" class="wp-image-%1$d"/></p>',
					$attachment_id,
					$attachment_src[0]
				),
				'post_status'  => 'publish',
			)
		);

		$object = \Activitypub\Transformer\Post::transform( get_post( $post_id ) )->to_object();

		$this->assertEquals(
			array(
				array(
					'type'      => 'Image',
					'url'       => $attachment_src[0],
					'mediaType' => 'image/jpeg',
				),
			),
			$object->get_attachment()
		);

		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Saves an attachment.
	 *
	 * @param string $file      The file name to create attachment object for.
	 * @param int    $parent_id ID of the post to attach the file to.
	 *
	 * @return int|WP_Error The attachment ID on success. The value 0 or WP_Error on failure.
	 */
	public function create_upload_object( $file, $parent_id = 0 ) {
		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$dest = dirname( $file ) . DIRECTORY_SEPARATOR . 'test-temp.jpg';
		$fs   = new \WP_Filesystem_Direct( array() );
		$fs->copy( $file, $dest );

		$file = $dest;

		$file_array = array(
			'name'     => wp_basename( $file ),
			'tmp_name' => $file,
		);

		$upload = wp_handle_sideload( $file_array, array( 'test_form' => false ) );

		$type = '';
		if ( ! empty( $upload['type'] ) ) {
			$type = $upload['type'];
		} else {
			$mime = wp_check_filetype( $upload['file'] );
			if ( $mime ) {
				$type = $mime['type'];
			}
		}

		$attachment = array(
			'post_title'     => wp_basename( $upload['file'] ),
			'post_content'   => '',
			'post_type'      => 'attachment',
			'post_parent'    => $parent_id,
			'post_mime_type' => $type,
			'guid'           => $upload['url'],
		);

		// Save the data.
		$id = wp_insert_attachment( $attachment, $upload['file'], $parent_id );
		// phpcs:ignore
		@wp_update_attachment_metadata( $id, @wp_generate_attachment_metadata( $id, $upload['file'] ) );

		return $id;
	}
}
