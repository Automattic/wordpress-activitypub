<?php
/**
 * Attachments processing file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Posts;
use Activitypub\Collection\Remote_Actors;

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
	 * Directory for storing actor avatar files.
	 *
	 * @var string
	 */
	public static $actors_dir = '/activitypub/actors/';

	/**
	 * Directory for storing emoji files (organized by domain).
	 *
	 * @var string
	 */
	public static $emoji_dir = '/activitypub/emoji/';

	/**
	 * Maximum width for imported images.
	 *
	 * @var int
	 */
	const MAX_IMAGE_DIMENSION = 1200;

	/**
	 * Maximum width for actor avatars.
	 *
	 * @var int
	 */
	const MAX_AVATAR_DIMENSION = 512;

	/**
	 * Initialize the class and set up filters.
	 */
	public static function init() {
		\add_action( 'before_delete_post', array( self::class, 'delete_ap_posts_directory' ) );
		\add_action( 'before_delete_post', array( self::class, 'delete_actors_directory' ) );
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

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return;
		}

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
	 * Import a remote emoji image locally.
	 *
	 * Downloads the emoji image and stores it organized by source domain.
	 * If the emoji is already cached and not stale, returns the existing local URL.
	 *
	 * @param string      $emoji_url The remote emoji URL.
	 * @param string|null $updated   Optional. The remote emoji's updated timestamp (ISO 8601).
	 *                               If provided and newer than cached version, re-downloads.
	 *
	 * @return string|false The local emoji URL on success, false on failure.
	 */
	public static function import_emoji( $emoji_url, $updated = null ) {
		if ( empty( $emoji_url ) || ! \filter_var( $emoji_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		/**
		 * Filters the result of emoji import before processing.
		 *
		 * Allows short-circuiting the emoji import, useful for testing.
		 *
		 * @param string|false|null $result    The import result. Return a URL string to short-circuit,
		 *                                     false to indicate failure, or null to proceed normally.
		 * @param string            $emoji_url The remote emoji URL being imported.
		 * @param string|null       $updated   The remote emoji's updated timestamp.
		 */
		$pre_import = \apply_filters( 'activitypub_pre_import_emoji', null, $emoji_url, $updated );
		if ( null !== $pre_import ) {
			return $pre_import;
		}

		// Check if already cached.
		$cached_url = self::get_emoji_url( $emoji_url );
		if ( $cached_url ) {
			// If no updated timestamp provided, use cached version.
			if ( ! $updated ) {
				return $cached_url;
			}

			// Compare timestamps - re-download if remote is newer.
			$paths       = self::get_emoji_storage_paths( $emoji_url );
			$url_path    = \wp_parse_url( $emoji_url, PHP_URL_PATH );
			$file_stem   = \sanitize_file_name( \pathinfo( $url_path, PATHINFO_FILENAME ) );
			$matches     = \glob( $paths['basedir'] . '/' . $file_stem . '.*' );
			$file_path   = ( $matches && \is_file( $matches[0] ) ) ? $matches[0] : null;
			$local_time  = $file_path ? \filemtime( $file_path ) : 0;
			$remote_time = \strtotime( $updated );

			if ( $remote_time && $local_time >= $remote_time ) {
				return $cached_url;
			}
		}

		if ( ! \function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Download the emoji.
		$tmp_file = \download_url( $emoji_url, 10 ); // 10 second timeout for emoji downloads.
		if ( \is_wp_error( $tmp_file ) ) {
			return false;
		}

		if ( ! \wp_get_image_mime( $tmp_file ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Get storage paths for this emoji.
		$paths = self::get_emoji_storage_paths( $emoji_url );

		// Create directory if it doesn't exist.
		if ( ! \wp_mkdir_p( $paths['basedir'] ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Generate filename from URL path (consistent with get_emoji_url lookup).
		$url_path  = \wp_parse_url( $emoji_url, PHP_URL_PATH );
		$file_name = \sanitize_file_name( \basename( $url_path ) );
		$file_path = $paths['basedir'] . '/' . $file_name;

		// Initialize filesystem.
		\WP_Filesystem();
		global $wp_filesystem;

		// Move file to destination (overwrite if exists).
		if ( ! $wp_filesystem->move( $tmp_file, $file_path, true ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		return $paths['baseurl'] . '/' . $file_name;
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

		switch ( $object_type ) {
			case 'comment':
				$sub_dir = self::$comments_dir;
				break;
			case 'actor':
				$sub_dir = self::$actors_dir;
				break;
			default:
				$sub_dir = self::$ap_posts_dir;
				break;
		}

		return array(
			'basedir' => $upload_dir['basedir'] . $sub_dir . $object_id,
			'baseurl' => $upload_dir['baseurl'] . $sub_dir . $object_id,
		);
	}

	/**
	 * Get storage paths for an emoji based on its source URL.
	 *
	 * Organizes emoji by source domain: /activitypub/emoji/{domain}/
	 *
	 * @param string $emoji_url The emoji source URL.
	 *
	 * @return array {
	 *     Storage paths for the emoji.
	 *
	 *     @type string $basedir Base directory path.
	 *     @type string $baseurl Base URL.
	 * }
	 */
	private static function get_emoji_storage_paths( $emoji_url ) {
		$upload_dir = \wp_upload_dir();
		$domain     = \wp_parse_url( $emoji_url, PHP_URL_HOST );
		$domain     = \sanitize_file_name( $domain );

		return array(
			'basedir' => $upload_dir['basedir'] . self::$emoji_dir . $domain,
			'baseurl' => $upload_dir['baseurl'] . self::$emoji_dir . $domain,
		);
	}

	/**
	 * Get the local URL for a cached emoji.
	 *
	 * @param string $emoji_url The remote emoji URL.
	 *
	 * @return string|false The local URL if cached, false otherwise.
	 */
	private static function get_emoji_url( $emoji_url ) {
		if ( empty( $emoji_url ) || ! \filter_var( $emoji_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$paths = self::get_emoji_storage_paths( $emoji_url );

		// Check if directory exists.
		if ( ! \is_dir( $paths['basedir'] ) ) {
			return false;
		}

		// Get the expected filename base from the URL.
		$url_path  = \wp_parse_url( $emoji_url, PHP_URL_PATH );
		$url_name  = \pathinfo( $url_path, PATHINFO_FILENAME );
		$file_name = \sanitize_file_name( $url_name );

		// Look for file with any extension (original or webp after optimization).
		$files = \glob( $paths['basedir'] . '/' . $file_name . '.*' );

		if ( ! empty( $files ) ) {
			return $paths['baseurl'] . '/' . \basename( $files[0] );
		}

		return false;
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

		// Initialize filesystem.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return new \WP_Error( 'filesystem_error', \__( 'Could not initialize filesystem.', 'activitypub' ) );
		}

		$is_local = ! preg_match( '#^https?://#i', $attachment_data['url'] );

		if ( $is_local ) {
			// Read local file from disk.
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

		// Get original filename from URL.
		$original_name = \basename( \wp_parse_url( $attachment_data['url'], PHP_URL_PATH ) );

		// Rename temp file to have proper extension for optimize_image to detect mime type.
		$original_ext = \pathinfo( $original_name, PATHINFO_EXTENSION );
		if ( $original_ext ) {
			$renamed_tmp = $tmp_file . '.' . $original_ext;
			if ( $wp_filesystem->move( $tmp_file, $renamed_tmp, true ) ) {
				$tmp_file = $renamed_tmp;
			}
		}

		// Optimize images before sideloading (resize and convert to WebP).
		$tmp_file = self::optimize_image( $tmp_file, self::MAX_IMAGE_DIMENSION );

		// Update filename extension to match optimized file.
		$new_ext = \pathinfo( $tmp_file, PATHINFO_EXTENSION );
		if ( $new_ext ) {
			$original_name = \preg_replace( '/\.[^.]+$/', '.' . $new_ext, $original_name );
		}

		$file_array = array(
			'name'     => $original_name,
			'tmp_name' => $tmp_file,
		);

		// Prepare attachment post data.
		// Let WordPress auto-detect the mime type from the file.
		$post_data = array(
			'post_title'   => $attachment_data['name'] ?? '',
			'post_content' => $attachment_data['name'] ?? '',
			'post_author'  => $author_id,
			'meta_input'   => array(
				'_source_url' => $attachment_data['url'],
			),
		);

		// Add alt text for images.
		if ( ! empty( $attachment_data['name'] ) ) {
			$original_mime = $attachment_data['mediaType'] ?? '';
			if ( 'image' === strtok( $original_mime, '/' ) ) {
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
	 * For video and audio files, returns the remote URL directly without downloading
	 * to avoid storage overhead for large media files.
	 *
	 * @param array  $attachment_data The normalized attachment data.
	 * @param int    $object_id       The post or comment ID to attach to.
	 * @param string $object_type     The object type ('post' or 'comment').
	 * @param int    $max_dimension   Optional. Maximum image dimension in pixels. Default MAX_IMAGE_DIMENSION.
	 *
	 * @return array|\WP_Error {
	 *     Array of file data on success, WP_Error on failure.
	 *
	 *     @type string $url       Full URL to the saved file (or remote URL for video/audio).
	 *     @type string $mime_type MIME type of the file.
	 *     @type string $alt       Alt text from attachment name field.
	 * }
	 */
	private static function save_file( $attachment_data, $object_id, $object_type, $max_dimension = self::MAX_IMAGE_DIMENSION ) {
		$mime_type = $attachment_data['mediaType'] ?? '';

		// Skip download for video and audio files - use remote URL directly.
		if ( str_starts_with( $mime_type, 'video/' ) || str_starts_with( $mime_type, 'audio/' ) ) {
			return array(
				'url'       => $attachment_data['url'],
				'mime_type' => $attachment_data['mediaType'],
				'alt'       => $attachment_data['name'] ?? '',
			);
		}

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
		$url_path  = \wp_parse_url( $attachment_data['url'], PHP_URL_PATH );
		$file_name = \sanitize_file_name( \basename( $url_path ) );
		$file_path = $paths['basedir'] . '/' . $file_name;

		// Initialize filesystem if needed.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return new \WP_Error( 'filesystem_error', \__( 'Could not initialize filesystem.', 'activitypub' ) );
		}

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

		// Optimize images (resize and convert to WebP).
		$file_path = self::optimize_image( $file_path, $max_dimension );
		$file_name = \basename( $file_path );

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
	 * Get a unique file path by appending a counter if the file already exists.
	 *
	 * @param string $file_path The desired file path.
	 *
	 * @return string A unique file path that doesn't exist.
	 */
	private static function get_unique_path( $file_path ) {
		if ( ! \file_exists( $file_path ) ) {
			return $file_path;
		}

		$path_info = \pathinfo( $file_path );
		$dir       = $path_info['dirname'];
		$base_name = $path_info['filename'];
		$extension = isset( $path_info['extension'] ) ? '.' . $path_info['extension'] : '';
		$counter   = 1;

		do {
			$new_path = $dir . '/' . $base_name . '-' . $counter . $extension;
			++$counter;
		} while ( \file_exists( $new_path ) );

		return $new_path;
	}

	/**
	 * Optimize an image file by resizing and converting to WebP.
	 *
	 * Uses WordPress image editor to resize large images and convert them
	 * to WebP format for better compression while maintaining quality.
	 *
	 * @param string $file_path     Path to the image file.
	 * @param int    $max_dimension Maximum width/height in pixels.
	 *
	 * @return string The optimized file path.
	 */
	private static function optimize_image( $file_path, $max_dimension ) {
		// Check if it's an image.
		$mime_type = \wp_check_filetype( $file_path )['type'] ?? '';
		if ( ! $mime_type || ! \str_starts_with( $mime_type, 'image/' ) ) {
			return $file_path;
		}

		// Skip SVG and GIF files (GIFs may be animated).
		if ( \in_array( $mime_type, array( 'image/svg+xml', 'image/gif' ), true ) ) {
			return $file_path;
		}

		$editor = \wp_get_image_editor( $file_path );
		if ( \is_wp_error( $editor ) ) {
			return $file_path;
		}

		$size         = $editor->get_size();
		$needs_resize = $size['width'] > $max_dimension || $size['height'] > $max_dimension;

		// Resize if needed.
		if ( $needs_resize ) {
			$editor->resize( $max_dimension, $max_dimension, false );
		}

		// Check if WebP is supported.
		$can_webp = $editor->supports_mime_type( 'image/webp' );

		// Determine output format and save.
		if ( $can_webp ) {
			// Convert to WebP.
			$new_path = self::get_unique_path( \preg_replace( '/\.[^.]+$/', '.webp', $file_path ) );
			$result   = $editor->save( $new_path, 'image/webp' );
		} elseif ( \in_array( $mime_type, array( 'image/png', 'image/webp' ), true ) ) {
			// Keep original format for potentially transparent images when WebP not available.
			if ( ! $needs_resize ) {
				// No changes needed.
				return $file_path;
			}
			$result = $editor->save( $file_path );
		} else {
			// Convert to JPEG when WebP not available.
			$new_path = self::get_unique_path( \preg_replace( '/\.[^.]+$/', '.jpg', $file_path ) );
			$result   = $editor->save( $new_path, 'image/jpeg' );
		}

		if ( \is_wp_error( $result ) ) {
			return $file_path;
		}

		// Handle result - $result is always an array from $editor->save().
		$result_path = $result['path'] ?? $file_path;

		// If path changed (format conversion), delete the original file.
		if ( $result_path !== $file_path ) {
			\wp_delete_file( $file_path );
		}

		return $result_path;
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

		// Single image: use standalone image block.
		if ( 1 === \count( $attachment_ids ) && 'image' === $type ) {
			return self::get_image_block( $attachment_ids[0] );
		}

		// Multiple attachments: use gallery block.
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

		// Single image: use standalone image block.
		if ( 1 === \count( $files ) && 'image' === $type ) {
			return self::get_files_image_block( $files[0] );
		}

		// Multiple attachments: use gallery block.
		return self::get_files_gallery_block( $files );
	}

	/**
	 * Get standalone image block markup for file-based attachments.
	 *
	 * @param array $file {
	 *     File data array.
	 *
	 *     @type string $url       Full URL to the file.
	 *     @type string $mime_type MIME type of the file.
	 *     @type string $alt       Alt text for the file.
	 * }
	 *
	 * @return string The image block markup.
	 */
	private static function get_files_image_block( $file ) {
		$block  = '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->' . "\n";
		$block .= '<figure class="wp-block-image size-large">';
		$block .= '<img src="' . \esc_url( $file['url'] ) . '" alt="' . \esc_attr( $file['alt'] ) . '"/>';
		$block .= '</figure>' . "\n";
		$block .= '<!-- /wp:image -->';

		return $block;
	}

	/**
	 * Get standalone image block markup.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string The image block markup.
	 */
	private static function get_image_block( $attachment_id ) {
		$image_src = \wp_get_attachment_image_src( $attachment_id, 'large' );
		if ( ! $image_src ) {
			return '';
		}

		$alt = \get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! $alt ) {
			$alt = \get_post_field( 'post_excerpt', $attachment_id );
		}

		$block  = '<!-- wp:image {"id":' . \esc_attr( $attachment_id ) . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n";
		$block .= '<figure class="wp-block-image size-large">';
		$block .= '<img src="' . \esc_url( $image_src[0] ) . '" alt="' . \esc_attr( $alt ) . '" class="' . \esc_attr( 'wp-image-' . $attachment_id ) . '"/>';
		$block .= '</figure>' . "\n";
		$block .= '<!-- /wp:image -->';

		return $block;
	}

	/**
	 * Get gallery block markup.
	 *
	 * @param int[] $attachment_ids The attachment IDs to use.
	 *
	 * @return string The gallery block markup.
	 */
	private static function get_gallery_block( $attachment_ids ) {
		$gallery  = '<!-- wp:gallery {"columns":2,"linkTo":"none","sizeSlug":"large","imageCrop":true} -->' . "\n";
		$gallery .= '<figure class="wp-block-gallery has-nested-images columns-2 is-cropped">';

		foreach ( $attachment_ids as $id ) {
			$image_src = \wp_get_attachment_image_src( $id, 'large' );
			if ( ! $image_src ) {
				continue;
			}

			$alt = \get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! $alt ) {
				$alt = \get_post_field( 'post_excerpt', $id );
			}

			$gallery .= "\n" . '<!-- wp:image {"id":' . \esc_attr( $id ) . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n";
			$gallery .= '<figure class="wp-block-image size-large">';
			$gallery .= '<img src="' . \esc_url( $image_src[0] ) . '" alt="' . \esc_attr( $alt ) . '" class="' . \esc_attr( 'wp-image-' . $id ) . '"/>';
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
		$gallery  = '<!-- wp:gallery {"columns":2,"linkTo":"none","imageCrop":true} -->' . "\n";
		$gallery .= '<figure class="wp-block-gallery has-nested-images columns-2 is-cropped">';

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

	/**
	 * Save a remote actor's avatar locally.
	 *
	 * Downloads the avatar image, optimizes it, and stores it in the actors directory.
	 * Returns the local URL for the saved avatar.
	 *
	 * @param int    $actor_id   The local actor post ID.
	 * @param string $avatar_url The remote avatar URL.
	 *
	 * @return string|false The local avatar URL on success, false on failure.
	 */
	public static function save_actor_avatar( $actor_id, $avatar_url ) {
		// Validate actor_id is a positive integer to prevent path traversal.
		$actor_id = (int) $actor_id;
		if ( $actor_id <= 0 ) {
			return false;
		}

		if ( empty( $avatar_url ) || ! \filter_var( $avatar_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Delete existing avatar files before saving new one.
		// This prevents accumulating old avatar files since save_file creates unique filenames.
		self::delete_actors_directory( $actor_id );

		$attachment_data = array( 'url' => $avatar_url );
		$result          = self::save_file( $attachment_data, $actor_id, 'actor', self::MAX_AVATAR_DIMENSION );

		if ( \is_wp_error( $result ) || ! isset( $result['url'] ) ) {
			return false;
		}

		return $result['url'];
	}

	/**
	 * Delete the activitypub files directory for an actor.
	 *
	 * @param int $actor_id The actor post ID.
	 */
	public static function delete_actors_directory( $actor_id ) {
		if ( Remote_Actors::POST_TYPE !== \get_post_type( $actor_id ) ) {
			return;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return;
		}

		$activitypub_dir = self::get_storage_paths( $actor_id, 'actor' )['basedir'];

		if ( $wp_filesystem->is_dir( $activitypub_dir ) ) {
			$wp_filesystem->rmdir( $activitypub_dir, true );
		}
	}
}
