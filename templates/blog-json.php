<?php
/**
 * ActivityPub Blog JSON template.
 *
 * @package Activitypub
 */

$user = new \Activitypub\Model\Blog();

/**
 * Fires before an ActivityPub blog profile is generated and sent to the client.
 *
 * @param int $user_id The ID of the WordPress blog user whose profile is being generated.
 */
\do_action( 'activitypub_json_author_pre', $user->get__id() );

\header( 'Content-Type: application/activity+json' );
echo $user->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires after an ActivityPub blog profile has been generated and sent to the client.
 *
 * @param int $user_id The ID of the WordPress blog user whose profile was generated.
 */
\do_action( 'activitypub_json_author_post', $user->get__id() );
