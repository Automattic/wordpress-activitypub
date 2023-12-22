<?php
$user = new \Activitypub\Model\Blog_User();

$user->set_context(
	\Activitypub\Activity\Activity::CONTEXT
);

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_author_pre', $user->get__id() );

\header( 'Content-Type: application/activity+json' );
echo $user->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_author_post', $user->get__id() );
