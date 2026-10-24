<?php
/**
 * Class file for the Remote Posts Collection Registry.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Remote Posts Collection Registry.
 *
 * Maps an ActivityPub object type (for example `Note` or `Event`) to the collection class that
 * handles it. Collections are static-only classes, so the registry stores class names.
 *
 * Registering also flags the collection's post type with {@see Remote_Posts::SUPPORT_FEATURE},
 * so the registry and {@see Remote_Posts::get_supporting_post_types()} cannot disagree.
 *
 * Object types without a registered collection are handled by the default: {@see Default_Remote_Posts}}
 *
 * @since unreleased
 */
final class Remote_Posts_Collection_Registry {
	/**
	 * Registered custom collection classes, keyed by ActivityPub object type.
	 *
	 * @var array<string, class-string<Remote_Posts>>
	 */
	private static $collections = array();

	/**
	 * Post types this registry flagged itself.
	 *
	 * Tracked so `reset()` only removes flags it added, not flags set via `register_post_type()`.
	 *
	 * @var string[]
	 */
	private static $flagged_post_types = array();

	/**
	 * Not instantiable.
	 */
	private function __construct() {}

	/**
	 * Register a collection for an ActivityPub object type.
	 *
	 * If the object type already has a collection,
	 * the new one is ignored. Several object types may share one collection.
	 *
	 * @param class-string<Remote_Posts> $collection  The collection class name, a subclass of Remote_Posts.
	 *
	 * @return bool True if registered, false if the object type was already registered or the
	 *              arguments are invalid.
	 */
	public static function register( $collection ) {
		$object_type = $collection::get_activitypub_object_type();

		if ( '' === $object_type || isset( self::$collections[ $object_type ] ) ) {
			return false;
		}

		if ( ! \is_subclass_of( $collection, Remote_Posts::class ) ) {
			\_doing_it_wrong( __METHOD__, 'The collection must be the name of a Remote_Posts subclass.', 'unreleased' );
			return false;
		}

		$post_type = $collection::get_post_type();

		if ( ! \is_string( $post_type ) || '' === $post_type ) {
			\_doing_it_wrong( __METHOD__, 'The collection must return a post type.', 'unreleased' );
			return false;
		}

		self::$collections[ $object_type ] = $collection;

		// Only writes to a global array, so this is safe before the post type is registered.
		\add_post_type_support( $post_type, Remote_Posts::SUPPORT_FEATURE );

		if ( $collection::is_exclusive() ) {
			\add_post_type_support( $post_type, Remote_Posts::SUPPORT_EXCLUSIVE );
		}

		return true;
	}

	/**
	 * Get the collection class that handles an object type.
	 *
	 * @param string $object_type The ActivityPub object type.
	 *
	 * @return class-string<Remote_Posts> The collection class name, the default one if none was registered.
	 */
	public static function get( $object_type ) {
		if ( ! \is_string( $object_type ) ) {
			return Default_Remote_Posts::class;
		}

		return self::$collections[ $object_type ] ?? Default_Remote_Posts::class;
	}

	/**
	 * Reset the registry, including the support flags it added. Intended for tests.
	 *
	 * @return void
	 */
	public static function reset() {
		foreach ( self::$flagged_post_types as $post_type ) {
			\remove_post_type_support( $post_type, Remote_Posts::SUPPORT_FEATURE );
		}

		self::$collections        = array();
		self::$flagged_post_types = array();
	}
}
