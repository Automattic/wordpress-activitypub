<?php
$comment     = \get_comment( \get_query_var( 'c', null ) ); // phpcs:ignore
$transformer = \Activitypub\Transformer\Factory::get_transformer( $comment );

if ( \is_wp_error( $transformer ) ) {
	\wp_die(
		\esc_html( $transformer->get_error_message() ),
		404
	);
}

/*
 * Action triggerd prior to the ActivityPub profile being created and sent to the client
 */
\do_action( 'activitypub_json_comment_pre' );

\header( 'Content-Type: application/activity+json' );
echo $transformer->to_object()->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/*
 * Action triggerd after the ActivityPub profile has been created and sent to the client
 */
\do_action( 'activitypub_json_comment_post' );
