<?php
namespace Activitypub\Db;

class Followers {

	public static function get_followers( $author_id ) {
		$followers = get_user_option( 'activitypub_followers', $author_id );

		if ( ! $followers ) {
			return $followers;
		}

		foreach ( $followers as $key => $follower ) {
			if (
				is_array( $follower ) &&
				isset( $follower['type'] ) &&
				'Person' === $follower['type'] &&
				isset( $follower['id'] ) &&
				false !== filter_var( $follower['id'], FILTER_VALIDATE_URL )
			) {
				$followers[ $key ] = $follower['id'];
			}
		}

		return $followers;
	}

	public static function add_follower( $actor, $author_id ) {
		$followers = get_user_option( 'activitypub_followers', $author_id );

		if ( ! is_string( $actor ) ) {
			if (
				is_array( $actor ) &&
				isset( $actor['type'] ) &&
				'Person' === $actor['type'] &&
				isset( $actor['id'] ) &&
				false !== filter_var( $actor['id'], FILTER_VALIDATE_URL )
			) {
				$actor = $actor['id'];
			}

			return new \WP_Error( 'invalid_actor_object', __( 'Unknown Actor schema', 'activitypub' ), array(
				'status' => 404,
			) );
		}

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
			if ( $value === $actor ) {
				unset( $followers[ $key ] );
			}
		}

		update_user_meta( $author_id, 'activitypub_followers', $followers );
	}
}
