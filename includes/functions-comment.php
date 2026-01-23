<?php
/**
 * Comment functions.
 *
 * Functions for working with comments in ActivityPub context.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Detect a comment request.
 *
 * @deprecated 7.1.0
 *
 * @return int|bool Comment ID or false if not found.
 */
function is_comment() {
	\_deprecated_function( __FUNCTION__, '7.1.0' );

	$comment_id = get_query_var( 'c', null );

	if ( ! is_null( $comment_id ) ) {
		$comment = \get_comment( $comment_id );

		if ( $comment ) {
			return $comment_id;
		}
	}

	return false;
}

/**
 * Get the comment from an ActivityPub Object ID.
 *
 * @param string $id ActivityPub object ID (usually a URL) to check.
 *
 * @return \WP_Comment|boolean Comment, or false on failure.
 */
function object_id_to_comment( $id ) {
	return Comment::object_id_to_comment( $id );
}

/**
 * Verify that URL is a local comment or a previously received remote comment.
 * (For threading comments locally)
 *
 * @param string $url The URL to check.
 *
 * @return string|null Comment ID or null if not found
 */
function url_to_commentid( $url ) {
	return Comment::url_to_commentid( $url );
}

/**
 * Check if a comment should be federated.
 *
 * We consider a comment should be federated if it is authored by a user that is
 * not disabled for federation and if it is a reply directly to the post or to a
 * federated comment.
 *
 * @param mixed $comment Comment object or ID.
 *
 * @return boolean True if the comment should be federated, false otherwise.
 */
function should_comment_be_federated( $comment ) {
	return Comment::should_be_federated( $comment );
}

/**
 * Check if a comment was federated.
 *
 * This function checks if a comment was federated via ActivityPub.
 *
 * @param mixed $comment Comment object or ID.
 *
 * @return boolean True if the comment was federated, false otherwise.
 */
function was_comment_sent( $comment ) {
	return Comment::was_sent( $comment );
}

/**
 * Check if a comment is federated.
 *
 * We consider a comment federated if comment was received via ActivityPub.
 *
 * Use this function to check if it is comment that was received via ActivityPub.
 *
 * @param mixed $comment Comment object or ID.
 *
 * @return boolean True if the comment is federated, false otherwise.
 */
function was_comment_received( $comment ) {
	return Comment::was_received( $comment );
}

/**
 * Check if a comment is local only.
 *
 * This function checks if a comment is local only and was not sent or received via ActivityPub.
 *
 * @param mixed $comment Comment object or ID.
 *
 * @return boolean True if the comment is local only, false otherwise.
 */
function is_local_comment( $comment ) {
	return Comment::is_local( $comment );
}

/**
 * Retrieves the IDs of the ancestors of a comment.
 *
 * Adaption of `get_post_ancestors` from WordPress core.
 *
 * @see https://developer.wordpress.org/reference/functions/get_post_ancestors/
 *
 * @param int|\WP_Comment $comment Comment ID or comment object.
 *
 * @return int[] Array of ancestor IDs.
 */
function get_comment_ancestors( $comment ) {
	$comment = \get_comment( $comment );

	if ( ! $comment || empty( $comment->comment_parent ) || (int) $comment->comment_parent === (int) $comment->comment_ID ) {
		return array();
	}

	$ancestors = array();

	$id          = (int) $comment->comment_parent;
	$ancestors[] = $id;

	while ( $id > 0 ) {
		$ancestor = \get_comment( $id );

		if ( ! $ancestor ) {
			break;
		}

		$parent_id = (int) $ancestor->comment_parent;

		// Loop detection: If the ancestor has been seen before, break.
		if ( empty( $parent_id ) || ( $parent_id === (int) $comment->comment_ID ) || in_array( $parent_id, $ancestors, true ) ) {
			break;
		}

		$id          = $parent_id;
		$ancestors[] = $id;
	}

	return $ancestors;
}

/**
 * Registers a ActivityPub comment type.
 *
 * @param string $comment_type Key for comment type.
 * @param array  $args         Optional. Array of arguments for registering a comment type. Default empty array.
 *
 * @return array The registered Activitypub comment type.
 */
function register_comment_type( $comment_type, $args = array() ) {
	global $activitypub_comment_types;

	if ( ! is_array( $activitypub_comment_types ) ) {
		$activitypub_comment_types = array();
	}

	// Sanitize comment type name.
	$comment_type = sanitize_key( $comment_type );

	$activitypub_comment_types[ $comment_type ] = $args;

	/**
	 * Fires after a ActivityPub comment type is registered.
	 *
	 * @param string $comment_type Comment type.
	 * @param array  $args         Arguments used to register the comment type.
	 */
	do_action( 'activitypub_registered_comment_type', $comment_type, $args );

	return $args;
}

/**
 * Get the reply intent URI as a JavaScript URI.
 *
 * @return string The reply intent URI.
 */
function get_reply_intent_js() {
	return sprintf(
		'javascript:(()=>{window.open(\'%s\'+encodeURIComponent(window.location.href));})();',
		get_reply_intent_url()
	);
}

/**
 * Get the reply intent URI.
 *
 * @return string The reply intent URI.
 */
function get_reply_intent_url() {
	/**
	 * Filters the reply intent parameters.
	 *
	 * @param array $params The reply intent parameters.
	 */
	$params = \apply_filters( 'activitypub_reply_intent_params', array() );

	$params += array( 'in_reply_to' => '' );
	$query   = \http_build_query( $params );
	$path    = 'post-new.php?' . $query;
	$url     = \admin_url( $path );

	/**
	 * Filters the reply intent URL.
	 *
	 * @param string $url The reply intent URL.
	 */
	$url = \apply_filters( 'activitypub_reply_intent_url', $url );

	return esc_url_raw( $url );
}
