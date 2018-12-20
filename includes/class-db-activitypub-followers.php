<?php

class Db_Activitypub_Followers {

	public static function get_followers( $author_id ) {
		return get_user_option( 'activitypub_followers', $author_id );
	}

	public static function add_follower( $actor, $author_id ) {
		$followers = get_user_option( 'activitypub_followers', $author_id );

		if ( ! is_array( $followers ) ) {
			$followers = array( $actor );
		} else {
			$followers[] = $actor;
		}

		$followers = array_unique( $followers );

		update_user_meta( $author_id, 'activitypub_followers', $followers );
	}

	public static function remove_follower( $actor, $author_id ) {
		$followers = get_user_option( 'activitypub_followers', $author_id );

		foreach ( $followers as $key => $value ) {
			if ( $value === $actor) {
				unset( $followers[$key] );
			}
		}

		update_user_meta( $author_id, 'activitypub_followers', $followers );
	}
}
