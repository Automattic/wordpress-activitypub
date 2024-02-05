<?php
namespace Activitypub;

/**
 * ActivityPub (Account) Move Class
 *
 * @author Matthias Pfefferle
 */
class Move {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'activitypub_activity_user_object_array', array( self::class, 'extend_actor_profiles' ), 10, 3 );
	}

	/**
	 * Extend the actor profiles and add the "movedTo" and "alsoKnownAs" properties
	 *
	 * @param array $actor the actor profile
	 *
	 * @return array the extended actor profile
	 */
	public static function extend_actor_profiles( $actor, $id, $user ) {
		// Check if the user is a valid user object
		if ( ! $user instanceof \Activitypub\Model\User ) {
			return $actor;
		}

		$move_to_host = apply_filters( 'activitypub_move_actor_to_host', false, $id, $user );

		if ( $move_to_host && is_string( $move_to_host ) ) {
			$actor['movedTo'] = self::normalize_host( $move_to_host, $user->get_id() );
		}

		$move_from_host = apply_filters( 'activitypub_move_actor_from_host', false, $id, $user );

		if ( $move_from_host && is_array( $move_from_host ) ) {
			$actor['alsoKnownAs'] = self::normalize_hosts( $move_from_host, $user->get_id() );
		}

		return $actor;
	}

	/**
	 * Add settings to the admin interface
	 *
	 * @return void
	 */
	public static function add_settings() {

	}

	/**
	 * Normalize the host
	 *
	 * Returns the host if it is a valid URL, otherwise it tries to replace
	 * the host of the Actor-ID with the new host
	 *
	 * @param string $host_or_url the host or the url
	 * @param string $id          the Actor-ID (URL)
	 *
	 * @return string the normalized host
	 */
	public static function normalize_host( $host_or_url, $id ) {
		// if it is a valid URL use it
		if ( filter_var( $host_or_url, FILTER_VALIDATE_URL ) ) {
			return $host_or_url;
		}

		// otherwise try to replace the host of the Actor-ID with the new host
		$id = str_replace( wp_parse_url( get_home_url(), PHP_URL_HOST ), $host_or_url, $id );

		return $id;
	}

	/**
	 * Normalize the hosts
	 *
	 * Returns an array of normalized hosts
	 *
	 * @param string $hosts_or_urls the host or the url
	 * @param string $id            the Actor-ID (URL)
	 *
	 * @return array the normalized hosts
	 */
	public static function normalize_hosts( $hosts_or_urls, $id ) {
		$normalized_hosts = array();

		foreach ( $hosts_or_urls as $host_or_url ) {
			$normalized_hosts[] = self::normalize_host( $host_or_url, $id );
		}

		return $normalized_hosts;
	}
}
