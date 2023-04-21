<?php
namespace Activitypub\Peer;

/**
 * ActivityPub Followers DB-Class
 *
 * @author Matthias Pfefferle
 */
class Followers {

	public static function get_followers( $author_id ) {
		_deprecated_function( __METHOD__, '1.0.0', '\Activitypub\Collection\Followers::get_followers' );

		$items = array(); // phpcs:ignore
		foreach ( \Activitypub\Collection\Followers::get_followers( $author_id ) as $follower ) {
			$items[] = $follower->name; // phpcs:ignore
		}
		return $items;
	}

	public static function count_followers( $author_id ) {
		_deprecated_function( __METHOD__, '1.0.0', '\Activitypub\Collection\Followers::count_followers' );

		return \Activitypub\Collection\Followers::count_followers( $author_id );
	}

	public static function add_follower( $actor, $author_id ) {
		_deprecated_function( __METHOD__, '1.0.0', '\Activitypub\Collection\Followers::add_follower' );

		return \Activitypub\Collection\Followers::add_followers( $author_id, $actor );
	}

	public static function remove_follower( $actor, $author_id ) {
		_deprecated_function( __METHOD__, '1.0.0', '\Activitypub\Collection\Followers::remove_follower' );

		return \Activitypub\Collection\Followers::remove_follower( $author_id, $actor );
	}
}
