<?php
$post = get_post();

$json = new stdClass();

$json->{'@context'} = array(
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

$post = activitypub_post_to_json( $post, $json );

// filter output
$json = apply_filters( 'activitypub_json_post_array', $json );

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
do_action( 'activitypub_json_post_pre' );

$options = 0;
// JSON_PRETTY_PRINT added in PHP 5.4
if ( get_query_var( 'pretty' ) ) {
	$options |= JSON_PRETTY_PRINT; // phpcs:ignore
}

/*
 * Options to be passed to json_encode()
 *
 * @param int $options The current options flags
 */
$options = apply_filters( 'activitypub_json_post_options', $options );

header( 'Content-Type: application/activity+json' );
echo wp_json_encode( $json, $options );

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
do_action( 'activitypub_json_post_post' );
