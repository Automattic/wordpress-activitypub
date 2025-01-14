<?php
/**
 * ActivityPub Comment JSON template.
 *
 * @package Activitypub
 */

$comment     = \get_comment( \get_query_var( 'c', null ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$transformer = \Activitypub\Transformer\Factory::get_transformer( $comment );

if ( \is_wp_error( $transformer ) ) {
	\wp_die(
		\esc_html( $transformer->get_error_message() ),
		404
	);
}

/**
 * Fires before an ActivityPub comment object is generated and sent to the client.
 */
\do_action( 'activitypub_json_comment_pre' );

\header( 'Content-Type: application/activity+json' );
echo $transformer->to_object()->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires after an ActivityPub comment object has been generated and sent to the client.
 */
\do_action( 'activitypub_json_comment_post' );
