<?php

namespace Activitypub;

use WP_Comment_Query;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Users;
use Activitypub\Scheduler;

use function Activitypub\is_user_disabled;

/**
 * ActivityPub Application Class
 *
 * @author Django Doucet
 */
class Application {

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

	public static function known_commenters() {
		// at this point we just need known_commenters
		// this could get expensive though, eventually it would make sense
		// to schedule an add comment_author_url on Follow to a known_users site option
		$args = array(
			'user_id' => 0,
			'meta_query' => array(
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
					'compare' => '=',
				),
			),
		);
		$comment_query = new WP_Comment_Query( $args );
		$known_commenters_all = [];
		foreach ( $comment_query->comments as $user ) {
			$known_commenters_all[] = $user->comment_author_url;
		}
		return array_unique( array_filter( $known_commenters_all ) );
	}

	public static function is_actor_delete_request( $request ) {
		$json = $request->get_params( 'JSON' );
		if ( 'delete' === $json['type'] && $json['actor'] === $json['object'] ) {
			return true;
		}
		return false;
	}

	public static function is_known_commenter( $request ) {
		$json = $request->get_params( 'JSON' );
		if ( in_array( $json['actor'], self::known_commenters() ) ) {
			return $json['actor'];
		}
		return false;
	}
}
