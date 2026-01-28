<?php
/**
 * Post functions.
 *
 * Functions for working with posts in ActivityPub context.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Posts;

/**
 * Check if a post is disabled for ActivityPub.
 *
 * @param mixed $post The post object or ID.
 *
 * @return boolean True if the post is disabled, false otherwise.
 */
function is_post_disabled( $post ) {
	$post     = \get_post( $post );
	$disabled = false;

	if ( ! $post ) {
		return true;
	}

	$visibility          = \get_post_meta( $post->ID, 'activitypub_content_visibility', true );
	$is_local_or_private = in_array( $visibility, array( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE ), true );

	if (
		$is_local_or_private ||
		! \post_type_supports( $post->post_type, 'activitypub' ) ||
		'private' === $post->post_status ||
		! empty( $post->post_password )
	) {
		$disabled = true;
	}

	/*
	 * Check for posts that need special handling.
	 * Federated posts changed to local/private need Delete activity.
	 * Deleted posts restored to public need Create activity.
	 */
	$object_state = get_wp_object_state( $post );

	if (
		ACTIVITYPUB_OBJECT_STATE_DELETED === $object_state ||
		( $is_local_or_private && ACTIVITYPUB_OBJECT_STATE_FEDERATED === $object_state )
	) {
		$disabled = false;
	}

	/**
	 * Allow plugins to disable posts for ActivityPub.
	 *
	 * @param boolean  $disabled True if the post is disabled, false otherwise.
	 * @param \WP_Post $post     The post object.
	 */
	return \apply_filters( 'activitypub_is_post_disabled', $disabled, $post );
}

/**
 * Check if a post is an ActivityPub post.
 *
 * @param mixed $post The post object or ID.
 *
 * @return boolean True if the post is an ActivityPub post, false otherwise.
 */
function is_ap_post( $post ) {
	$post = \get_post( $post );

	if ( ! $post ) {
		return false;
	}

	// Check for ap_post post type.
	return Posts::POST_TYPE === $post->post_type;
}

/**
 * Get the description of a post type.
 *
 * Set some default descriptions for the default post types.
 *
 * @param \WP_Post_Type $post_type The post type object.
 *
 * @return string The description of the post type.
 */
function get_post_type_description( $post_type ) {
	switch ( $post_type->name ) {
		case 'post':
		case 'page':
			$description = '';
			break;
		case 'attachment':
			$description = ' - ' . __( 'Files uploaded to the media library (such as images, videos, documents, or other attachments). Note: This federates every file upload, not just published content.', 'activitypub' );
			break;
		default:
			$description = '';
			if ( ! empty( $post_type->description ) ) {
				$description = ' - ' . $post_type->description;
			}
	}

	/**
	 * Allow plugins to get the description of a post type.
	 *
	 * @param string        $description    The description of the post type.
	 * @param string        $post_type_name The post type name.
	 * @param \WP_Post_Type $post_type      The post type object.
	 */
	return apply_filters( 'activitypub_post_type_description', $description, $post_type->name, $post_type );
}

/**
 * Get the enclosures of a post.
 *
 * @param int $post_id The post ID.
 *
 * @return array The enclosures.
 */
function get_enclosures( $post_id ) {
	$enclosures = get_post_meta( $post_id, 'enclosure', false );

	if ( ! $enclosures ) {
		return array();
	}

	$enclosures = array_map(
		static function ( $enclosure ) {
			// Check if the enclosure is a string.
			if ( ! $enclosure || ! is_string( $enclosure ) ) {
				return false;
			}

			$attributes = explode( "\n", $enclosure );

			if ( ! isset( $attributes[0] ) || ! \wp_http_validate_url( $attributes[0] ) ) {
				return false;
			}

			return array(
				'url'       => $attributes[0],
				'length'    => $attributes[1] ?? null,
				'mediaType' => $attributes[2] ?? 'application/octet-stream',
			);
		},
		$enclosures
	);

	return array_filter( $enclosures );
}

