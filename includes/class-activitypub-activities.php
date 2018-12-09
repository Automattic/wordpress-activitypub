<?php

class Activitypub_Activities {

	/**
	 * [accept description]
	 * @param  [type] $data      [description]
	 * @param  [type] $author_id [description]
	 * @return [type]            [description]
	 */
	public static function accept( $data, $author_id ) {
		if ( ! array_key_exists( 'actor', $data ) ) {
			return new WP_Error( 'activitypub_no_actor', __( 'No "Actor" found', 'activitypub' ), $metadata );
		}

		$inbox = Db_Activitypub_Followers::get_inbox_by_actor( $data['actor'] );

		$activity = wp_json_encode(
			array(
				'@context' => array( 'https://www.w3.org/ns/activitystreams' ),
				'type' => 'Accept',
				'actor' => get_author_posts_url( $author_id ),
				'object' => $data,
				'to' => $data['actor'],
			)
		);

		return activitypub_safe_remote_post( $inbox, $activity, $author_id );
	}

	/**
	 * [follow description]
	 * @param  [type] $data      [description]
	 * @param  [type] $author_id [description]
	 * @return [type]            [description]
	 */
	public static function follow( $data, $author_id ) {
		if ( ! array_key_exists( 'actor', $data ) ) {
			return new WP_Error( 'activitypub_no_actor', __( 'No "Actor" found', 'activitypub' ), $metadata );
		}

		Db_Activitypub_Followers::add_follower( $data['actor'], $author_id );
	}

	/**
	 * [unfollow description]
	 * @param  [type] $data      [description]
	 * @param  [type] $author_id [description]
	 * @return [type]            [description]
	 */
	public static function unfollow( $data, $author_id ) {

	}

	/**
	 * [create description]
	 * @param  [type] $data      [description]
	 * @param  [type] $author_id [description]
	 * @return [type]            [description]
	 */
	public static function create( $data, $author_id ) {

	}
}
