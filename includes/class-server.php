<?php

namespace Activitypub;

/**
 * ActivityPub Server Class
 *
 * @author Django Doucet
 */
class Server {

	private static function known_inboxes () {
		$authors = get_users( array(
			'capability' => 'publish_posts'
		) );
		$follower_inboxes_all = [];
		foreach ( $authors as $user ) {
			$follower_inboxes = Followers::get_inboxes( $user->ID );
			$follower_inboxes_all = array_merge( $follower_inboxes, $follower_inboxes_all );
		}
		return array_unique( array_filter( $follower_inboxes_all ) );
	}

}
