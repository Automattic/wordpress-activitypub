<?php

namespace Activitypub;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Users;

use function Activitypub\is_user_disabled;

/**
 * ActivityPub Server Class
 *
 * @author Django Doucet
 */
class Server {

	public static function known_inboxes() {
		$authors = get_users(
			array(
				'capability' => 'publish_posts',
			)
		);
		$follower_inboxes_all = [];
		foreach ( $authors as $user ) {
			$follower_inboxes = Followers::get_inboxes( $user->ID );
			$follower_inboxes_all = array_merge( $follower_inboxes, $follower_inboxes_all );
		}
		if ( ! is_user_disabled( Users::BLOG_USER_ID ) ) {
			$follower_inboxes = Followers::get_inboxes( Users::BLOG_USER_ID );
			$follower_inboxes_all = array_merge( $follower_inboxes, $follower_inboxes_all );
		}
		return array_unique( array_filter( $follower_inboxes_all ) );
	}
}
