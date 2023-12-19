<?php
namespace Activitypub\Transformer;

use Activitypub\Transformer\Post;

/**
 * WordPress Attachment Transformer
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
	 * Generates all Media Attachments for a Post.
	 *
	 * @return array The Attachments.
	 */
	protected function get_attachment() {
		$mime_type  = get_post_mime_type( $this->object->ID );
		$media_type = preg_replace( '/(\/[a-zA-Z]+)/i', '', $mime_type );

		switch ( $media_type ) {
			case 'audio':
			case 'video':
				$type = 'Document';
				break;
			case 'image':
				$type = 'Image';
				break;
		}

		$attachment = array(
			'type'      => $type,
			'url'       => wp_get_attachment_url( $this->object->ID ),
			'mediaType' => $mime_type,
		);

		$alt = \get_post_meta( $this->object->ID, '_wp_attachment_image_alt', true );
		if ( $alt ) {
			$attachment['name'] = $alt;
		}

		return $attachment;
	}
}
