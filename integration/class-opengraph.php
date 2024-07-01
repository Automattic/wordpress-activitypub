<?php
namespace Activitypub\Integration;

use Activitypub\Model\Blog;
use Activitypub\Collection\Users;

use function Activitypub\is_single_user;
use function Activitypub\is_user_type_disabled;

/**
 * Compatibility with the OpenGraph plugin
 *
 * @see https://wordpress.org/plugins/opengraph/
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/XXXX/fep-XXXX.md
 * @see https://github.com/mastodon/mastodon/pull/30398
 */
class Opengraph {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		// \add_filter( 'opengraph_prefixes', array( self::class, 'add_opengraph_prefixes' ) );
		\add_filter( 'opengraph_metadata', array( self::class, 'add_opengraph_metadata' ) );
	}

	/**
	 * Add the ActivityPub prefix to the OpenGraph prefixes.
	 *
	 * @param array $prefixes the current prefixes.
	 *
	 * @return array the updated prefixes.
	 */
	public static function add_opengraph_prefixes( $prefixes ) {
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
	public static function add_opengraph_metadata( $metadata ) {
		// Do not add the metadata if it already exists
		if ( array_key_exists( 'fediverse:creator', $metadata ) ) {
			return $metadata;
		}

		// Always show Blog-User if the Blog is in single user mode
		if ( is_single_user() ) {
			$user = new Blog();

			// add WebFinger resource
			$metadata['fediverse:creator'] = $user->get_webfinger();

			return $metadata;
		}

		if ( \is_author() ) {
			// Use the Author of the Archive-Page
			$user_id = \get_queried_object_id();
		} elseif (
			\is_singular() &&
			\post_type_supports( get_post_type(), 'activitypub' )
		) {
			// Use the Author of the Post
			$user_id = \get_post_field( 'post_author', \get_queried_object_id() );
		} elseif ( ! is_user_type_disabled( 'blog' ) ) {
			// Use the Blog-User for any other page, if the Blog-User is not disabled
			$user_id = Users::BLOG_USER_ID;
		} else {
			// Do not add any metadata otherwise
			return $metadata;
		}

		$user = Users::get_by_id( $user_id );

		if ( ! $user || \is_wp_error( $user ) ) {
			return $metadata;
		}

		// add WebFinger resource
		$metadata['fediverse:creator'] = $user->get_webfinger();

		return $metadata;
	}
}
