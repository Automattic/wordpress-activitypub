<?php
/**
 * Returns the ActivityPub default JSON-context
 *
 * @return array the activitypub context
 */
function get_activitypub_context() {
	$context = array(
		'https://www.w3.org/ns/activitystreams',
		'https://w3id.org/security/v1',
		array(
			'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
			'sensitive' => 'as:sensitive',
			'movedTo' => array(
				'@id' => 'as:movedTo',
				'@type' => '@id',
			),
			'Hashtag' => 'as:Hashtag',
			'ostatus' => 'http://ostatus.org#',
			'atomUri' => 'ostatus:atomUri',
			'inReplyToAtomUri' => 'ostatus:inReplyToAtomUri',
			'conversation' => 'ostatus:conversation',
			'toot' => 'http://joinmastodon.org/ns#',
			'Emoji' => 'toot:Emoji',
			'focalPoint' => array(
				'@container' => '@list',
				'@id' => 'toot:focalPoint',
			),
			'featured' => array(
				'@id' => 'toot:featured',
				'@type' => '@id',
			),
			'schema' => 'http://schema.org#',
			'PropertyValue' => 'schema:PropertyValue',
			'value' => 'schema:value',
		),
	);

	return apply_filters( 'activitypub_json_context', $context );
}

function activitypub_safe_remote_post( $url, $body, $user_id ) {
	$date = gmdate( 'D, d M Y H:i:s T' );
	$signature = Activitypub_Signature::generate_signature( $user_id, $url, $date );

	$wp_version = get_bloginfo( 'version' );
	$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
	$args = array(
		'timeout' => 100,
		'limit_response_size' => 1048576,
		'redirection' => 3,
		'user-agent' => "$user_agent; ActivityPub",
		'headers' => array(
			'Accept' => 'application/activity+json',
			'Content-Type' => 'application/activity+json',
			'Signature' => $signature,
			'Date' => $date,
		),
		'body' => $body,
	);

	return wp_safe_remote_post( $url, $args );
}

/**
 * Returns a users WebFinger "resource"
 *
 * @param int $user_id
 *
 * @return string The user-resource
 */
function activitypub_get_webfinger_resource( $user_id ) {
	// use WebFinger plugin if installed
	if ( function_exists( 'get_webfinger_resource' ) ) {
		return get_webfinger_resource( $user_id, false );
	}

	$user = get_user_by( 'id', $user_id );

	return $user->user_login . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
}

/**
 * [get_metadata_by_actor description]
 *
 * @param  [type] $actor [description]
 * @return [type]        [description]
 */
function activitypub_get_remote_metadata_by_actor( $actor ) {
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

/**
 * [get_inbox_by_actor description]
 * @param  [type] $actor [description]
 * @return [type]        [description]
 */
function activitypub_get_inbox_by_actor( $actor ) {
	$metadata = activitypub_get_remote_metadata_by_actor( $actor );

	if ( is_wp_error( $metadata ) ) {
		return $metadata;
	}

	if ( isset( $metadata['endpoints'] ) && isset( $metadata['endpoints']['sharedInbox'] ) ) {
		return $metadata['endpoints']['sharedInbox'];
	}

	if ( array_key_exists( 'inbox', $metadata ) ) {
		return $metadata['inbox'];
	}

	return new WP_Error( 'activitypub_no_inbox', __( 'No "Inbox" found', 'activitypub' ), $metadata );
}

/**
 * [get_inbox_by_actor description]
 * @param  [type] $actor [description]
 * @return [type]        [description]
 */
function activitypub_get_publickey_by_actor( $actor, $key_id ) {
	$metadata = activitypub_get_remote_metadata_by_actor( $actor );

	if ( is_wp_error( $metadata ) ) {
		return $metadata;
	}

	if (
		isset( $metadata['publicKey'] ) &&
		isset( $metadata['publicKey']['id'] ) &&
		isset( $metadata['publicKey']['owner'] ) &&
		isset( $metadata['publicKey']['publicKeyPem'] ) &&
		$key_id === $metadata['publicKey']['id'] &&
		$actor === $metadata['publicKey']['owner']
	) {
		return $metadata['publicKey']['publicKeyPem'];
	}

	return new WP_Error( 'activitypub_no_public_key', __( 'No "Public-Key" found', 'activitypub' ), $metadata );
}

function activitypub_get_follower_inboxes( $user_id, $followers ) {
	$inboxes = array();
	foreach ( $followers as $follower ) {
		$inboxes[] = activitypub_get_inbox_by_actor( $follower );
	}

	return array_unique( $inboxes );
}

/**
 * Get the excerpt for a post for use outside of the loop.
 *
 * @param int|WP_Post $post ID or WP_Post object.
 * @param int         Optional excerpt length.
 *
 * @return string     The excerpt.
 */
function activitypub_get_the_excerpt( $post, $excerpt_length = 55 ) {

	$excerpt = get_post_field( 'post_excerpt', $post );

	if ( '' === $excerpt ) {

		$content = get_post_field( 'post_content', $post );

		// An empty string will make wp_trim_excerpt do stuff we do not want.
		if ( '' !== $content ) {

			$excerpt = strip_shortcodes( $content );

			/** This filter is documented in wp-includes/post-template.php */
			$excerpt = apply_filters( 'the_content', $excerpt );
			$excerpt = str_replace( ']]>', ']]>', $excerpt );

			$excerpt_length = apply_filters( 'excerpt_length', $excerpt_length );

			/** This filter is documented in wp-includes/formatting.php */
			$excerpt_more = apply_filters( 'excerpt_more', ' […]' );

			$excerpt = wp_trim_words( $excerpt, $excerpt_length, $excerpt_more );
		}
	}

	return apply_filters( 'the_excerpt', $excerpt );
}
