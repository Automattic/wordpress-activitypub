<?php
/**
 * ActivityPub Outbox JSON template.
 *
 * @package Activitypub
 */

$activity = \Activitypub\Collection\Outbox::get_as_activity( \get_query_var( 'p' ) );

/**
 * Fires before an ActivityPub blog profile is generated and sent to the client.
 *
 * @param int $user_id The ID of the WordPress blog user whose profile is being generated.
 */
\do_action( 'activitypub_json_pre', $activity );

\header( 'Content-Type: application/activity+json' );

echo $activity->to_json();

/**
 * Fires after an ActivityPub blog profile has been generated and sent to the client.
 *
 * @param int $user_id The ID of the WordPress blog user whose profile was generated.
 */
\do_action( 'activitypub_json_post', $activity );
