<?php

namespace Activitypub\Integration;

use Activitypub\Transformer\Post;

class Seriously_Simple_Podcasting extends Post {
	/**
	 * Gets the attachment for a podcast episode.
	 *
	 * This method is overridden to add the audio file as an attachment.
	 *
	 * @return array The attachments array.
	 */
	public function get_attachment() {
		$post        = $this->wp_object;
		$attachments = parent::get_attachment();

		$attachment = array(
			'type' => \esc_attr( \get_post_meta( $post->ID, 'episode_type', true ) ),
			'url'  => \esc_url( \get_post_meta( $post->ID, 'audio_file', true ) ),
			'name' => \esc_attr( \get_the_title( $post->ID ) ),
			'icon' => \esc_url( \get_post_meta( $post->ID, 'cover_image', true ) ),
		);

		$attachment = array_filter( $attachment );
		array_unshift( $attachments, $attachment );

		return $attachments;
	}

	/**
	 * Gets the object type for a podcast episode.
	 *
	 * Always returns 'Note' for the best possible compatibility with ActivityPub.
	 *
	 * @return string The object type.
	 */
	public function get_type() {
		return 'Note';
	}
}
