<?php
$comment = \get_comment( \get_query_var( 'ap_comment_id' ) );

$activitypub_comment = new \Activitypub\Model\Comment( $comment );
$json = \array_merge( array( '@context' => \Activitypub\get_context() ), $activitypub_comment->to_array() );

// filter output
$json = \apply_filters( 'activitypub_json_comment_array', $json );

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_comment_pre' );

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
$options = \apply_filters( 'activitypub_json_comment_options', $options );

\header( 'Content-Type: application/activity+json' );
echo \wp_json_encode( $json, $options );

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_comment_comment' );
