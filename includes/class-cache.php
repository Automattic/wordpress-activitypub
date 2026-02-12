<?php
/**
 * Cache class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Cache\Avatar;
use Activitypub\Cache\Emoji;
use Activitypub\Cache\Media;

/**
 * Cache orchestrator class.
 *
 * Manages registration and initialization of remote media cache handlers.
 * Each cache type (Avatar, Media, Emoji) handles specific remote media caching
 * needs for ActivityPub content.
 *
 * Cache types can be disabled globally via constant or filter, or individually
 * via type-specific filters.
 *
 * @since 5.6.0
 */
class Cache {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		self::register_caches();
	}

	/**
	 * Check if remote caching is enabled globally.
	 *
	 * @return bool True if caching is enabled, false otherwise.
	 */
	public static function is_enabled() {
		// Check constant first.
		if ( ACTIVITYPUB_DISABLE_MEDIA_CACHE ) {
			return false;
		}

		/**
		 * Filters whether remote media caching is enabled.
		 *
		 * @since 5.6.0
		 *
		 * @param bool $enabled Whether caching is enabled. Default true.
		 */
		return (bool) \apply_filters( 'activitypub_remote_cache_enabled', true );
	}

	/**
	 * Register all cache handlers.
	 */
	public static function register_caches() {
		Avatar::init();
		Media::init();
		Emoji::init();

		/**
		 * Fires after all built-in cache handlers are registered.
		 *
		 * Use this hook to register additional cache handlers.
		 *
		 * @since 5.6.0
		 */
		\do_action( 'activitypub_register_caches' );
	}
}
