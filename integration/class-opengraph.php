<?php
namespace Activitypub\Integration;

use Activitypub\Collection\Users;

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
		\add_filter( 'opengraph_prefixes', array( self::class, 'add_opengraph_prefixes' ) );
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
		if ( \is_author() ) {
			$user_id = \get_queried_object_id();
		} elseif (
			\is_singular() &&
			\post_type_supports( get_post_type(), 'activitypub' )
		) {
			$user_id = \get_the_author_meta( 'ID' );
		} else {
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
