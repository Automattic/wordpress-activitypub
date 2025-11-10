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
	 * Directory for storing ap_post media files.
	 *
	 * @var string
	 */
	public static $ap_posts_dir = '/activitypub/ap_posts/';

	/**
	 * Directory for storing comment media files.
	 *
	 * @var string
	 */
	public static $comments_dir = '/activitypub/comments/';

	/**
	 * Initialize the class and set up filters.
	 */
	public static function init() {
		\add_action( 'before_delete_post', array( self::class, 'delete_ap_posts_directory' ) );
	}

	/**
	 * Delete the activitypub files directory for a post.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function delete_ap_posts_directory( $post_id ) {
		if ( Posts::POST_TYPE !== \get_post_type( $post_id ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		\WP_Filesystem();
		global $wp_filesystem;

		$activitypub_dir = self::get_storage_paths( $post_id, 'post' )['basedir'];

		if ( $wp_filesystem->is_dir( $activitypub_dir ) ) {
			$wp_filesystem->delete( $activitypub_dir, true );
		}
	}

	/**
	 * Import attachments from an ActivityPub object and attach them to a post.
	 *
	 * Creates full WordPress attachment posts in the media library. Each attachment
	 * becomes a searchable, manageable attachment post that appears in the WordPress
	 * Media Library and is part of the user's content.
	 *
	 * Use this when:
	 * - Importing content that will be owned and editable by the user.
	 * - You need WordPress attachment posts with full metadata support.
	 * - Media should be searchable and manageable in the Media Library.
	 * - Working with content that will be part of the user's site (e.g., importers).
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
			self::append_media_to_post_content( $post_id, $attachment_ids );
		}

		return $attachment_ids;
	}

	/**
	 * Import attachments as direct files for posts.
	 *
	 * Saves files directly to uploads/activitypub/ap_posts/{post_id}/ without creating
	 * WordPress attachment posts. This lightweight approach is ideal for federated content
	 * that doesn't require full WordPress media management.
	 *
	 * Files are stored in a dedicated directory structure and automatically cleaned up
	 * when the parent post is deleted. Media URLs point directly to the stored files
	 * rather than going through WordPress attachment APIs.
	 *
	 * Use this when:
	 * - Processing ActivityPub Create/Update activities from the inbox.
	 * - Handling federated content that won't be owned or edited by the user.
	 * - You want lightweight storage without Media Library overhead.
	 *
	 * @param array $attachments Array of ActivityPub attachment objects.
	 * @param int   $post_id     The post ID to attach files to.
	 *
	 * @return array[] Array of file data arrays.
	 */
	public static function import_post_files( $attachments, $post_id ) {
		return self::import_files_for_object( $attachments, $post_id, 'post' );
	}

	/**
	 * Import attachments as direct files for any object type.
	 *
	 * Saves files directly to uploads/activitypub/{type}/{id}/ without creating
	 * WordPress attachment posts. This is the internal method that handles
	 * the actual import logic for both posts and comments.
	 *
	 * @param array  $attachments Array of ActivityPub attachment objects.
	 * @param int    $object_id   The object ID (post or comment).
	 * @param string $object_type The object type ('post' or 'comment').
	 *
	 * @return array[] Array of file data arrays.
	 */
	private static function import_files_for_object( $attachments, $object_id, $object_type ) {
		// First, import inline images from the content.
		$inline_mappings = self::import_inline_files( $object_id, $object_type );

		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return array();
		}

		$files = array();
		foreach ( $attachments as $attachment ) {
			$attachment_data = self::normalize_attachment( $attachment );

			if ( empty( $attachment_data['url'] ) ) {
				continue;
			}

			// Skip if this URL was already processed as an inline image.
			if ( isset( $inline_mappings[ $attachment_data['url'] ] ) ) {
				continue;
			}

			$file_data = self::save_file( $attachment_data, $object_id, $object_type );

			if ( ! \is_wp_error( $file_data ) ) {
				$files[] = $file_data;
			}
		}

		// Append media markup to content.
		if ( ! empty( $files ) ) {
			self::append_files_to_content( $object_id, $files, $object_type );
		}

		return $files;
	}

	/**
	 * Get storage paths for an object based on its type.
	 *
	 * @param int    $object_id   The object ID (post or comment).
	 * @param string $object_type The object type ('post' or 'comment').
	 *
	 * @return array {
	 *     Storage paths for the object.
	 *
	 *     @type string $basedir Base directory path.
	 *     @type string $baseurl Base URL.
	 * }
	 */
	private static function get_storage_paths( $object_id, $object_type ) {
		$upload_dir = \wp_upload_dir();
		$sub_dir    = 'comment' === $object_type ? self::$comments_dir : self::$ap_posts_dir;

		return array(
			'basedir' => $upload_dir['basedir'] . $sub_dir . $object_id,
			'baseurl' => $upload_dir['baseurl'] . $sub_dir . $object_id,
		);
	}

	/**
	 * Get content for an object based on its type.
	 *
	 * @param int    $object_id   The object ID (post or comment).
	 * @param string $object_type The object type ('post' or 'comment').
	 *
	 * @return string The content string or empty if not found.
	 */
	private static function get_object_content( $object_id, $object_type ) {
		if ( 'comment' === $object_type ) {
			$comment = \get_comment( $object_id );
			return $comment ? $comment->comment_content : '';
		}

		return \get_post_field( 'post_content', $object_id );
	}

	/**
	 * Update content for an object based on its type.
	 *
	 * @param int    $object_id   The object ID (post or comment).
	 * @param string $object_type The object type ('post' or 'comment').
	 * @param string $content     The new content.
	 */
	private static function update_object_content( $object_id, $object_type, $content ) {
		if ( 'comment' === $object_type ) {
			\wp_update_comment(
				array(
					'comment_ID'      => $object_id,
					'comment_content' => $content,
				)
			);
		} else {
			\wp_update_post(
				array(
					'ID'           => $object_id,
					'post_content' => $content,
				)
			);
		}
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
	 * Process inline images from content (for direct file storage).
	 *
	 * @param int    $object_id   The post or comment ID.
	 * @param string $object_type The object type ('post' or 'comment').
	 *
	 * @return array Array of URL mappings (old URL => new URL).
	 */
	private static function import_inline_files( $object_id, $object_type ) {
		$content = self::get_object_content( $object_id, $object_type );
		if ( ! $content ) {
			return array();
		}

		// Find all img tags in the content.
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

		if ( empty( $matches[1] ) ) {
			return array();
		}

		$url_mappings = array();

		foreach ( $matches[1] as $image_url ) {
			// Skip if already processed.
			if ( isset( $url_mappings[ $image_url ] ) ) {
				continue;
			}

			$file_data = self::save_file( array( 'url' => $image_url ), $object_id, $object_type );

			if ( \is_wp_error( $file_data ) ) {
				continue;
			}

			$new_url = $file_data['url'];
			if ( $new_url ) {
				$url_mappings[ $image_url ] = $new_url;
				$content                    = \str_replace( $image_url, $new_url, $content );
			}
		}

		// Update content if URLs were replaced.
		if ( ! empty( $url_mappings ) ) {
			self::update_object_content( $object_id, $object_type, $content );
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

		// Sideload the attachment into WordPress.
		$attachment_id = \media_handle_sideload( $file_array, $post_id, '', $post_data );

		// Clean up temp file if there was an error.
		if ( \is_wp_error( $attachment_id ) ) {
			\wp_delete_file( $tmp_file );
		}

		return $attachment_id;
	}

	/**
	 * Save a file directly to uploads/activitypub/{type}/{id}/.
	 *
	 * @param array  $attachment_data The normalized attachment data.
	 * @param int    $object_id       The post or comment ID to attach to.
	 * @param string $object_type     The object type ('post' or 'comment').
	 *
	 * @return array|\WP_Error {
	 *     Array of file data on success, WP_Error on failure.
	 *
	 *     @type string $url       Full URL to the saved file.
	 *     @type string $mime_type MIME type of the file.
	 *     @type string $alt       Alt text from attachment name field.
	 * }
	 */
	private static function save_file( $attachment_data, $object_id, $object_type ) {
		if ( ! \function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Download remote URL.
		$tmp_file = \download_url( $attachment_data['url'] );

		if ( \is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Get storage paths for this object.
		$paths = self::get_storage_paths( $object_id, $object_type );

		// Create directory if it doesn't exist.
		\wp_mkdir_p( $paths['basedir'] );

		// Generate unique file name.
		$file_name = \sanitize_file_name( \basename( $attachment_data['url'] ) );
		$file_path = $paths['basedir'] . '/' . $file_name;

		// Initialize filesystem if needed.
		\WP_Filesystem();
		global $wp_filesystem;

		// Make sure file name is unique.
		$counter = 1;
		while ( $wp_filesystem->exists( $file_path ) ) {
			$path_info = pathinfo( $file_name );
			$file_name = $path_info['filename'] . '-' . $counter;
			if ( ! empty( $path_info['extension'] ) ) {
				$file_name .= '.' . $path_info['extension'];
			}
			$file_path = $paths['basedir'] . '/' . $file_name;
			++$counter;
		}

		// Move file to destination.
		if ( ! $wp_filesystem->move( $tmp_file, $file_path, true ) ) {
			\wp_delete_file( $tmp_file );
			return new \WP_Error( 'file_move_failed', \__( 'Failed to move file to destination.', 'activitypub' ) );
		}

		// Get mime type and validate file.
		$file_info = \wp_check_filetype_and_ext( $file_path, $file_name );
		$mime_type = $file_info['type'] ?? $attachment_data['mediaType'] ?? '';

		return array(
			'url'       => $paths['baseurl'] . '/' . $file_name,
			'mime_type' => $mime_type,
			'alt'       => $attachment_data['name'] ?? '',
		);
	}

	/**
	 * Append media to post content.
	 *
	 * @param int   $post_id        The post ID.
	 * @param int[] $attachment_ids Array of attachment IDs.
	 */
	private static function append_media_to_post_content( $post_id, $attachment_ids ) {
		$post = \get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$media     = self::generate_media_markup( $attachment_ids );
		$separator = empty( trim( $post->post_content ) ) ? '' : "\n\n";

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $post->post_content . $separator . $media,
			)
		);
	}

	/**
	 * Append file-based media to content.
	 *
	 * @param int     $object_id   The post or comment ID.
	 * @param array[] $files       Array of file data arrays.
	 * @param string  $object_type The object type ('post' or 'comment').
	 */
	private static function append_files_to_content( $object_id, $files, $object_type ) {
		$content = self::get_object_content( $object_id, $object_type );
		if ( empty( $content ) ) {
			return;
		}

		$media     = self::generate_files_markup( $files );
		$separator = empty( trim( $content ) ) ? '' : "\n\n";

		self::update_object_content( $object_id, $object_type, $content . $separator . $media );
	}

	/**
	 * Generate media markup for attachments.
	 *
	 * @param int[] $attachment_ids Array of attachment IDs.
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
		 * @param int[]  $attachment_ids Array of attachment IDs.
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
	 * Generate media markup for file-based attachments.
	 *
	 * @param array[] $files {
	 *     Array of file data arrays.
	 *
	 *     @type string $url       Full URL to the file.
	 *     @type string $mime_type MIME type of the file.
	 *     @type string $alt       Alt text for the file.
	 * }
	 *
	 * @return string The generated markup.
	 */
	private static function generate_files_markup( $files ) {
		if ( empty( $files ) ) {
			return '';
		}

		/**
		 * Filters the media markup for ActivityPub file-based attachments.
		 *
		 * Allows plugins to provide custom markup for file-based attachments.
		 * If this filter returns a non-empty string, it will be used instead of
		 * the default block markup.
		 *
		 * @param string $markup The custom markup. Default empty string.
		 * @param array  $files  Array of file data arrays.
		 */
		$custom_markup = \apply_filters( 'activitypub_files_media_markup', '', $files );

		if ( ! empty( $custom_markup ) ) {
			return $custom_markup;
		}

		// Default to block markup.
		$type = strtok( $files[0]['mime_type'], '/' );

		// Single video or audio file.
		if ( 1 === \count( $files ) && ( 'video' === $type || 'audio' === $type ) ) {
			return sprintf(
				'<!-- wp:%1$s --><figure class="wp-block-%1$s"><%1$s controls src="%2$s"></%1$s></figure><!-- /wp:%1$s -->',
				\esc_attr( $type ),
				\esc_url( $files[0]['url'] )
			);
		}

		// Multiple attachments or images: use gallery block.
		return self::get_files_gallery_block( $files );
	}

	/**
	 * Get gallery block markup.
	 *
	 * @param int[] $attachment_ids The attachment IDs to use.
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

	/**
	 * Get gallery block markup for file-based attachments.
	 *
	 * @param array[] $files {
	 *     Array of file data arrays.
	 *
	 *     @type string $url       Full URL to the file.
	 *     @type string $mime_type MIME type of the file.
	 *     @type string $alt       Alt text for the file.
	 * }
	 *
	 * @return string The gallery block markup.
	 */
	private static function get_files_gallery_block( $files ) {
		$gallery  = '<!-- wp:gallery {"linkTo":"none"} -->' . "\n";
		$gallery .= '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">';

		foreach ( $files as $file ) {
			$gallery .= "\n<!-- wp:image {\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n";
			$gallery .= '<figure class="wp-block-image size-large">';
			$gallery .= '<img src="' . \esc_url( $file['url'] ) . '" alt="' . \esc_attr( $file['alt'] ) . '"/>';
			$gallery .= '</figure>';
			$gallery .= "\n<!-- /wp:image -->\n";
		}

		$gallery .= "</figure>\n";
		$gallery .= '<!-- /wp:gallery -->';

		return $gallery;
	}
}
