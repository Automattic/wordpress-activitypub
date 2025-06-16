<?php
/**
 * ActivityPub JSON template.
 *
 * @package Activitypub
 */

$object = \Activitypub\Query::get_instance()->get_activitypub_object();

/**
 * Fires before an ActivityPub object is generated and sent to the client.
 *
 * @param object $object The ActivityPub object.
 */
\do_action( 'activitypub_json_pre', $object );

\header( 'Content-Type: application/activity+json' );
echo $object->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires after an ActivityPub object is generated and sent to the client.
 *
 * @param object $object The ActivityPub object.
 */
\do_action( 'activitypub_json_post', $object );
