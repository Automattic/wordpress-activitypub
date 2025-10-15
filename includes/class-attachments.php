<?php
/**
 * Attachments processing file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Attachments processor class.
 */
class Attachments {

	/**
	 * Process attachments from an ActivityPub object and attach them to a post.
	 *
	 * @param array $attachments Array of ActivityPub attachment objects.
	 * @param int   $post_id     The post ID to attach files to.
	 * @param int   $author_id   Optional. User ID to set as attachment author. Default 0.
	 *
	 * @return array Array of attachment IDs.
	 */
	public static function process( $attachments, $post_id, $author_id = 0 ) {
		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return array();
		}

		$attachment_ids = array();
		foreach ( $attachments as $attachment ) {
			$attachment_data = self::normalize_attachment( $attachment );

			if ( empty( $attachment_data['url'] ) ) {
				continue;
			}

			$attachment_id = self::save_attachment( $attachment_data, $post_id, $author_id );

			if ( ! \is_wp_error( $attachment_id ) ) {
				$attachment_ids[] = $attachment_id;
			}
		}

		// Append media markup to post content.
		if ( ! empty( $attachment_ids ) ) {
			self::append_media_to_content( $post_id, $attachment_ids );
		}

		return $attachment_ids;
	}

	/**
	 * Normalize an ActivityPub attachment object to a standard format.
	 *
	 * @param mixed $attachment The attachment data (array or object).
	 *
	 * @return array|false Normalized attachment data or false on failure.
	 */
	private static function normalize_attachment( $attachment ) {
		// Convert object to array if needed.
		if ( is_object( $attachment ) ) {
			$attachment = get_object_vars( $attachment );
		}

		if ( ! is_array( $attachment ) || empty( $attachment['url'] ) ) {
			return false;
		}

		return array(
			'url'       => $attachment['url'],
			'mediaType' => $attachment['mediaType'] ?? '',
			'name'      => $attachment['name'] ?? '',
			'type'      => $attachment['type'] ?? 'Document',
		);
	}

	/**
	 * Save an attachment (local file or remote URL) to the media library.
	 *
	 * @param array $attachment_data The normalized attachment data.
	 * @param int   $post_id         The post ID to attach to.
	 * @param int   $author_id       Optional. User ID to set as attachment author. Default 0.
	 *
	 * @return int|\WP_Error The attachment ID or WP_Error on failure.
	 */
	private static function save_attachment( $attachment_data, $post_id, $author_id = 0 ) {
		// Ensure required WordPress functions are loaded.
		if ( ! \function_exists( 'media_handle_sideload' ) || ! \function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$is_local = ! preg_match( '#^https?://#i', $attachment_data['url'] );

		if ( $is_local ) {
			// Read local file from disk.
			\WP_Filesystem();
			global $wp_filesystem;

			if ( ! $wp_filesystem->exists( $attachment_data['url'] ) ) {
				/* translators: %s: file path */
				return new \WP_Error( 'file_not_found', sprintf( \__( 'File not found: %s', 'activitypub' ), $attachment_data['url'] ) );
			}

			// Copy to temp file so media_handle_sideload doesn't move the original.
			$tmp_file = \wp_tempnam( \basename( $attachment_data['url'] ) );
			$wp_filesystem->copy( $attachment_data['url'], $tmp_file, true );
		} else {
			// Download remote URL.
			$tmp_file = \download_url( $attachment_data['url'] );

			if ( \is_wp_error( $tmp_file ) ) {
				return $tmp_file;
			}
		}

		// Prepare file array for WordPress.
		$file_array = array(
			'name'     => \basename( $attachment_data['url'] ),
			'tmp_name' => $tmp_file,
		);

		// Prepare attachment post data.
		$post_data = array(
			'post_mime_type' => $attachment_data['mediaType'] ?? '',
			'post_title'     => $attachment_data['name'] ?? '',
			'post_content'   => $attachment_data['name'] ?? '',
			'post_status'    => 'inherit',
			'post_author'    => $author_id,
			'meta_input'     => array(
				'_activitypub_source_url' => $attachment_data['url'],
			),
		);

		// Add alt text for images.
		if ( ! empty( $attachment_data['name'] ) ) {
			$mime_type = $attachment_data['mediaType'] ?? '';
			if ( 'image' === strtok( $mime_type, '/' ) ) {
				$post_data['meta_input']['_wp_attachment_image_alt'] = $attachment_data['name'];
			}
		}

		// Sideload the attachment into WordPress.
		$attachment_id = \media_handle_sideload( $file_array, $post_id, '', $post_data );

		// Clean up temp file if there was an error.
		if ( \is_wp_error( $attachment_id ) ) {
			\wp_delete_file( $tmp_file );
		}

		return $attachment_id;
	}

	/**
	 * Append media to post content.
	 *
	 * @param int   $post_id        The post ID.
	 * @param array $attachment_ids Array of attachment IDs.
	 */
	private static function append_media_to_content( $post_id, $attachment_ids ) {
		$post = \get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$media     = self::generate_media_markup( $attachment_ids );
		$separator = "\n\n";

		// Don't add separator if content is empty.
		if ( empty( trim( $post->post_content ) ) ) {
			$separator = '';
		}

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $post->post_content . $separator . $media,
			)
		);
	}

	/**
	 * Generate media markup for attachments.
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 *
	 * @return string The generated markup.
	 */
	private static function generate_media_markup( $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return '';
		}

		/**
		 * Filters the media markup for ActivityPub attachments.
		 *
		 * Allows plugins to provide custom markup for attachments.
		 * If this filter returns a non-empty string, it will be used instead of
		 * the default block markup.
		 *
		 * @param string $markup         The custom markup. Default empty string.
		 * @param array  $attachment_ids Array of attachment IDs.
		 */
		$custom_markup = \apply_filters( 'activitypub_attachments_media_markup', '', $attachment_ids );

		if ( ! empty( $custom_markup ) ) {
			return $custom_markup;
		}

		// Default to block markup.
		$type = strtok( \get_post_mime_type( $attachment_ids[0] ), '/' );

		// Single video or audio file.
		if ( 1 === \count( $attachment_ids ) && ( 'video' === $type || 'audio' === $type ) ) {
			return sprintf(
				'<!-- wp:%1$s {"id":"%2$s"} --><figure class="wp-block-%1$s"><%1$s controls src="%3$s"></%1$s></figure><!-- /wp:%1$s -->',
				\esc_attr( $type ),
				\esc_attr( $attachment_ids[0] ),
				\esc_url( \wp_get_attachment_url( $attachment_ids[0] ) )
			);
		}

		// Multiple attachments or images: use gallery block.
		return self::get_gallery_block( $attachment_ids );
	}

	/**
	 * Get gallery block markup.
	 *
	 * @param array $attachment_ids The attachment IDs to use.
	 *
	 * @return string The gallery block markup.
	 */
	private static function get_gallery_block( $attachment_ids ) {
		$gallery  = '<!-- wp:gallery {"ids":[' . \implode( ',', $attachment_ids ) . '],"linkTo":"none"} -->' . "\n";
		$gallery .= '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">';

		foreach ( $attachment_ids as $id ) {
			$image_src = \wp_get_attachment_image_src( $id, 'large' );
			if ( ! $image_src ) {
				continue;
			}

			$caption  = \get_post_field( 'post_content', $id );
			$gallery .= "\n<!-- wp:image {\"id\":{$id},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n";
			$gallery .= '<figure class="wp-block-image size-large">';
			$gallery .= '<img src="' . \esc_url( $image_src[0] ) . '" alt="' . \esc_attr( $caption ) . '" class="' . \esc_attr( 'wp-image-' . $id ) . '"/>';
			$gallery .= '</figure>';
			$gallery .= "\n<!-- /wp:image -->\n";
		}

		$gallery .= "</figure>\n";
		$gallery .= '<!-- /wp:gallery -->';

		return $gallery;
	}
}
