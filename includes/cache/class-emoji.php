<?php
/**
 * Emoji cache class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

use Activitypub\Collection\Posts;

/**
 * Emoji cache class.
 *
 * Handles caching of custom emoji locally.
 * Emoji are stored in /wp-content/uploads/activitypub/emoji/{domain}/
 * organized by source domain for easier management.
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
		\add_filter( 'activitypub_remote_media_url', array( self::class, 'maybe_cache' ), 10, 3 );

		// Process emoji in ap_post content after save.
		\add_action( 'save_post_' . Posts::POST_TYPE, array( self::class, 'replace_post_emoji' ), 15 );

		// Process emoji in comments after insert.
		\add_action( 'wp_insert_comment', array( self::class, 'replace_comment_emoji' ), 10, 2 );
	}

	/**
	 * Replace emoji in ap_post content via hook.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function replace_post_emoji( $post_id ) {
		$emoji_data = \get_post_meta( $post_id, '_activitypub_emoji', true );
		if ( empty( $emoji_data ) || ! \is_array( $emoji_data ) ) {
			return;
		}

		$post = \get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			\delete_post_meta( $post_id, '_activitypub_emoji' );
			return;
		}

		$content = $post->post_content;
		$changed = false;

		foreach ( $emoji_data as $emoji ) {
			if ( empty( $emoji['url'] ) || empty( $emoji['name'] ) ) {
				continue;
			}

			$local_url = self::import( $emoji['url'], $emoji['updated'] ?? null );
			if ( $local_url ) {
				$new_content = self::replace_emoji_in_text( $content, $emoji['name'], $local_url );
				if ( $new_content !== $content ) {
					$content = $new_content;
					$changed = true;
				}
			}
		}

		// Clear emoji meta.
		\delete_post_meta( $post_id, '_activitypub_emoji' );

		if ( $changed ) {
			// Unhook to prevent infinite loop.
			\remove_action( 'save_post_' . Posts::POST_TYPE, array( self::class, 'replace_post_emoji' ), 15 );

			\wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				)
			);

			// Re-hook.
			\add_action( 'save_post_' . Posts::POST_TYPE, array( self::class, 'replace_post_emoji' ), 15 );
		}
	}

	/**
	 * Replace emoji in comment content via hook.
	 *
	 * @param int         $comment_id The comment ID.
	 * @param \WP_Comment $comment    The comment object.
	 */
	public static function replace_comment_emoji( $comment_id, $comment ) {
		$emoji_data = \get_comment_meta( $comment_id, '_activitypub_emoji', true );
		if ( empty( $emoji_data ) || ! \is_array( $emoji_data ) ) {
			return;
		}

		$content = $comment->comment_content;
		if ( empty( $content ) ) {
			\delete_comment_meta( $comment_id, '_activitypub_emoji' );
			return;
		}

		$changed = false;

		foreach ( $emoji_data as $emoji ) {
			if ( empty( $emoji['url'] ) || empty( $emoji['name'] ) ) {
				continue;
			}

			$local_url = self::import( $emoji['url'], $emoji['updated'] ?? null );
			if ( $local_url ) {
				$new_content = self::replace_emoji_in_text( $content, $emoji['name'], $local_url );
				if ( $new_content !== $content ) {
					$content = $new_content;
					$changed = true;
				}
			}
		}

		// Clear emoji meta.
		\delete_comment_meta( $comment_id, '_activitypub_emoji' );

		if ( $changed ) {
			// Unhook to prevent infinite loop.
			\remove_action( 'wp_insert_comment', array( self::class, 'replace_comment_emoji' ), 10 );

			\wp_update_comment(
				array(
					'comment_ID'      => $comment_id,
					'comment_content' => $content,
				)
			);

			// Re-hook.
			\add_action( 'wp_insert_comment', array( self::class, 'replace_comment_emoji' ), 10, 2 );
		}
	}

	/**
	 * Replace emoji placeholder in text with image tag.
	 *
	 * @param string $text        The text to process.
	 * @param string $placeholder The emoji placeholder (e.g., ":kappa:").
	 * @param string $emoji_url   The URL of the emoji image.
	 *
	 * @return string The processed text.
	 */
	private static function replace_emoji_in_text( $text, $placeholder, $emoji_url ) {
		$name = \trim( $placeholder, ':' );

		return \str_ireplace(
			$placeholder,
			\sprintf(
				'<img src="%s" alt="%s" title="%s" class="emoji" width="20" height="20" draggable="false" />',
				\esc_url( $emoji_url ),
				\esc_attr( $name ),
				\esc_attr( $name )
			),
			$text
		);
	}

	/**
	 * Maybe cache an emoji URL.
	 *
	 * Hooked to the activitypub_remote_media_url filter.
	 *
	 * @param string      $url       The remote URL.
	 * @param string      $context   The context ('avatar', 'media', 'emoji', etc.).
	 * @param string|null $entity_id The entity identifier (domain or null to extract from URL).
	 *
	 * @return string The local URL if cached successfully, otherwise the original URL.
	 */
	public static function maybe_cache( $url, $context, $entity_id = null ) {
		if ( self::CONTEXT !== $context || empty( $url ) ) {
			return $url;
		}

		// For emoji, use domain as entity_id if not provided.
		$domain = $entity_id ?: \wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $domain ) ) {
			return $url;
		}

		$cached_url = self::get_or_cache(
			$url,
			$domain,
			array( 'max_dimension' => self::MAX_DIMENSION )
		);

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
	 * For emoji, we use a filename-based approach for consistency
	 * with the original implementation, falling back to hash if needed.
	 *
	 * @param string $url The URL to hash.
	 *
	 * @return string The hash/filename string.
	 */
	protected static function generate_hash( $url ) {
		$url_path = \wp_parse_url( $url, PHP_URL_PATH );
		if ( $url_path ) {
			$file_stem = \sanitize_file_name( \pathinfo( $url_path, PATHINFO_FILENAME ) );
			if ( ! empty( $file_stem ) ) {
				return $file_stem;
			}
		}

		// Fall back to hash.
		return parent::generate_hash( $url );
	}

	/**
	 * Get a cached emoji URL if it exists.
	 *
	 * Overrides parent to use filename-based lookup for backwards compatibility.
	 *
	 * @param string     $url       The remote URL.
	 * @param string|int $entity_id The entity identifier (domain).
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

		// Get the expected filename base from the URL.
		$file_stem = self::generate_hash( $url );

		// Look for file with any extension (original or webp after optimization).
		$matches = \glob( $paths['basedir'] . '/' . $file_stem . '.*' );

		if ( ! empty( $matches ) && \is_file( $matches[0] ) ) {
			return $paths['baseurl'] . '/' . \basename( $matches[0] );
		}

		return false;
	}
}
