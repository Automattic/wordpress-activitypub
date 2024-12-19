<?php
/**
 * ActivityPub User JSON template.
 *
 * @package Activitypub
 */

$user = \Activitypub\Collection\Actors::get_by_id( \get_the_author_meta( 'ID' ) );

/**
 * Fires before an ActivityPub user profile is generated and sent to the client.
 *
 * @param int $user_id The ID of the WordPress user whose profile is being generated.
 */
\do_action( 'activitypub_json_author_pre', $user->get__id() );

\header( 'Content-Type: application/activity+json' );
echo $user->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires after an ActivityPub user profile has been generated and sent to the client.
 *
 * @param int $user_id The ID of the WordPress user whose profile was generated.
 */
\do_action( 'activitypub_json_author_post', $user->get__id() );
