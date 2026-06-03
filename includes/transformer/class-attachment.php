<?php
/**
 * Attachment Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use function Activitypub\is_post_publicly_queryable;

/**
 * WordPress Attachment Transformer.
 *
 * The Attachment Transformer is responsible for transforming a WP_Post object into different other
 * Object-Types.
 *
 * Currently supported are:
 *
 * - Activitypub\Activity\Base_Object
 */
class Attachment extends Post {
	/**
	 * Whether the attachment should be redacted from ActivityPub representations.
	 *
	 * An attachment has no public status of its own (`inherit`) and is a media
	 * object in its own right, so unlike a regular post it is not redacted by
	 * the parent class's status/visibility rules. Instead it inherits its
	 * parent's visibility: an attachment of a non-public post is redacted to a
	 * Tombstone, while a standalone attachment is always rendered.
	 *
	 * @return boolean True if the attachment must be redacted, false otherwise.
	 */
	protected function is_redacted() {
		$parent = (int) $this->item->post_parent;

		return $parent > 0 && ! is_post_publicly_queryable( $parent );
	}

	/**
	 * Generates all Media Attachments for a Post.
	 *
	 * @return array The Attachments.
	 */
	protected function get_attachment() {
		$mime_type       = \get_post_mime_type( $this->item->ID );
		$mime_type_parts = \explode( '/', $mime_type );
		$type            = '';

		switch ( $mime_type_parts[0] ) {
			case 'audio':
				$type = 'Audio';
				break;
			case 'video':
				$type = 'Video';
				break;
			case 'image':
				$type = 'Image';
				break;
		}

		$attachment = array(
			'type'      => $type,
			'url'       => wp_get_attachment_url( $this->item->ID ),
			'mediaType' => $mime_type,
		);

		$alt = \get_post_meta( $this->item->ID, '_wp_attachment_image_alt', true );
		if ( $alt ) {
			$attachment['name'] = $alt;
		}

		return $attachment;
	}

	/**
	 * Returns the ActivityStreams 2.0 Object-Type for a Post based on the
	 * settings and the Post-Type.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#activity-types
	 *
	 * @return string The Object-Type.
	 */
	protected function get_type() {
		return 'Note';
	}
}
