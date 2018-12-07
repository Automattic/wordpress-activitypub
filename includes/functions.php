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

if ( ! function_exists( 'base64_url_encode' ) ) {
	/**
	 * Encode data
	 *
	 * @param string $input input text
	 *
	 * @return string the encoded text
	 */
	function base64_url_encode( $input ) {
		return strtr( base64_encode( $input ), '+/', '-_' );
	}
}
if ( ! function_exists( 'base64_url_decode' ) ) {
	/**
	 * Dencode data
	 *
	 * @param string $input input text
	 *
	 * @return string the decoded text
	 */
	function base64_url_decode( $input ) {
		return base64_decode( strtr( $input, '-_', '+/' ) );
	}
}

function activitypub_safe_remote_post( $url, $body, $author_id ) {
	$date = gmdate( 'D, d M Y H:i:s T' );
	$signature = Activitypub_Signature::generate_signature( $author_id, $url, $date );

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

	$response = wp_safe_remote_post( $url, $args );
}
