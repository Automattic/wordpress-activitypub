<?php
$user = new \Activitypub\Model\Blog_User();

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_author_pre', $user->get__id() );

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
$options = \apply_filters( 'activitypub_json_author_options', $options, $user->get__id() );

\header( 'Content-Type: application/activity+json' );
echo \wp_json_encode( $user->to_array(), $options );

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_author_post', $user->get__id() );