/**
 * Generates a summary of a post.
 *
 * This function generates a summary based on the post's excerpt or content.
 *
 * @param int|\WP_Post $post   The post ID or post object.
 * @param integer      $length The maximum length of the summary.
 *                             Default is 500. It will be ignored if the post excerpt
 *                             and the content above the <!--more--> tag.
 *
 * @return string The generated post summary.
 */
function generate_post_summary( $post, $length = 500 ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return '';
	}

	/**
	 * Filters the excerpt more value.
	 *
	 * @param string $excerpt_more The excerpt more.
	 */
	$excerpt_more = \apply_filters( 'activitypub_excerpt_more', '[…]' );
	$length       = $length - \mb_strlen( $excerpt_more, 'UTF-8' );

	$content = \sanitize_post_field( 'post_excerpt', $post->post_excerpt, $post->ID );

	if ( $content ) {
		// Ignore length if excerpt is set.
		$length = null;
	} else {
		$content       = \sanitize_post_field( 'post_content', $post->post_content, $post->ID );
		$content_parts = \get_extended( $content );

		// Check for the <!--more--> tag.
		if (
			! empty( $content_parts['extended'] ) &&
			! empty( $content_parts['main'] )
		) {
			$content = \trim( $content_parts['main'] ) . ' ' . $excerpt_more;
			$length  = null;
		}
	}

	$content = \strip_shortcodes( $content );
	$content = \wp_strip_all_tags( $content );
	$content = \html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
	$content = \trim( $content );
	$content = \preg_replace( '/\R+/mu', "\n\n", $content );
	$content = \preg_replace( '/[\r\t]/u', '', $content );

	if ( $length && \mb_strlen( $content, 'UTF-8' ) > $length ) {
		$content = \wordwrap( $content, $length, '</activitypub-summary>' );
		$content = \explode( '</activitypub-summary>', $content, 2 );
		$content = $content[0] . ' ' . $excerpt_more;
	}

	/*
	There is no proper support for HTML in ActivityPub summaries yet.
	// This filter is documented in wp-includes/post-template.php.
	return \apply_filters( 'the_excerpt', $content );
	*/
	return $content;
}

/**
 * Get the content warning of a post.
 *
 * @param int|\WP_Post $post_id The post ID or post object.
 *
 * @return string|false The content warning or false if not found.
 */
function get_content_warning( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	$warning = get_post_meta( $post->ID, 'activitypub_content_warning', true );
	if ( empty( $warning ) ) {
		return false;
	}

	return $warning;
}

/**
 * Get the ActivityPub ID of a Post by the WordPress Post ID.
 *
 * @param int $id The WordPress Post ID.
 *
 * @return string The ActivityPub ID (a URL) of the Post.
 */
function get_post_id( $id ) {
	$last_legacy_id = (int) \get_option( 'activitypub_last_post_with_permalink_as_id', 0 );
	$post_id        = (int) $id;

	if ( $post_id > $last_legacy_id ) {
		// Generate URI based on post ID.
		return \add_query_arg( 'p', $post_id, \home_url( '/' ) );
	}

	return \get_permalink( $post_id );
}

/**
 * Get the visibility of a post.
 *
 * @param int $post_id The post ID.
 *
 * @return string|false The visibility of the post or false if not found.
 */
function get_content_visibility( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}

	$visibility  = \get_post_meta( $post->ID, 'activitypub_content_visibility', true );
	$_visibility = ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
	$options     = array(
		ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC,
		ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
		ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
	);

	if ( in_array( $visibility, $options, true ) ) {
		$_visibility = $visibility;
	}

	/**
	 * Filters the visibility of a post.
	 *
	 * @param string   $_visibility The visibility of the post. Possible values are:
	 *                              - ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
	 *                              - ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC
	 *                              - ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE
	 *                              - ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL
	 * @param \WP_Post $post        The post object.
	 */
	return \apply_filters( 'activitypub_content_visibility', $_visibility, $post );
}
