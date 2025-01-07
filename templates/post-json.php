<?php
/**
 * ActivityPub Post JSON template.
 *
 * @package Activitypub
 */

$post        = \get_post(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$transformer = \Activitypub\Transformer\Factory::get_transformer( $post );

if ( \is_wp_error( $transformer ) ) {
	\wp_die(
		esc_html( $transformer->get_error_message() ),
		404
	);
}


/**
 * Fires before an ActivityPub post object is generated and sent to the client.
 */
\do_action( 'activitypub_json_post_pre' );

\header( 'Content-Type: application/activity+json' );
echo $transformer->to_object()->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires after an ActivityPub post object has been generated and sent to the client.
 */
\do_action( 'activitypub_json_post_post' );
