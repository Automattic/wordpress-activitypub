<?php
/**
 * Podlove Podcast Publisher integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Transformer\Post;

use function Activitypub\object_to_uri;
use function Activitypub\seconds_to_iso8601;

/**
 * Compatibility with the Podlove Podcast Publisher plugin.
 *
 * This is a transformer for the Podlove Podcast Publisher plugin,
 * that extends the default transformer for WordPress posts.
 *
 * @see https://wordpress.org/plugins/podlove-podcasting-plugin-for-wordpress/
 */
class Podlove_Podcast_Publisher extends Post {
	/**
	 * The Podlove Episode object.
	 *
	 * @var \Podlove\Model\Episode|null
	 */
	private $episode = null;

	/**
	 * Get the Podlove Episode object.
	 *
	 * @return \Podlove\Model\Episode|null The episode object or null if not found.
	 */
	protected function get_episode() {
		if ( null === $this->episode && \class_exists( '\Podlove\Model\Episode' ) ) {
			$this->episode = \Podlove\Model\Episode::find_one_by_post_id( $this->item->ID );
		}
		return $this->episode;
	}

	/**
	 * Gets the attachment for a podcast episode.
	 *
	 * This method is overridden to add the audio/video files as attachments.
	 *
	 * @return array The attachments array.
	 */
	public function get_attachment() {
		$episode = $this->get_episode();

		if ( ! $episode ) {
			return parent::get_attachment();
		}

		$attachments = array();

		// Get media files from Podlove.
		$media_files = $episode->media_files();

		foreach ( $media_files as $media_file ) {
			if ( ! $media_file->is_valid() ) {
				continue;
			}

			$episode_asset = $media_file->episode_asset();

			if ( ! $episode_asset ) {
				continue;
			}

			$file_type = $episode_asset->file_type();

			if ( ! $file_type ) {
				continue;
			}

			// Only include audio and video files.
			if ( ! in_array( $file_type->type, array( 'audio', 'video' ), true ) ) {
				continue;
			}

			// Use tracking URL if analytics is enabled, otherwise direct file URL.
			if ( 'ptm_analytics' === \Podlove\get_setting( 'tracking', 'mode' ) ) {
				$file_url = $media_file->get_public_file_url( 'activitypub' );
			} else {
				$file_url = $media_file->get_file_url();
			}

			$attachment = array(
				'type'      => \esc_attr( ucfirst( $file_type->type ) ),
				'url'       => \esc_url( $file_url ),
				'mediaType' => \esc_attr( $file_type->mime_type ),
				'name'      => \esc_attr( $episode->title() ?? '' ),
			);

			// Add duration if available (in ISO 8601 format).
			$duration = $episode->get_duration( 'seconds' );
			if ( $duration && is_numeric( $duration ) && (int) $duration > 0 ) {
				$attachment['duration'] = seconds_to_iso8601( (int) $duration );
			}

			$attachments[] = $attachment;
		}

		// If we have media files, add episode image as icon.
		if ( ! empty( $attachments ) ) {
			$icon = $this->get_episode_image();

			if ( $icon ) {
				foreach ( $attachments as $key => $attachment ) {
					$attachments[ $key ]['icon'] = \esc_url( $icon );
				}
			}
		}

		// If no Podlove media files found, fall back to parent.
		if ( empty( $attachments ) ) {
			return parent::get_attachment();
		}

		return $attachments;
	}

	/**
	 * Get the episode image URL.
	 *
	 * @return string|null The image URL or null if not found.
	 */
	protected function get_episode_image() {
		$episode = $this->get_episode();

		if ( ! $episode ) {
			return null;
		}

		$image = $episode->cover_art_with_fallback();

		if ( $image && method_exists( $image, 'url' ) ) {
			return $image->url();
		}

		// Fall back to post thumbnail.
		$icon = $this->get_icon();
		if ( $icon ) {
			return object_to_uri( $icon );
		}

		return null;
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

	/**
	 * Get the duration of the episode in ISO 8601 format.
	 *
	 * @return string|null The duration in ISO 8601 format or null if not available.
	 */
	public function get_duration() {
		$episode = $this->get_episode();

		if ( ! $episode ) {
			return null;
		}

		$duration_seconds = $episode->get_duration( 'seconds' );

		// Ensure we have a valid numeric duration.
		if ( ! $duration_seconds || ! is_numeric( $duration_seconds ) ) {
			return null;
		}

		$duration_seconds = (int) $duration_seconds;

		if ( $duration_seconds <= 0 ) {
			return null;
		}

		return seconds_to_iso8601( $duration_seconds );
	}
}
