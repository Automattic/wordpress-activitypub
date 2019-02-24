<?php
namespace Activitypub;

/**
 * Returns the ActivityPub default JSON-context
 *
 * @return array the activitypub context
 */
function get_context() {
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

function safe_remote_post( $url, $body, $user_id ) {
	$date = gmdate( 'D, d M Y H:i:s T' );
	$signature = \Activitypub\Signature::generate_signature( $user_id, $url, $date );

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
function get_webfinger_resource( $user_id ) {
	// use WebFinger plugin if installed
	if ( function_exists( '\get_webfinger_resource' ) ) {
		return \get_webfinger_resource( $user_id, false );
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
function get_remote_metadata_by_actor( $actor ) {
	$metadata = get_transient( 'activitypub_' . $actor );

	if ( $metadata ) {
		return $metadata;
	}

	if ( ! wp_http_validate_url( $actor ) ) {
		return new \WP_Error( 'activitypub_no_valid_actor_url', __( 'The "actor" is no valid URL', 'activitypub' ), $actor );
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
		return new \WP_Error( 'activitypub_invalid_json', __( 'No valid JSON data', 'activitypub' ), $actor );
	}

	set_transient( 'activitypub_' . $actor, $metadata, WEEK_IN_SECONDS );

	return $metadata;
}

/**
 * [get_inbox_by_actor description]
 * @param  [type] $actor [description]
 * @return [type]        [description]
 */
function get_inbox_by_actor( $actor ) {
	$metadata = \Activitypub\get_remote_metadata_by_actor( $actor );

	if ( is_wp_error( $metadata ) ) {
		return $metadata;
	}

	if ( isset( $metadata['endpoints'] ) && isset( $metadata['endpoints']['sharedInbox'] ) ) {
		return $metadata['endpoints']['sharedInbox'];
	}

	if ( array_key_exists( 'inbox', $metadata ) ) {
		return $metadata['inbox'];
	}

	return new \WP_Error( 'activitypub_no_inbox', __( 'No "Inbox" found', 'activitypub' ), $metadata );
}

/**
 * [get_inbox_by_actor description]
 * @param  [type] $actor [description]
 * @return [type]        [description]
 */
function get_publickey_by_actor( $actor, $key_id ) {
	$metadata = \Activitypub\get_remote_metadata_by_actor( $actor );

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

	return new \WP_Error( 'activitypub_no_public_key', __( 'No "Public-Key" found', 'activitypub' ), $metadata );
}

function get_follower_inboxes( $user_id, $followers ) {
	$inboxes = array();
	foreach ( $followers as $follower ) {
		$inboxes[] = \Activitypub\get_inbox_by_actor( $follower );
	}

	return array_unique( $inboxes );
}

function get_identifier_settings( $user_id ) {
	?>
<table class="form-table">
	<tbody>
		<tr>
			<th scope="row">
				<label><?php esc_html_e( 'Profile identifier', 'activitypub' ); ?></label>
			</th>
			<td>
				<p><code><?php echo \Activitypub\get_webfinger_resource( $user_id ); ?></code> or <code><?php echo get_author_posts_url( $user_id ); ?></code></p>
				<p class="description"><?php printf( __( 'Try to follow "@%s" in the mastodon/friendi.ca search field.', 'activitypub' ), \Activitypub\get_webfinger_resource( $user_id ) ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
	<?php
}

function get_followers( $user_id ) {
	$followers = \Activitypub\Db\Followers::get_followers( $user_id );

	if ( ! $followers ) {
		return array();
	}

	return $followers;
}

function count_followers( $user_id ) {
	$followers = \Activitypub\get_followers( $user_id );

	return count( $followers );
}
