<?php
/**
 * Tombstone JSON template.
 *
 * @package Activitypub
 */

$object = new \Activitypub\Activity\Base_Object();
$object->set_id( \Activitypub\Query::get_instance()->get_request_url() );
$object->set_type( 'Tombstone' );

/**
 * Fires before an ActivityPub object is generated and sent to the client.
 *
 * @param Activitypub\Activity\Base_Object $object The ActivityPub object.
 */
\do_action( 'activitypub_json_pre', $object );

\header( 'Content-Type: application/activity+json' );
echo $object->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires after an ActivityPub object is generated and sent to the client.
 *
 * @param Activitypub\Activity\Base_Object $object The ActivityPub object.
 */
\do_action( 'activitypub_json_post', $object );
