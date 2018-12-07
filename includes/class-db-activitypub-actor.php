<?php

class Db_Activitypub_Actor {
	/**
	 * [get_inbox_by_actor description]
	 * @param  [type] $actor [description]
	 * @return [type]        [description]
	 */
	public static function get_inbox_by_actor( $actor ) {
		$metadata = self::get_metadata_by_actor( $actor );

		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}

		if ( array_key_exists( 'inbox', $metadata ) ) {
			return $metadata['inbox'];
		}

		return new WP_Error( 'activitypub_no_inbox', __( 'No "Inbox" found', 'activitypub' ), $metadata );
	}

	/**
	 * [get_metadata_by_actor description]
	 * @param  [type] $actor [description]
	 * @return [type]        [description]
	 */
	public static function get_metadata_by_actor( $actor ) {
		$metadata = get_transient( 'activitypub_' . $actor );

		if ( $metadata ) {
			return $metadata;
		}

		if ( ! wp_http_validate_url( $actor ) ) {
			return new WP_Error( 'activitypub_no_valid_actor_url', __( 'The "actor" is no valid URL', 'activitypub' ), $actor );
		}

		$wp_version = get_bloginfo( 'version' );

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 1048576,
			'redirection'         => 3,
			'user-agent'          => "$user_agent; ActivityPub",
			'headers'             => array( 'accept' => 'application/activity+json' ),
		);

		$response = wp_safe_remote_get( $actor, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$metadata = wp_remote_retrieve_body( $response );
		$metadata = json_decode( $metadata, true );

		if ( ! $metadata ) {
			return new WP_Error( 'activitypub_invalid_json', __( 'No valid JSON data', 'activitypub' ), $actor );
		}

		set_transient( 'activitypub_' . $actor, $metadata, WEEK_IN_SECONDS );

		return $metadata;
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
