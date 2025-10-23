<?php
/**
 * Attachments processing file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Posts;

/**
 * Attachments processor class.
 */
class Attachments {

	/**
	 * Initialize the class and set up filters.
	 */
	public static function init() {
		\add_action( 'pre_get_posts', array( self::class, 'maybe_hide_from_media_library' ), 999 );
		\add_action( 'before_delete_post', array( self::class, 'delete_attachments_with_post' ) );
	}

	/**
	 * Delete attachments when an ap_post is deleted.
	 *
	 * @param int $post_id The post ID being deleted.
	 */
	public static function delete_attachments_with_post( $post_id ) {
		if ( Posts::POST_TYPE !== \get_post_type( $post_id ) ) {
			return;
		}

		foreach ( \get_attached_media( '', $post_id ) as $attachment ) {
			\wp_delete_attachment( $attachment->ID, true );
		}
	}

	/**
	 * Hide ActivityPub attachments from Media Library queries.
	 *
	 * This works for both the list view and the media modal by checking
	 * if we're querying attachments without explicitly requesting the
	 * _activitypub_import meta key.
	 *
	 * @param \WP_Query $query The WordPress query object.
	 */
	public static function maybe_hide_from_media_library( $query ) {
		// Only filter attachment queries.
		if ( 'attachment' !== $query->get( 'post_type' ) ) {
			return;
		}

		// Don't filter if we're querying attachments for an ap_post.
		if ( $query->get( 'post_parent' ) ) {
			$parent_post = \get_post( $query->get( 'post_parent' ) );
			if ( $parent_post && Posts::POST_TYPE === $parent_post->post_type ) {
				return;
			}
		}

		// Check if the query is already explicitly looking for _activitypub_import.
		$meta_query = $query->get( 'meta_query' ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary

		$has_activitypub_query = false;
		foreach ( $meta_query as $clause ) {
			if ( isset( $clause['key'] ) && '_activitypub_import' === $clause['key'] ) {
				$has_activitypub_query = true;
				break;
			}
		}

		// If not explicitly querying for this meta, exclude ActivityPub imports.
		if ( ! $has_activitypub_query ) {
			$meta_query[] = array(
				'key'     => '_activitypub_import',
				'compare' => 'NOT EXISTS',
			);
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Import attachments from an ActivityPub object and attach them to a post.
	 *
	 * @param array $attachments Array of ActivityPub attachment objects.
	 * @param int   $post_id     The post ID to attach files to.
	 * @param int   $author_id   Optional. User ID to set as attachment author. Default 0.
	 *
	 * @return array Array of attachment IDs.
	 */
	public static function import( $attachments, $post_id, $author_id = 0 ) {
		// First, import inline images from the post content.
		$inline_mappings = self::import_inline_images( $post_id, $author_id );

		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return array();
		}

		$attachment_ids = array();
		foreach ( $attachments as $attachment ) {
			$attachment_data = self::normalize_attachment( $attachment );

			if ( empty( $attachment_data['url'] ) ) {
				continue;
			}

			// Skip if this URL was already processed as an inline image.
			if ( isset( $inline_mappings[ $attachment_data['url'] ] ) ) {
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
	 * Check if an attachment with the same source URL already exists for a post.
	 *
	 * @param string $source_url The source URL to check.
	 * @param int    $post_id    The post ID to check attachments for.
	 *
	 * @return int|false The existing attachment ID or false if not found.
	 */
	private static function get_existing_attachment( $source_url, $post_id ) {
		foreach ( \get_attached_media( '', $post_id ) as $attachment ) {
			if ( \get_post_meta( $attachment->ID, '_source_url', true ) === $source_url ) {
				return $attachment->ID;
			}
		}

		return false;
	}

	/**
	 * Process inline images from post content.
	 *
	 * @param int $post_id    The post ID.
	 * @param int $author_id  Optional. User ID to set as attachment author. Default 0.
	 *
	 * @return array Array of URL mappings (old URL => new URL).
	 */
	private static function import_inline_images( $post_id, $author_id = 0 ) {
		$post = \get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return array();
		}

		// Find all img tags in the content.
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches );

		if ( empty( $matches[1] ) ) {
			return array();
		}

		$url_mappings = array();
		$content      = $post->post_content;

		foreach ( $matches[1] as $image_url ) {
			// Skip if already processed or is a local URL.
			if ( isset( $url_mappings[ $image_url ] ) ) {
				continue;
			}

			// Check if this image was already processed as an attachment.
			$attachment_id = self::get_existing_attachment( $image_url, $post_id );
			if ( ! $attachment_id ) {
				$attachment_id = self::save_attachment( array( 'url' => $image_url ), $post_id, $author_id );

				if ( \is_wp_error( $attachment_id ) ) {
					continue;
				}
			}

			$new_url = \wp_get_attachment_url( $attachment_id );
			if ( $new_url ) {
				$url_mappings[ $image_url ] = $new_url;
				$content                    = \str_replace( $image_url, $new_url, $content );
			}
		}

		// Update post content if URLs were replaced.
		if ( ! empty( $url_mappings ) ) {
			\wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				)
			);
		}

		return $url_mappings;
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
		if ( \is_object( $attachment ) ) {
			$attachment = \get_object_vars( $attachment );
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
			'post_author'    => $author_id,
			'meta_input'     => array(
				'_source_url' => $attachment_data['url'],
			),
		);

		// Add alt text for images.
		if ( ! empty( $attachment_data['name'] ) ) {
			$mime_type = $attachment_data['mediaType'] ?? '';
			if ( 'image' === strtok( $mime_type, '/' ) ) {
				$post_data['meta_input']['_wp_attachment_image_alt'] = $attachment_data['name'];
			}
		}

		// Flag to filter out from Media Library.
		if ( Posts::POST_TYPE === \get_post_type( $post_id ) ) {
			$post_data['meta_input']['_activitypub_import'] = 'inbox';
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
