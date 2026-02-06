<?php
/**
 * File cache abstract class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

/**
 * Abstract file cache class.
 *
 * Provides shared functionality for caching remote media files locally.
 * Subclasses implement type-specific storage paths and initialization.
 *
 * Caching is lazy/filter-based: URLs pass through the `activitypub_remote_media_url`
 * filter, and cache handlers check if already cached or download on demand.
 *
 * @since 5.6.0
 */
abstract class File {
	/**
	 * Maximum file size in bytes (10MB).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 10485760; // 10 * 1024 * 1024

	/**
	 * Allowed MIME types for cached files.
	 *
	 * @var array
	 */
	const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/svg+xml',
	);

	/**
	 * Get the cache type identifier.
	 *
	 * @return string Cache type (e.g., 'avatar', 'media', 'emoji').
	 */
	abstract public static function get_type();

	/**
	 * Get the base directory path relative to uploads.
	 *
	 * @return string Base directory path (e.g., '/activitypub/actors/').
	 */
	abstract public static function get_base_dir();

	/**
	 * Get the context identifier for the activitypub_remote_media_url filter.
	 *
	 * @return string Context identifier (e.g., 'avatar', 'media', 'emoji').
	 */
	abstract public static function get_context();

	/**
	 * Get the maximum dimension for images of this type.
	 *
	 * @return int Maximum width/height in pixels.
	 */
	abstract public static function get_max_dimension();

	/**
	 * Initialize the cache handler.
	 *
	 * Subclasses should override this to register filters and actions.
	 */
	public static function init() {
		// Subclasses implement specific initialization.
	}

	/**
	 * Check if this cache type is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public static function is_enabled() {
		$type = static::get_type();

		/**
		 * Filters whether a specific cache type is enabled.
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the cache type
		 * (e.g., 'avatar', 'media', 'emoji').
		 *
		 * @since 5.6.0
		 *
		 * @param bool $enabled Whether this cache type is enabled. Default true.
		 */
		return (bool) \apply_filters( "activitypub_cache_{$type}_enabled", true );
	}

	/**
	 * Get storage paths for an entity.
	 *
	 * @param string|int $entity_id The entity identifier (post ID, domain, etc.).
	 *
	 * @return array {
	 *     Storage paths for the entity.
	 *
	 *     @type string $basedir Base directory path.
	 *     @type string $baseurl Base URL.
	 * }
	 */
	public static function get_storage_paths( $entity_id ) {
		$upload_dir = \wp_upload_dir();
		$entity_id  = \sanitize_file_name( (string) $entity_id );

		return array(
			'basedir' => $upload_dir['basedir'] . static::get_base_dir() . $entity_id,
			'baseurl' => $upload_dir['baseurl'] . static::get_base_dir() . $entity_id,
		);
	}

	/**
	 * Get a cached file URL if it exists.
	 *
	 * @param string     $url       The remote URL.
	 * @param string|int $entity_id The entity identifier.
	 *
	 * @return string|false The local URL if cached, false otherwise.
	 */
	public static function get( $url, $entity_id ) {
		if ( empty( $url ) || ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$paths = static::get_storage_paths( $entity_id );

		if ( ! \is_dir( $paths['basedir'] ) ) {
			return false;
		}

		$hash    = static::generate_hash( $url );
		$matches = \glob( $paths['basedir'] . '/' . $hash . '.*' );

		if ( ! empty( $matches ) && \is_file( $matches[0] ) ) {
			return $paths['baseurl'] . '/' . \basename( $matches[0] );
		}

		return false;
	}

	/**
	 * Get a cached file or cache it if not present.
	 *
	 * This is the main entry point for lazy caching. Called via filter hooks.
	 *
	 * @param string     $url       The remote URL.
	 * @param string|int $entity_id The entity identifier.
	 * @param array      $options   Optional. Additional options like 'updated' timestamp.
	 *
	 * @return string|false The local URL on success, false on failure.
	 */
	public static function get_or_cache( $url, $entity_id, $options = array() ) {
		if ( empty( $url ) || ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Check if already cached.
		$cached_url = static::get( $url, $entity_id );
		if ( $cached_url ) {
			// Check for staleness if updated timestamp provided.
			if ( ! empty( $options['updated'] ) ) {
				$paths       = static::get_storage_paths( $entity_id );
				$hash        = static::generate_hash( $url );
				$matches     = \glob( $paths['basedir'] . '/' . $hash . '.*' );
				$file_path   = ( $matches && \is_file( $matches[0] ) ) ? $matches[0] : null;
				$local_time  = $file_path ? \filemtime( $file_path ) : 0;
				$remote_time = \strtotime( $options['updated'] );

				if ( $remote_time && $local_time >= $remote_time ) {
					return $cached_url;
				}
				// Stale - continue to re-download.
			} else {
				return $cached_url;
			}
		}

		// Download and cache the file.
		return static::cache( $url, $entity_id, $options );
	}

	/**
	 * Cache a remote file locally.
	 *
	 * Downloads the file, validates it, optimizes images, and stores locally.
	 *
	 * @param string     $url       The remote URL.
	 * @param string|int $entity_id The entity identifier.
	 * @param array      $options   Optional. Additional options.
	 *
	 * @return string|false The local URL on success, false on failure.
	 */
	public static function cache( $url, $entity_id, $options = array() ) {
		$result = static::download_and_validate( $url );

		if ( \is_wp_error( $result ) || empty( $result['file'] ) ) {
			return false;
		}

		$tmp_file = $result['file'];
		$paths    = static::get_storage_paths( $entity_id );

		// Create directory if it doesn't exist.
		if ( ! \wp_mkdir_p( $paths['basedir'] ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Generate hash-based filename.
		$hash = static::generate_hash( $url );
		$ext  = \pathinfo( $tmp_file, PATHINFO_EXTENSION );
		if ( empty( $ext ) ) {
			$ext = static::mime_to_extension( $result['mime_type'] );
		}
		$file_name = $hash . '.' . $ext;
		$file_path = $paths['basedir'] . '/' . $file_name;

		// Initialize filesystem.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Move file to destination.
		if ( ! $wp_filesystem->move( $tmp_file, $file_path, true ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Optimize image if applicable.
		$max_dimension = $options['max_dimension'] ?? static::get_max_dimension();
		$file_path     = static::optimize_image( $file_path, $max_dimension );
		$file_name     = \basename( $file_path );

		return $paths['baseurl'] . '/' . $file_name;
	}

	/**
	 * Invalidate cached files for an entity.
	 *
	 * Deletes the entire entity directory and all its contents.
	 *
	 * @param string|int $entity_id The entity identifier.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function invalidate_entity( $entity_id ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return false;
		}

		$paths = static::get_storage_paths( $entity_id );

		if ( $wp_filesystem->is_dir( $paths['basedir'] ) ) {
			return $wp_filesystem->rmdir( $paths['basedir'], true );
		}

		return true;
	}

	/**
	 * Generate a hash for a URL.
	 *
	 * Used to create consistent, collision-resistant filenames.
	 *
	 * @param string $url The URL to hash.
	 *
	 * @return string The hash string (16 characters).
	 */
	protected static function generate_hash( $url ) {
		return substr( md5( $url ), 0, 16 );
	}

	/**
	 * Validate a URL is safe to fetch.
	 *
	 * @param string $url The URL to validate.
	 *
	 * @return bool True if URL is safe to fetch, false otherwise.
	 */
	protected static function is_safe_url( $url ) {
		if ( empty( $url ) || ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return (bool) \wp_http_validate_url( $url );
	}

	/**
	 * Download and validate a remote file.
	 *
	 * @param string $url The remote URL to download.
	 *
	 * @return array|\WP_Error {
	 *     Array on success, WP_Error on failure.
	 *
	 *     @type string $file      Path to downloaded file.
	 *     @type string $mime_type Validated MIME type.
	 * }
	 */
	protected static function download_and_validate( $url ) {
		// Validate URL is safe to fetch.
		if ( ! static::is_safe_url( $url ) ) {
			return new \WP_Error( 'invalid_url', \__( 'URL is not allowed.', 'activitypub' ) );
		}

		if ( ! \function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp_file = \download_url( $url, 15 ); // 15 second timeout.

		if ( \is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Validate file size.
		$file_size = \filesize( $tmp_file );
		if ( $file_size > static::MAX_FILE_SIZE ) {
			\wp_delete_file( $tmp_file );
			return new \WP_Error( 'file_too_large', \__( 'File exceeds maximum size limit.', 'activitypub' ) );
		}

		// Validate MIME type.
		$validation = static::validate_mime_type( $tmp_file );
		if ( \is_wp_error( $validation ) ) {
			\wp_delete_file( $tmp_file );
			return $validation;
		}

		// Get the validated file path (may have been renamed).
		$file_path = \is_string( $validation ) ? $validation : $tmp_file;
		$mime_type = static::get_file_mime_type( $file_path );

		return array(
			'file'      => $file_path,
			'mime_type' => $mime_type,
		);
	}

	/**
	 * Validate MIME type of a file using multiple methods.
	 *
	 * This method addresses potential wp_get_image_mime() bypass concerns
	 * by using finfo, getimagesize, and wp_check_filetype_and_ext for validation.
	 *
	 * @param string $file_path Path to the file.
	 *
	 * @return string|\WP_Error File path (possibly renamed) on success, WP_Error on failure.
	 */
	protected static function validate_mime_type( $file_path ) {
		// Method 1: Use finfo for reliable MIME detection.
		$finfo = \finfo_open( FILEINFO_MIME_TYPE );
		if ( ! $finfo ) {
			return new \WP_Error( 'finfo_failed', \__( 'Could not open finfo.', 'activitypub' ) );
		}

		$mime = \finfo_file( $finfo, $file_path );
		\finfo_close( $finfo );

		if ( ! \in_array( $mime, static::ALLOWED_MIME_TYPES, true ) ) {
			return new \WP_Error( 'invalid_mime', \__( 'File type not allowed.', 'activitypub' ) );
		}

		// Method 2: Verify it's actually a valid image (except SVG).
		if ( 'image/svg+xml' !== $mime ) {
			$image_info = @\getimagesize( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $image_info ) {
				return new \WP_Error( 'invalid_image', \__( 'File is not a valid image.', 'activitypub' ) );
			}

			// Verify image can actually be rendered.
			if ( ! \function_exists( 'file_is_displayable_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			if ( ! \file_is_displayable_image( $file_path ) ) {
				return new \WP_Error( 'not_displayable', \__( 'Image cannot be displayed.', 'activitypub' ) );
			}
		}

		// Method 3: Use WordPress's wp_check_filetype_and_ext for additional validation.
		$expected_ext = static::mime_to_extension( $mime );
		$file_name    = \wp_basename( $file_path );
		$file_info    = \wp_check_filetype_and_ext( $file_path, $file_name, static::ALLOWED_MIME_TYPES );

		// If WordPress couldn't validate the file type, reject it.
		if ( empty( $file_info['type'] ) || ! \str_starts_with( $file_info['type'], 'image/' ) ) {
			return new \WP_Error( 'invalid_file_type', \__( 'File type validation failed.', 'activitypub' ) );
		}

		// Method 4: Ensure file extension matches MIME type.
		$ext = \pathinfo( $file_path, PATHINFO_EXTENSION );

		if ( strtolower( $ext ) !== $expected_ext ) {
			// Rename file to correct extension using WP_Filesystem.
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				\WP_Filesystem();
			}

			$new_path = \preg_replace( '/\.[^.]+$/', '.' . $expected_ext, $file_path );
			if ( empty( $new_path ) || $new_path === $file_path ) {
				$new_path = $file_path . '.' . $expected_ext;
			}

			if ( $wp_filesystem && $wp_filesystem->move( $file_path, $new_path, true ) ) {
				return $new_path;
			}
		}

		return $file_path;
	}

	/**
	 * Get the MIME type of a file.
	 *
	 * @param string $file_path Path to the file.
	 *
	 * @return string The MIME type.
	 */
	protected static function get_file_mime_type( $file_path ) {
		$finfo = \finfo_open( FILEINFO_MIME_TYPE );
		if ( $finfo ) {
			$mime = \finfo_file( $finfo, $file_path );
			\finfo_close( $finfo );
			return $mime;
		}

		// Fallback to WordPress function.
		return \wp_check_filetype( $file_path )['type'] ?? '';
	}

	/**
	 * Convert MIME type to file extension.
	 *
	 * @param string $mime The MIME type.
	 *
	 * @return string The file extension (without dot).
	 */
	protected static function mime_to_extension( $mime ) {
		$map = array(
			'image/jpeg'    => 'jpg',
			'image/png'     => 'png',
			'image/gif'     => 'gif',
			'image/webp'    => 'webp',
			'image/svg+xml' => 'svg',
		);

		return $map[ $mime ] ?? 'bin';
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
	protected static function optimize_image( $file_path, $max_dimension ) {
		// Check if it's an image.
		$mime_type = static::get_file_mime_type( $file_path );
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
		$dir = \dirname( $file_path );

		if ( $can_webp ) {
			// Convert to WebP.
			$new_name = \wp_unique_filename( $dir, \preg_replace( '/\.[^.]+$/', '.webp', \basename( $file_path ) ) );
			$result   = $editor->save( $dir . '/' . $new_name, 'image/webp' );
		} elseif ( \in_array( $mime_type, array( 'image/png', 'image/webp' ), true ) ) {
			// Keep original format for potentially transparent images when WebP not available.
			if ( ! $needs_resize ) {
				return $file_path;
			}
			$result = $editor->save( $file_path );
		} else {
			// Convert to JPEG when WebP not available.
			$new_name = \wp_unique_filename( $dir, \preg_replace( '/\.[^.]+$/', '.jpg', \basename( $file_path ) ) );
			$result   = $editor->save( $dir . '/' . $new_name, 'image/jpeg' );
		}

		if ( \is_wp_error( $result ) ) {
			return $file_path;
		}

		// Handle result.
		$result_path = $result['path'] ?? $file_path;

		// If path changed (format conversion), delete the original file.
		if ( $result_path !== $file_path ) {
			\wp_delete_file( $file_path );
		}

		return $result_path;
	}
}
