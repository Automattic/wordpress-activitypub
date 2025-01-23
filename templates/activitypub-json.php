<?php
/**
 * ActivityPub Blog JSON template.
 *
 * @package Activitypub
 */

$object = \Activitypub\Query::get_instance()->get_activitypub_object();

/**
 * Fires before an ActivityPub blog profile is generated and sent to the client.
 *
 * @param int $user_id The ID of the WordPress blog user whose profile is being generated.
 */
\do_action( 'activitypub_json_pre', $object );

\header( 'Content-Type: application/activity+json' );
echo $object->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires after an ActivityPub blog profile has been generated and sent to the client.
 *
 * @param int $user_id The ID of the WordPress blog user whose profile was generated.
 */
\do_action( 'activitypub_json_post', $object );
