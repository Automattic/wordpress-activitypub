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
	 * Default allowed MIME types for cached files.
	 *
	 * @var array
	 */
	const DEFAULT_ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * The formats the cache writes its images in, with their extensions.
	 *
	 * Every cached file is written by the image editor in one of these, under a name the cache
	 * gives it, so these are also the only files it serves.
	 *
	 * @since 9.4.4
	 *
	 * @var array
	 */
	const OUTPUT_FORMATS = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
	);

	/**
	 * Cached finfo instance for MIME type detection.
	 *
	 * Using a cached instance avoids repeated finfo_open() calls and
	 * the deprecated finfo_close() in PHP 8.5+.
	 *
	 * @var \finfo|null
	 */
	private static $finfo = null;


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
	 *
	 * @return void
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

		$file = static::get_cached_file( $url, $entity_id );
		if ( ! $file ) {
			return false;
		}

		return static::get_storage_paths( $entity_id )['baseurl'] . '/' . \basename( $file );
	}

	/**
	 * Get the path of the cached copy of a URL.
	 *
	 * Only the names cache() gives its files are looked up: the hash of the URL with the extension
	 * of one of the output formats.
	 *
	 * @since 9.4.4
	 *
	 * @param string     $url       The remote URL.
	 * @param string|int $entity_id The entity identifier.
	 *
	 * @return string|false The file path if cached, false otherwise.
	 */
	protected static function get_cached_file( $url, $entity_id ) {
		$base = static::get_storage_paths( $entity_id )['basedir'] . '/' . static::generate_hash( $url );

		foreach ( self::OUTPUT_FORMATS as $extension ) {
			if ( \is_file( $base . '.' . $extension ) ) {
				return $base . '.' . $extension;
			}
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
			/*
			 * Only a valid `updated` time that is newer than the cached file downloads it again.
			 * A time in the future, or one that cannot be read, would make every render download.
			 */
			$remote_time = empty( $options['updated'] ) ? false : \strtotime( $options['updated'] );
			if ( ! $remote_time || $remote_time > \time() ) {
				return $cached_url;
			}

			$file       = static::get_cached_file( $url, $entity_id );
			$local_time = $file ? \filemtime( $file ) : 0;
			if ( $local_time >= $remote_time ) {
				return $cached_url;
			}
			// Stale - continue to re-download.
		}

		/*
		 * Files are downloaded while a page renders, so a URL that failed is not tried again for
		 * a while. Otherwise every page view would wait for the same slow or broken host.
		 */
		$failed_key = 'activitypub_failed_download_' . static::get_type() . '_' . static::generate_hash( $url );
		if ( \get_transient( $failed_key ) ) {
			return false;
		}

		// Download and cache the file.
		$local_url = static::cache( $url, $entity_id, $options );
		if ( ! $local_url ) {
			\set_transient( $failed_key, 1, HOUR_IN_SECONDS );
		}

		return $local_url;
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

		/*
		 * The image editor writes the copy that is stored, and nothing else is: a file the editor
		 * cannot read or write is not cached. The image is optimized while it is still a temporary
		 * file, so the name it is published under is the format it ends up in.
		 */
		$max_dimension = $options['max_dimension'] ?? static::get_max_dimension();
		$tmp_file      = static::optimize_image( $result['file'], $max_dimension );
		if ( ! $tmp_file ) {
			\wp_delete_file( $result['file'] );
			return false;
		}

		// The extension comes from the format the editor wrote, and only an output format has one.
		$ext   = self::OUTPUT_FORMATS[ static::get_file_mime_type( $tmp_file ) ] ?? '';
		$paths = static::get_storage_paths( $entity_id );
		if ( ! $ext || ! \wp_mkdir_p( $paths['basedir'] ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Generate hash-based filename.
		$hash      = static::generate_hash( $url );
		$file_name = $hash . '.' . $ext;
		$file_path = $paths['basedir'] . '/' . $file_name;

		/*
		 * Move the file to its destination, once more after re-creating the directory if that
		 * fails. Caching an entity's file races with invalidating that same entity, which deletes
		 * the whole directory: when the delete lands between the two, the move has nowhere to put
		 * the file and the download is lost for no reason.
		 */
		if ( ! static::get_filesystem()->move( $tmp_file, $file_path, true ) ) {
			if ( ! \wp_mkdir_p( $paths['basedir'] ) || ! static::get_filesystem()->move( $tmp_file, $file_path, true ) ) {
				\wp_delete_file( $tmp_file );
				return false;
			}
		}

		// A copy in the other format, from before the source changed, would still be found first.
		foreach ( self::OUTPUT_FORMATS as $extension ) {
			if ( $extension !== $ext ) {
				\wp_delete_file( $paths['basedir'] . '/' . $hash . '.' . $extension );
			}
		}

		$local_url = $paths['baseurl'] . '/' . $file_name;

		/**
		 * Fires after a remote media file has been successfully cached.
		 *
		 * Use this hook for logging, analytics, or post-processing.
		 *
		 * @since 5.6.0
		 *
		 * @param string     $local_url  The local URL of the cached file.
		 * @param string     $url        The original remote URL.
		 * @param string|int $entity_id  The entity identifier.
		 * @param string     $type       The cache type ('avatar', 'media', 'emoji').
		 * @param string     $file_path  The local file system path.
		 */
		\do_action( 'activitypub_media_cached', $local_url, $url, $entity_id, static::get_type(), $file_path );

		return $local_url;
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
		$paths = static::get_storage_paths( $entity_id );

		return static::delete_directory( $paths['basedir'] );
	}

	/**
	 * Get a direct filesystem instance.
	 *
	 * Uses WP_Filesystem_Direct explicitly instead of WP_Filesystem(),
	 * which may fall back to FTP on servers where ABSPATH is not writable.
	 * The uploads directory (where cache files live) is always writable by
	 * the web server — the same assumption WordPress core makes for media
	 * uploads in _wp_handle_upload().
	 *
	 * @since 8.0.0
	 *
	 * @return \WP_Filesystem_Direct The direct filesystem instance.
	 */
	protected static function get_filesystem() {
		static $filesystem = null;

		if ( null === $filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

			$filesystem = new \WP_Filesystem_Direct( null );
		}

		return $filesystem;
	}

	/**
	 * Delete a directory and all its contents.
	 *
	 * @since 8.0.0
	 *
	 * @param string $basedir The directory path to delete.
	 *
	 * @return bool True on success or if directory doesn't exist, false on failure.
	 */
	public static function delete_directory( $basedir ) {
		if ( ! \is_dir( $basedir ) ) {
			return true;
		}

		return static::get_filesystem()->rmdir( $basedir, true );
	}

	/**
	 * Remove legacy `<hash>-N.webp` and `<hash>-N.jpg` image copies.
	 *
	 * Keep an existing canonical image, or promote the newest copy when it is missing.
	 * A failed promotion leaves the group intact. Without `$delete`, only count the changes.
	 *
	 * Each directory is scanned twice, retaining only the newest copy per hash rather than every
	 * duplicate. Subdirectories are cleaned before this directory's files are removed.
	 *
	 * @since 9.4.0
	 *
	 * @param string $directory The cache directory to walk, subdirectories included.
	 * @param bool   $delete    Whether to remove and move the files. Default false, count only.
	 *
	 * @return array {
	 *     What was done, or would be.
	 *
	 *     @type int $removed  Copies removed.
	 *     @type int $bytes    Their combined size.
	 *     @type int $promoted Copies moved to the canonical name.
	 *     @type int $failed   Files or directories that could not be read, moved or removed.
	 * }
	 */
	public static function remove_duplicates( $directory, $delete = false ) {
		$result = array(
			'removed'  => 0,
			'bytes'    => 0,
			'promoted' => 0,
			'failed'   => 0,
		);

		// A live cache can remove entity directories at any time.
		if ( ! \is_dir( $directory ) ) {
			return $result;
		}

		if ( ! \is_readable( $directory ) ) {
			++$result['failed'];
			return $result;
		}

		try {
			$entries = new \FilesystemIterator( $directory, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME );
		} catch ( \UnexpectedValueException $exception ) {
			// Entity directories can disappear between the readability check and opening the iterator.
			\clearstatcache( true, $directory );
			if ( \is_dir( $directory ) ) {
				++$result['failed'];
			}
			return $result;
		}

		/*
		 * The lookup matches `<hash>.*`: retain one candidate per hash, not per format or copy.
		 */
		$groups  = array();
		$pattern = '/^([0-9a-f]{32})-([1-9][0-9]*)\.(webp|jpg)$/';

		foreach ( $entries as $file ) {
			// Match the previous glob's exclusion of hidden entries.
			if ( '.' === \basename( $file )[0] ) {
				continue;
			}

			if ( \is_dir( $file ) ) {
				// Linked directories can lead out of the cache or back into it.
				if ( ! \is_link( $file ) ) {
					foreach ( static::remove_duplicates( $file, $delete ) as $key => $count ) {
						$result[ $key ] += $count;
					}
				}
				continue;
			}

			// Only the exact shape the old code produced: a full hash, a counter and a converted format.
			if ( ! \is_file( $file ) || ! \preg_match( $pattern, \basename( $file ), $match ) ) {
				continue;
			}

			$canonical = $directory . '/' . $match[1];
			$copy      = array(
				'time'    => (int) \filemtime( $file ),
				'counter' => (int) $match[2],
				'path'    => $file,
			);

			// Modification time decides, then the counter; the path makes exact ties deterministic.
			if ( ! isset( $groups[ $canonical ] ) || $copy > $groups[ $canonical ] ) {
				$groups[ $canonical ] = $copy;
			}
		}

		$filesystem = static::get_filesystem();

		foreach ( $groups as $canonical => $newest ) {
			// With no file to serve under the hash, in any format, the newest copy becomes it, in its own.
			if ( ! \glob( static::escape_glob_pattern( $canonical ) . '.*' ) ) {
				$target = $canonical . '.' . \pathinfo( $newest['path'], PATHINFO_EXTENSION );

				// If it cannot be moved, the group stays whole; removing the others would leave nothing to promote later.
				if ( $delete && ! $filesystem->move( $newest['path'], $target ) ) {
					++$result['failed'];
					unset( $groups[ $canonical ] );
					continue;
				}

				++$result['promoted'];
				$groups[ $canonical ] = $newest['path'];
			} else {
				// A canonical file already exists, so every numbered copy can be removed.
				$groups[ $canonical ] = '';
			}
		}

		// Rewind the streaming iterator to remove copies without retaining their paths in memory.
		foreach ( $entries as $file ) {
			if ( ! \is_file( $file ) || ! \preg_match( $pattern, \basename( $file ), $match ) ) {
				continue;
			}

			$canonical = $directory . '/' . $match[1];
			if ( ! isset( $groups[ $canonical ] ) || $file === $groups[ $canonical ] ) {
				continue;
			}

			$size = (int) \filesize( $file );
			if ( $delete && ! $filesystem->delete( $file ) ) {
				++$result['failed'];
				continue;
			}

			++$result['removed'];
			$result['bytes'] += $size;
		}

		return $result;
	}

	/**
	 * Generate a hash for a URL.
	 *
	 * Uses full MD5 hash (32 characters) for better collision resistance.
	 * With truncated hashes, collision probability increases significantly
	 * at scale.
	 *
	 * @param string $url The URL to hash.
	 *
	 * @return string The full MD5 hash string (32 characters).
	 */
	protected static function generate_hash( $url ) {
		return \md5( $url );
	}

	/**
	 * Escape glob metacharacters in a pattern.
	 *
	 * This prevents special characters (*, ?, [, ]) from being interpreted
	 * as glob patterns when searching for files.
	 *
	 * @param string $pattern The pattern to escape.
	 *
	 * @return string The escaped pattern safe for use in glob().
	 */
	protected static function escape_glob_pattern( $pattern ) {
		return \preg_replace( '/([*?\[\]])/', '[$1]', $pattern );
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

		/**
		 * Filters whether a URL passes safety validation.
		 *
		 * By default, uses wp_http_validate_url() which prevents SSRF attacks
		 * by blocking private IPs and localhost. This filter allows overriding
		 * for testing or custom validation needs.
		 *
		 * @since 5.6.0
		 *
		 * @param bool|null $is_safe Whether the URL is safe. Return true/false to override,
		 *                           or null to use default wp_http_validate_url() check.
		 * @param string    $url     The URL being validated.
		 */
		$is_safe = \apply_filters( 'activitypub_cache_is_safe_url', null, $url );

		if ( null !== $is_safe ) {
			return (bool) $is_safe;
		}

		return (bool) \wp_http_validate_url( $url );
	}

	/**
	 * Get allowed MIME types for this cache type.
	 *
	 * @return array Array of allowed MIME types.
	 */
	protected static function get_allowed_mime_types() {
		$type = static::get_type();

		/**
		 * Filters the allowed MIME types for a cache type.
		 *
		 * Use this filter to add or remove allowed MIME types.
		 *
		 * @since 5.6.0
		 *
		 * @param array  $mime_types Array of allowed MIME types.
		 * @param string $type       The cache type ('avatar', 'media', 'emoji').
		 */
		return (array) \apply_filters( 'activitypub_cache_allowed_mime_types', static::DEFAULT_ALLOWED_MIME_TYPES, $type );
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
		$type = static::get_type();

		/**
		 * Filters the download result before fetching a URL.
		 *
		 * Allows short-circuiting the download process by providing a pre-downloaded
		 * file path. Useful for testing or when files are already available locally.
		 *
		 * @since 5.6.0
		 *
		 * @param array|null $result {
		 *     Return null to proceed with download, or array with file info.
		 *
		 *     @type string $file      Path to the downloaded file.
		 *     @type string $mime_type The file's MIME type.
		 * }
		 * @param string     $url  The URL that would be downloaded.
		 * @param string     $type The cache type ('avatar', 'media', 'emoji').
		 */
		$pre_download = \apply_filters( 'activitypub_pre_download_url', null, $url, $type );

		if ( null !== $pre_download ) {
			return $pre_download;
		}

		/**
		 * Filters whether a URL should be cached.
		 *
		 * Allows preventing specific URLs from being downloaded and cached.
		 * Return false to skip caching this URL.
		 *
		 * @since 5.6.0
		 *
		 * @param bool   $should_cache Whether to cache this URL. Default true.
		 * @param string $url          The remote URL.
		 * @param string $type         The cache type ('avatar', 'media', 'emoji').
		 */
		$should_cache = \apply_filters( 'activitypub_should_cache_url', true, $url, $type );

		if ( ! $should_cache ) {
			return new \WP_Error( 'cache_skipped', \__( 'URL caching was skipped by filter.', 'activitypub' ) );
		}

		// Validate URL is safe to fetch.
		if ( ! static::is_safe_url( $url ) ) {
			return new \WP_Error( 'invalid_url', \__( 'URL is not allowed.', 'activitypub' ) );
		}

		if ( ! \function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// At most one byte past the limit is written, so the size check below rejects an oversized download.
		$tmp_file = \wp_tempnam( 'activitypub-cache' );
		if ( ! $tmp_file ) {
			return new \WP_Error( 'http_no_file', \__( 'Could not create a temporary file.', 'activitypub' ) );
		}

		$response = \wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'stream'              => true,
				'filename'            => $tmp_file,
				'limit_response_size' => static::MAX_FILE_SIZE + 1,
			)
		);

		if ( \is_wp_error( $response ) ) {
			\wp_delete_file( $tmp_file );
			return $response;
		}

		if ( 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			\wp_delete_file( $tmp_file );
			return new \WP_Error( 'http_404', \trim( \wp_remote_retrieve_response_message( $response ) ) );
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
		$allowed_mime_types = static::get_allowed_mime_types();

		// Require fileinfo extension for validation.
		if ( ! \extension_loaded( 'fileinfo' ) ) {
			return new \WP_Error( 'finfo_failed', \__( 'Fileinfo extension not available.', 'activitypub' ) );
		}

		// Method 1: Use cached finfo instance for reliable MIME detection.
		if ( null === self::$finfo ) {
			self::$finfo = new \finfo( FILEINFO_MIME_TYPE );
		}

		$mime = self::$finfo->file( $file_path );

		if ( ! \in_array( $mime, $allowed_mime_types, true ) ) {
			return new \WP_Error( 'invalid_mime', \__( 'File type not allowed.', 'activitypub' ) );
		}

		// Method 2: Verify it's actually a valid image.
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

		/*
		 * Method 3: Use WordPress's wp_check_filetype_and_ext for additional validation.
		 * MIME type restriction is already enforced by finfo in Method 1; this cross-checks
		 * that file content matches the declared type using WordPress defaults.
		 */
		$expected_ext = \wp_get_default_extension_for_mime_type( $mime );

		// Use the detected extension, since a downloaded temp file has a .tmp extension.
		$file_name = \pathinfo( \wp_basename( $file_path ), PATHINFO_FILENAME ) . '.' . $expected_ext;
		$file_info = \wp_check_filetype_and_ext( $file_path, $file_name );

		// If WordPress couldn't validate the file type, reject it.
		if ( empty( $file_info['type'] ) || ! \str_starts_with( $file_info['type'], 'image/' ) ) {
			return new \WP_Error( 'invalid_file_type', \__( 'File type validation failed.', 'activitypub' ) );
		}

		// Method 4: Ensure file extension matches MIME type.
		$ext = \pathinfo( $file_path, PATHINFO_EXTENSION );

		if ( \strtolower( $ext ) !== $expected_ext ) {
			// Name the file after its content type, and stop if it can't be renamed.
			$dir      = \dirname( $file_path );
			$new_path = $dir . '/' . \wp_unique_filename( $dir, \md5( $file_path ) . '.' . $expected_ext );

			if ( ! static::get_filesystem()->move( $file_path, $new_path, true ) ) {
				return new \WP_Error( 'invalid_file_type', \__( 'File type validation failed.', 'activitypub' ) );
			}

			return $new_path;
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
		if ( \extension_loaded( 'fileinfo' ) ) {
			if ( null === self::$finfo ) {
				self::$finfo = new \finfo( FILEINFO_MIME_TYPE );
			}
			return self::$finfo->file( $file_path );
		}

		// Fallback to WordPress function.
		return \wp_check_filetype( $file_path )['type'] ?? '';
	}

	/**
	 * Write a resized copy of an image in one of the output formats.
	 *
	 * A JPEG or PNG keeps its format, anything else becomes a PNG. Every image is written again by
	 * the editor. An animated image keeps its first frame.
	 *
	 * @param string $file_path     Path to the image file.
	 * @param int    $max_dimension Maximum width/height in pixels.
	 *
	 * @return string|false The path of the file the editor wrote, false if it could not read or write it.
	 */
	protected static function optimize_image( $file_path, $max_dimension ) {
		// Check if it's an image.
		$mime_type = static::get_file_mime_type( $file_path );
		if ( ! $mime_type || ! \str_starts_with( $mime_type, 'image/' ) ) {
			return false;
		}

		$editor = \wp_get_image_editor( $file_path );
		if ( \is_wp_error( $editor ) ) {
			return false;
		}

		// Resize if needed.
		$size = $editor->get_size();
		if ( $size['width'] > $max_dimension || $size['height'] > $max_dimension ) {
			$editor->resize( $max_dimension, $max_dimension, false );
		}

		// The source format is kept when the cache writes it, anything else becomes a PNG.
		$output   = isset( self::OUTPUT_FORMATS[ $mime_type ] ) && $editor->supports_mime_type( $mime_type ) ? $mime_type : 'image/png';
		$dir      = \dirname( $file_path );
		$new_name = \wp_unique_filename( $dir, \pathinfo( $file_path, PATHINFO_FILENAME ) . '-cached.' . self::OUTPUT_FORMATS[ $output ] );

		// The site's output format mapping is meant for its media library, not for this cache.
		$no_mapping = static function () {
			return array();
		};
		\add_filter( 'image_editor_output_format', $no_mapping, PHP_INT_MAX );
		try {
			$result = $editor->save( $dir . '/' . $new_name, $output );
		} finally {
			\remove_filter( 'image_editor_output_format', $no_mapping, PHP_INT_MAX );
		}

		if ( \is_wp_error( $result ) || empty( $result['path'] ) ) {
			return false;
		}

		/*
		 * The copy has a name of its own, so the original is deleted. It may already be gone:
		 * invalidating the entity deletes its whole directory, and that can land while this file
		 * is being converted. Deleting it again is not an error worth a diagnostic.
		 */
		if ( \file_exists( $file_path ) ) {
			\wp_delete_file( $file_path );
		}

		return $result['path'];
	}
}
