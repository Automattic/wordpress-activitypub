<?php
/**
 * Plugin Name:       Keep the Featured Image out of the Fediverse
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Stops the featured image being federated as media, so it does not take a slot from the images in the post.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Matthias Pfefferle
 * Author URI:        https://notiz.blog/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       activitypub-no-featured-image
 * Requires Plugins:  activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Snippets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drop the featured image from the attachments of a post.
 *
 * The transformer lists the thumbnail first, before the images found in the content, so with a
 * limit on attachments the featured image takes a slot and the last image in the post falls off
 * the end.
 *
 * @param array    $media The attachments, each one an array with an `id`.
 * @param \WP_Post $item  The post being transformed.
 *
 * @return array The attachments without the featured image.
 */
function remove_featured_image_attachment( $media, $item ) {
	if ( ! \has_post_thumbnail( $item ) ) {
		return $media;
	}

	$thumbnail_id = (int) \get_post_thumbnail_id( $item );

	return \array_values(
		\array_filter(
			$media,
			static function ( $attachment ) use ( $thumbnail_id ) {
				// Anything without an id came from somewhere else and is left alone.
				return ! isset( $attachment['id'] ) || (int) $attachment['id'] !== $thumbnail_id;
			}
		)
	);
}
\add_filter( 'activitypub_attachment_ids', __NAMESPACE__ . '\remove_featured_image_attachment', 10, 2 );

/**
 * Drop the `image` property from a federated post.
 *
 * Separate from the attachments above: `image` is the preview some servers show for a link, and
 * it is filled from the featured image as well.
 *
 * Filtered here rather than on `activitypub_get_image`, which also runs for the actor icon and
 * would take the site icon down with it.
 *
 * @param array  $object_array The object as it will be sent.
 * @param string $object_class The object class, lowercased.
 *
 * @return array The object without its image.
 */
function remove_featured_image_property( $object_array, $object_class ) {
	if ( \in_array( $object_class, array( 'note', 'article' ), true ) ) {
		unset( $object_array['image'] );
	}

	return $object_array;
}
\add_filter( 'activitypub_activity_object_array', __NAMESPACE__ . '\remove_featured_image_property', 10, 2 );
