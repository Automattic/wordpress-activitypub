<?php
$author_id = get_the_author_meta( 'ID' );

$json = new stdClass();

$json->{'@context'} = get_activitypub_context();
$json->id = get_author_posts_url( $author_id );
$json->type = 'Person';
$json->name = get_the_author_meta( 'display_name', $author_id );
$json->summary = wp_strip_all_tags( get_the_author_meta( 'description', $author_id ) );
$json->preferredUsername = get_the_author_meta( 'login', $author_id ); // phpcs:ignore
$json->url = get_author_posts_url( $author_id );
$json->icon = array(
	'type' => 'Image',
	'url'  => get_avatar_url( $author_id, array( 'size' => 120 ) ),
);

if ( has_header_image() ) {
	$json->image = array(
		'type' => 'Image',
		'url'  => get_header_image(),
	);
} else {
	$json->image = array();
}

$json->inbox = get_rest_url( null, "/activitypub/1.0/users/$author_id/inbox" );
$json->outbox = get_rest_url( null, "/activitypub/1.0/users/$author_id/outbox" );

$json->manuallyApprovesFollowers = apply_filters( 'activitypub_json_manually_approves_followers', __return_false() ); // phpcs:ignore

if ( method_exists( 'Magic_Sig', 'get_public_key' ) ) {
	// phpcs:ignore
	$json->publicKey = array(
		'id' => get_author_posts_url( $author_id ) . '#main-key',
		'owner' => get_author_posts_url( $author_id ),
		'publicKeyPem' => trim( Magic_Sig::get_public_key( $author_id ) ),
	);
}

$json->tag = array();
$json->attachment = array();
//$json->endpoints = array(
//	'sharedInbox' => get_rest_url( null, '/activitypub/1.0/inbox' ),
//);

// filter output
$json = apply_filters( 'activitypub_json_author_array', $json );

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
do_action( 'activitypub_json_author_pre' );

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
$options = apply_filters( 'activitypub_json_author_options', $options );

header( 'Content-Type: application/activity+json' );
echo wp_json_encode( $json, $options );

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
do_action( 'activitypub_json_author_post' );
