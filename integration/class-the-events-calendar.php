<?php
namespace Activitypub\Integration;

use Activitypub\Transformer\Post;

/**
 * Compatibility with the Tribe The Events Calendar plugin.
 *
 * This is a transformer for the Tribe The Events Calendar plugin,
 * that extends the default transformer for WordPress posts.
 *
 * @see https://wordpress.org/plugins/the-events-calendar/
 */
class The_Events_Calendar extends Post {
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

		return $attachments;
	}

	/**
	 * Gets the object type for a Event.
	 *
	 * Always returns 'Note' for the best possible compatibility with ActivityPub.
	 *
	 * @return string The object type.
	 */
	public function get_type() {
		return 'Event';
	}
}
