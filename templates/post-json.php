// SPDX-FileCopyrightText: 2022 Matthias Pfefferly <matthias@pfefferle.org>
//
// SPDX-License-Identifier: MIT

<?php
$post = \get_post();

$activitypub_post = new \Activitypub\Model\Post( $post );
$json = \array_merge( array( '@context' => \Activitypub\get_context() ), $activitypub_post->to_array() );

// filter output
$json = \apply_filters( 'activitypub_json_post_array', $json );

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_post_pre' );

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
$options = \apply_filters( 'activitypub_json_post_options', $options );

\header( 'Content-Type: application/activity+json' );
echo \wp_json_encode( $json, $options );

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_post_post' );
