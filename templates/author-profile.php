<?php
/**
 * Activity Streams 1 Feed Template for displaying AS1 Posts feed.
 *
 * @link https://github.com/pento/7B a lot of changes made by @pento
 */

$author_id = get_the_author_meta( 'ID' );

$json = new stdClass();

$json->{'@context'} = array(
	'https://www.w3.org/ns/activitystreams',
	'https://w3id.org/security/v1',
);

$json->id                = get_author_posts_url( $author_id );
$json->type              = 'Person';
$json->name              = get_the_author_meta( 'display_name', $author_id );
$json->summary           = get_the_author_meta( 'description', $author_id );
$json->preferredUsername = get_the_author(); // phpcs:ignore
$json->url               = get_author_posts_url( $author_id );
$json->icon              = array(
	'type' => 'Image',
	'url'  => get_avatar_url( $author_id, array( 'size' => 120 ) ),
);

if ( has_header_image() ) {
	$json->image = array(
		'type' => 'Image',
		'url'  => get_header_image(),
	);
}

$json->outbox = get_rest_url( null, '/activitypub/1.0/outbox' );

if ( method_exists( 'Magic_Sig', 'get_public_key' ) ) {
	// phpcs:ignore
	$json->publicKey = array(
		'id'           => get_author_posts_url( $author_id ) . '#key',
		'owner'        => get_author_posts_url( $author_id ),
		'publicKeyPem' => Magic_Sig::get_public_key( $author_id ),
	);
}

header( 'Content-Type: application/activity-json', true );

// filter output
$json = apply_filters( 'activitypub_profile_array', $json );

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
do_action( 'activitypub_profile_pre' );

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
$options = apply_filters( 'activitypub_profile_options', $options );
echo wp_json_encode( $json, $options );

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
do_action( 'activitypub_profile_post' );
