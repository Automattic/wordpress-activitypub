<?php
/**
 * Opengraph integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Model\Blog;
use Activitypub\Collection\Actors;

use function Activitypub\is_single_user;
use function Activitypub\is_user_type_disabled;

/**
 * Compatibility with the OpenGraph plugin.
 *
 * @see https://wordpress.org/plugins/opengraph/
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/XXXX/fep-XXXX.md
 * @see https://github.com/mastodon/mastodon/pull/30398
 */
class Opengraph {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		if ( ! function_exists( 'opengraph_metadata' ) ) {
			\add_action( 'wp_head', array( self::class, 'add_meta_tags' ) );
		}

		\add_filter( 'opengraph_metadata', array( self::class, 'add_metadata' ) );
	}

	/**
	 * Add the ActivityPub prefix to the OpenGraph prefixes.
	 *
	 * @param array $prefixes the current prefixes.
	 *
	 * @return array the updated prefixes.
	 */
	public static function add_prefixes( $prefixes ) {
		// @todo discuss namespace
		$prefixes['fediverse'] = 'https://codeberg.org/fediverse/fep/src/branch/main/fep/XXXX/fep-XXXX.md';

		return $prefixes;
	}

	/**
	 * Add the ActivityPub metadata to the OpenGraph metadata.
	 *
	 * @param array $metadata the current metadata.
	 *
	 * @return array the updated metadata.
	 */
	public static function add_metadata( $metadata ) {
		// Always show Blog-User if the Blog is in single user mode.
		if ( is_single_user() ) {
			$user = new Blog();

			// Add WebFinger resource.
			$metadata['fediverse:creator'] = $user->get_webfinger();

			return $metadata;
		}

		if ( \is_author() ) {
			// Use the Author of the Archive-Page.
			$user_id = \get_queried_object_id();
		} elseif ( \is_singular() ) {
			// Use the Author of the Post.
			$user_id = \get_post_field( 'post_author', \get_queried_object_id() );
		} elseif ( ! is_user_type_disabled( 'blog' ) ) {
			// Use the Blog-User for any other page, if the Blog-User is not disabled.
			$user_id = Actors::BLOG_USER_ID;
		} else {
			// Do not add any metadata otherwise.
			return $metadata;
		}

		$user = Actors::get_by_id( $user_id );

		if ( ! $user || \is_wp_error( $user ) ) {
			return $metadata;
		}

		// Add WebFinger resource.
		$metadata['fediverse:creator'] = $user->get_webfinger();

		return $metadata;
	}

	/**
	 * Output Open Graph <meta> tags in the page header.
	 */
	public static function add_meta_tags() {
		$metadata = apply_filters( 'opengraph_metadata', array() );
		foreach ( $metadata as $key => $value ) {
			if ( empty( $key ) || empty( $value ) ) {
				continue;
			}
			$value = (array) $value;

			foreach ( $value as $v ) {
				printf(
					'<meta property="%1$s" name="%1$s" content="%2$s" />' . PHP_EOL,
					esc_attr( $key ),
					esc_attr( $v )
				);
			}
		}
	}
}
