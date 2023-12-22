<?php
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$post = \get_post();

$post_object = \Activitypub\Transformer\Factory::get_transformer( $post )->to_object();
$post_object->set_context( \Activitypub\get_context() );

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_post_pre' );

\header( 'Content-Type: application/activity+json' );
echo $post_object->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_post_post' );
