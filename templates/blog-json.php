<?php
/**
 * ActivityPub Blog JSON template.
 *
 * @package Activitypub
 */

$user = new \Activitypub\Model\Blog();

/**
 * Action triggered prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_author_pre', $user->get__id() );

\header( 'Content-Type: application/activity+json' );
echo $user->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Action triggered after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_author_post', $user->get__id() );
