<?php
/**
 * Emoji cache class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

/**
 * Emoji cache class.
 *
 * Handles file caching of custom emoji locally.
 * Emoji are stored in /wp-content/uploads/activitypub/emoji/{domain}/
 * organized by source domain for easier management.
 *
 * This class is responsible ONLY for file operations (download, validate, store, optimize).
 * Content transformation (replacing shortcodes with img tags) is handled by the main
 * Activitypub\Emoji class.
 *
 * @since 5.6.0
 */
class Emoji extends File {
	/**
	 * Maximum dimension for emoji in pixels.
	 *
	 * @var int
	 */
	const MAX_DIMENSION = 128;

	/**
	 * Context identifier for the filter.
	 *
	 * @var string
	 */
	const CONTEXT = 'emoji';

	/**
	 * Base directory for emoji storage.
	 *
	 * @var string
	 */
	const BASE_DIR = '/activitypub/emoji/';

	/**
	 * Get the cache type identifier.
	 *
	 * @return string Cache type.
	 */
	public static function get_type() {
		return 'emoji';
	}

	/**
	 * Get the base directory path relative to uploads.
	 *
	 * @return string Base directory path.
	 */
	public static function get_base_dir() {
		return self::BASE_DIR;
	}

	/**
	 * Get the context identifier for the filter.
	 *
	 * @return string Context identifier.
	 */
	public static function get_context() {
		return self::CONTEXT;
	}

	/**
	 * Get the maximum dimension for emoji.
	 *
	 * @return int Maximum width/height in pixels.
	 */
	public static function get_max_dimension() {
		return self::MAX_DIMENSION;
	}

	/**
	 * Initialize the cache handler.
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Hook into the universal remote media URL filter.
		// This allows third-party CDN plugins to intercept emoji URLs.
		\add_filter( 'activitypub_remote_media_url', array( self::class, 'maybe_cache' ), 10, 4 );
	}

	/**
	 * Maybe cache an emoji URL.
	 *
	 * Hooked to the activitypub_remote_media_url filter.
	 * Delegates to import() to preserve the activitypub_pre_import_emoji filter.
	 *
	 * @param string      $url       The remote URL.
	 * @param string      $context   The context ('avatar', 'media', 'emoji', etc.).
	 * @param string|null $entity_id The entity identifier (unused for emoji, domain extracted from URL).
	 * @param array       $options   Optional. Additional options like 'updated' timestamp.
	 *
	 * @return string The local URL if cached successfully, otherwise the original URL.
	 */
	public static function maybe_cache( $url, $context, $entity_id = null, $options = array() ) {
		if ( self::CONTEXT !== $context || empty( $url ) ) {
			return $url;
		}

		// Delegate to import() which handles the activitypub_pre_import_emoji filter.
		$cached_url = self::import( $url, $options['updated'] ?? null );

		return $cached_url ?: $url;
	}

	/**
	 * Import a remote emoji image locally.
	 *
	 * This is a convenience method that wraps the cache functionality
	 * with staleness checking based on the updated timestamp.
	 *
	 * @param string      $emoji_url The remote emoji URL.
	 * @param string|null $updated   Optional. The remote emoji's updated timestamp (ISO 8601).
	 *                               If provided and newer than cached version, re-downloads.
	 *
	 * @return string|false The local emoji URL on success, false on failure.
	 */
	public static function import( $emoji_url, $updated = null ) {
		if ( empty( $emoji_url ) || ! \filter_var( $emoji_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		/**
		 * Filters the result of emoji import before processing.
		 *
		 * Allows short-circuiting the emoji import, useful for testing.
		 *
		 * @since 5.6.0
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

		$domain = \wp_parse_url( $emoji_url, PHP_URL_HOST );
		if ( empty( $domain ) ) {
			return false;
		}

		$options = array( 'max_dimension' => self::MAX_DIMENSION );
		if ( $updated ) {
			$options['updated'] = $updated;
		}

		return self::get_or_cache( $emoji_url, $domain, $options );
	}

	/**
	 * Generate a hash for an emoji URL.
	 *
	 * Uses full URL path hash to prevent collisions between emoji with the same
	 * filename but different paths (e.g., /set1/kappa.png vs /set2/kappa.png).
	 *
	 * @param string $url The URL to hash.
	 *
	 * @return string The hash string.
	 */
	protected static function generate_hash( $url ) {
		$url_path = \wp_parse_url( $url, PHP_URL_PATH );
		if ( $url_path ) {
			return \md5( $url_path );
		}

		// Fall back to full URL hash.
		return parent::generate_hash( $url );
	}
}
