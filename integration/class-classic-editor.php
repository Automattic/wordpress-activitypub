<?php
/**
 * Classic Editor integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

/**
 * Classic Editor integration class.
 *
 * Handles compatibility with the Classic Editor plugin and sites without
 * block editor support.
 */
class Classic_Editor {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_attachments_media_markup', array( self::class, 'filter_attachments_media_markup' ), 10, 2 );
	}

	/**
	 * Filter attachment media markup to use shortcodes instead of blocks.
	 *
	 * @param string $markup         The custom markup. Empty string by default.
	 * @param array  $attachment_ids Array of attachment IDs.
	 *
	 * @return string The generated shortcode markup.
	 */
	public static function filter_attachments_media_markup( $markup, $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return $markup;
		}

		$type = strtok( \get_post_mime_type( $attachment_ids[0] ), '/' );

		// Single video or audio file: use media shortcode.
		if ( 1 === \count( $attachment_ids ) && ( 'video' === $type || 'audio' === $type ) ) {
			return sprintf(
				'[%1$s src="%2$s"]',
				\esc_attr( $type ),
				\esc_url( \wp_get_attachment_url( $attachment_ids[0] ) )
			);
		}

		// Multiple attachments or images: use gallery shortcode.
		return '[gallery ids="' . implode( ',', $attachment_ids ) . '" link="none"]';
	}
}
