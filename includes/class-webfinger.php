<?php
namespace Activitypub;

/**
 * ActivityPub WebFinger Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Returns a users WebFinger "resource"
	 *
	 * @param int $user_id
	 *
	 * @return string The user-resource
	 */
	public static function get_user_resource( $user_id ) {
		// use WebFinger plugin if installed
		if ( \function_exists( '\get_webfinger_resource' ) ) {
			return \get_webfinger_resource( $user_id, false );
		}

		$user = \get_user_by( 'id', $user_id );

		return $user->user_login . '@' . \wp_parse_url( \home_url(), \PHP_URL_HOST );
	}
}
