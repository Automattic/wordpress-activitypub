<?php
/**
 * Stream integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration\Stream;

/**
 * Stream integration.
 *
 * This class handles the compatibility with the Stream plugin.
 *
 * @see https://wordpress.org/plugins/stream/
 */
class Stream {
	/**
	 * Initialize the Stream integration.
	 */
	public static function init() {
		\add_filter( 'wp_stream_connectors', array( self::class, 'register_connector' ) );
		\add_filter( 'wp_stream_posts_exclude_post_types', array( self::class, 'exclude_post_types' ) );
	}

	/**
	 * Register the Stream Connector for ActivityPub.
	 *
	 * @param array $classes The Stream connectors.
	 *
	 * @return array The Stream connectors with the ActivityPub connector.
	 */
	public static function register_connector( $classes ) {
		$class = new Connector();

		if ( \method_exists( $class, 'is_dependency_satisfied' ) && $class->is_dependency_satisfied() ) {
			$classes[] = $class;
		}

		return $classes;
	}

	/**
	 * Exclude ActivityPub post types from the Stream.
	 *
	 * @param array $post_types The post types to exclude.
	 *
	 * @return array The post types to exclude with ActivityPub post types.
	 */
	public static function exclude_post_types( $post_types ) {
		$post_types[] = 'ap_actor';
		$post_types[] = 'ap_extrafield';
		$post_types[] = 'ap_extrafield_blog';
		$post_types[] = 'ap_post';

		return $post_types;
	}
}
