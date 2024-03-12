<?php
$user = \get_user_by( 'id', get_the_author_meta( 'ID' ) );
$user_actor = \Activitypub\Transformer\User::transform( $user )->to_actor();

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_author_pre', $user->ID );

\header( 'Content-Type: application/activity+json' );
echo $user_actor->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_author_post', $user->ID );
