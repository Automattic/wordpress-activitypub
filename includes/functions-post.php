<?php
/**
 * Post functions.
 *
 * Functions for working with posts in ActivityPub context.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Remote_Posts;

/**
 * Check whether ActivityPub processing should be skipped for this post.
 *
 * Pipeline-level gate. Used by schedulers, transformers, and the outbox to
 * decide whether a post participates in federation processing at all.
 *
 * Intentionally returns `false` for posts that are undergoing a federation
 * lifecycle transition — e.g., a previously federated post whose visibility
 * was changed to private, or a previously deleted post that was restored —
 * so that the Delete or Create activity can still be emitted to notify
 * remote servers.
 *
 * DO NOT use this as a content-exposure gate for REST metadata, block
 * rendering, content-negotiated frontend JSON, or any other surface that
 * reveals a post's current content or existence to unauthenticated readers.
 * Use {@see is_post_publicly_queryable()} for those: it answers the simpler
 * "is this post currently public?" question with no lifecycle escape hatch.
 *
 * @see is_post_publicly_queryable() For the current-visibility gate used by
 *                                   content-exposure surfaces.
 *
 * @param mixed $post The post object or ID.
 *
 * @return boolean True if ActivityPub processing should be skipped for this post, false otherwise.
 */
function is_post_disabled( $post ) {
	// Refuse empty input so `get_post()` doesn't silently resolve to the global $post.
	if ( empty( $post ) ) {
		return true;
	}

	$post = \get_post( $post );

	if ( ! $post ) {
		return true;
	}

	$disabled = ! is_post_publicly_queryable( $post );

	/*
	 * Lifecycle-transition override.
	 *
	 * A previously federated post that has since been moved to any non-
	 * publicly-queryable state (local/private visibility, non-public
	 * status, password-protected, or whose post type no longer supports
	 * federation) still needs the pipeline to run so it can emit a Delete
	 * activity. A post that was deleted but later restored needs the
	 * pipeline to emit Create. In both cases we flip the gate back open
	 * even though the post is not currently publicly queryable.
	 */
	$object_state = get_wp_object_state( $post );

	if (
		ACTIVITYPUB_OBJECT_STATE_DELETED === $object_state ||
		( ACTIVITYPUB_OBJECT_STATE_FEDERATED === $object_state && $disabled )
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
 * Check whether a post's current content is publicly queryable via ActivityPub.
 *
 * Content-exposure gate. Use wherever a post's current content, metadata, or
 * mere existence could leak to an unauthenticated request. Unlike
 * {@see is_post_disabled()}, this function ignores the federation lifecycle
 * state: a post that was federated publicly and has since been made private,
 * local, trashed, or password-protected returns `false` here, even while its
 * Delete activity is still pending in the outbox.
 *
 * Use for: per-post REST metadata routes (reactions, replies, context,
 * remote-reply), block server-side render callbacks that expose post
 * content, content-negotiated frontend JSON. Do NOT use for federation
 * pipeline decisions — that's what {@see is_post_disabled()} is for.
 *
 * A post is publicly queryable when it satisfies ALL of the following:
 *   - `post_status` is `publish` (or a well-defined equivalent: published
 *     attachments inheriting from a public parent, or a preview requested
 *     by a user with edit capability).
 *   - Its `activitypub_content_visibility` meta is neither `local` nor
 *     `private`.
 *   - The post type supports the `activitypub` feature.
 *   - No `post_password` is set.
 *
 * @since 8.1.0
 *
 * @see is_post_disabled() For the pipeline-level federation gate.
 *
 * @param mixed $post The post object or ID.
 *
 * @return boolean True if the post is currently publicly queryable, false otherwise.
 */
function is_post_publicly_queryable( $post ) {
	/*
	 * Refuse to resolve an empty/zero input through `get_post()`. A bare
	 * `get_post( null )` or `get_post( 0 )` falls back to the global
	 * `$post` during a WordPress loop, which would silently check the
	 * wrong post and potentially leak reactions/replies/metadata for a
	 * looped-over post instead of the one the caller intended.
	 */
	if ( empty( $post ) ) {
		return false;
	}

	$post = \get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$visibility          = \get_post_meta( $post->ID, 'activitypub_content_visibility', true );
	$is_local_or_private = in_array( $visibility, array( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE ), true );

	/*
	 * An attachment (`inherit` status) inherits its parent's visibility.
	 * Recurse into the parent so the attachment also picks up the parent's
	 * content-visibility meta, password protection, and post-type support,
	 * not just its post_status. Unattached attachments are allowed through.
	 */
	$is_attachment_public = 'inherit' === $post->post_status &&
		'attachment' === $post->post_type &&
		( ! $post->post_parent || is_post_publicly_queryable( $post->post_parent ) );

	// Drafts and pending posts are allowed during preview requests so the Fediverse Preview works.
	$is_preview = in_array( $post->post_status, array( 'draft', 'pending' ), true ) &&
		\get_query_var( 'preview' ) &&
		\current_user_can( 'edit_post', $post->ID );

	$is_public_status = 'publish' === $post->post_status || $is_attachment_public || $is_preview;

	$queryable = $is_public_status &&
		! $is_local_or_private &&
		\post_type_supports( $post->post_type, 'activitypub' ) &&
		empty( $post->post_password );

	/**
	 * Filter whether a post is publicly queryable via ActivityPub.
	 *
	 * @since 8.1.0
	 *
	 * @param boolean  $queryable True if the post is publicly queryable, false otherwise.
	 * @param \WP_Post $post      The post object.
	 */
	return \apply_filters( 'activitypub_is_post_publicly_queryable', $queryable, $post );
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
	return Remote_Posts::POST_TYPE === $post->post_type;
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
