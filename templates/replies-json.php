<?php

$post = \get_post();

$page =  \get_query_var( 'page' );
$args = array(
	'status'  => 'approve',
	'number'  => '10',
    'offset'  => $page,
    'type'    => 'activitypub',
	'post_id' => $post->ID,
    'order'   => 'ASC',
);
$comments = get_comments( $args );

$replies_request = \add_query_arg( $_SERVER['QUERY_STRING'], '', $post->guid );
$collection_id = \remove_query_arg(['page', 'replytocom'], $replies_request );

$json = new \stdClass();
$json->{'@context'} = 'https://www.w3.org/ns/activitystreams';
$json->id = $collection_id;

$collectionPage = new \stdClass();
$collectionPage->type = 'CollectionPage';
$collectionPage->partOf = $collection_id;
$collectionPage->totalItems = \count( $comments ); // phpcs:ignore

if ( $page && ( ( \ceil ( $collectionPage->totalItems / 10 ) ) > $page ) ) { // phpcs:ignore
    $collectionPage->first = \add_query_arg( 'page', 1, $collectionPage->partOf ); // phpcs:ignore TODO
    $collectionPage->next  = \add_query_arg( 'page', $page + 1, $collectionPage->partOf ); // phpcs:ignore 
    $collectionPage->last  = \add_query_arg( 'page', \ceil ( $collectionPage->totalItems / 10 ), $collectionPage->partOf ); // phpcs:ignore
}

foreach ( $comments as $comment ) {
    $remote_url = \get_comment_meta( $comment->comment_ID, 'source_url', true ); 
    if ( $remote_url ) { //
        $collectionPage->items[] = $remote_url; 
    } else {
        $activitypub_comment = new \Activitypub\Model\Comment( $comment );
        $activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_NONE );
        $activitypub_activity->from_post( $activitypub_comment->to_array() );
        $collectionPage->items[] = $activitypub_activity->to_array(); // phpcs:ignore
    }
}

if ( ! \get_query_var( 'collection_page' ) ) {
    $json->type = 'Collection';
    //if +10, embed first
    $json->first = $collectionPage;
} else {
    $json = $collectionPage;
    
}

// filter output
$json = \apply_filters( 'activitypub_json_replies_array', $json );

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_replies_pre' );

$options = 0;
// JSON_PRETTY_PRINT added in PHP 5.4
if ( \get_query_var( 'pretty' ) ) {
	$options |= \JSON_PRETTY_PRINT; // phpcs:ignore
}

$options |= \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT;

/*
 * Options to be passed to json_encode()
 *
 * @param int $options The current options flags
 */
$options = \apply_filters( 'activitypub_json_replies_options', $options );

\header( 'Content-Type: application/activity+json' );
echo \wp_json_encode( $json, $options );

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_replies_comment' );
