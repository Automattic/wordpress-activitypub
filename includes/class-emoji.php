<?php
/**
 * ActivityPub Emoji file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Handles custom emoji processing for ActivityPub content.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/9098/fep-9098.md FEP-9098: Custom Emojis
 */
class Emoji {

	/**
	 * Get the allowed HTML structure for emoji img tags.
	 *
	 * Uses WordPress KSES features (WP 5.9+) to strictly validate emoji images:
	 * - Requires class="emoji"
	 * - Validates src URL points to local emoji directory
	 * - Requires standard emoji dimensions
	 *
	 * @return array The allowed HTML structure for use with wp_kses.
	 */
	public static function get_kses_allowed_html() {
		return array(
			'img' => array(
				'class'     => array(
					'required' => true,
					'values'   => array( 'emoji' ),
				),
				'src'       => array(
					'required'       => true,
					'value_callback' => array( self::class, 'validate_emoji_src' ),
				),
				'alt'       => array( 'required' => true ),
				'title'     => array( 'required' => true ),
				'height'    => array(
					'required' => true,
					'values'   => array( '20' ),
				),
				'width'     => array(
					'required' => true,
					'values'   => array( '20' ),
				),
				'draggable' => array(
					'required' => true,
					'values'   => array( 'false' ),
				),
			),
		);
	}

	/**
	 * Validate emoji src attribute for wp_kses.
	 *
	 * Only allows emoji URLs from local uploads directory.
	 *
	 * @param string $value The src attribute value.
	 *
	 * @return bool True if the src is valid, false otherwise.
	 */
	public static function validate_emoji_src( $value ) {
		$upload_dir = \wp_upload_dir();
		$emoji_base = $upload_dir['baseurl'] . Attachments::$emoji_dir;

		return \str_starts_with( $value, $emoji_base );
	}

	/**
	 * Prepare comment data with emoji handling.
	 *
	 * Replaces emoji in content at insert-time. Author emoji is handled
	 * at display-time via the remote actor's stored emoji data.
	 *
	 * @param array $comment_data The comment data array.
	 * @param array $activity     The activity array.
	 *
	 * @return array The comment data with emoji processing applied.
	 */
	public static function prepare_comment_data( $comment_data, $activity ) {
		// Replace emoji in content at insert-time.
		if ( ! empty( $comment_data['comment_content'] ) && ! empty( $activity['object'] ) ) {
			// Unslash, replace emoji, then re-slash to avoid escaping img tag attributes.
			$content                         = \wp_unslash( $comment_data['comment_content'] );
			$content                         = self::replace_custom_emoji( $content, $activity['object'] );
			$comment_data['comment_content'] = \addslashes( $content );
		}

		return $comment_data;
	}

	/**
	 * Prepare actor meta for emoji storage.
	 *
	 * Extracts emoji data from an actor and returns it for storage as post meta.
	 *
	 * @param array $actor The actor array containing potential emoji in tags.
	 *
	 * @return array Meta input array with emoji data, or empty array if no emoji.
	 */
	public static function prepare_actor_meta( $actor ) {
		$emoji_tags = self::get_emoji_tags( $actor );

		if ( empty( $emoji_tags ) ) {
			return array();
		}

		return array(
			'_activitypub_emoji' => \wp_json_encode( $emoji_tags ),
		);
	}

	/**
	 * Get only the emoji-type tags from a data array.
	 *
	 * @param array $data The data array containing tags.
	 *
	 * @return array Array of emoji tag objects.
	 */
	private static function get_emoji_tags( $data ) {
		if ( empty( $data['tag'] ) || ! is_array( $data['tag'] ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$data['tag'],
				function ( $tag ) {
					return is_array( $tag ) && isset( $tag['type'] ) && 'Emoji' === $tag['type'];
				}
			)
		);
	}

	/**
	 * Replace custom emoji shortcodes with their corresponding emoji.
	 *
	 * @param string $text     The text to process.
	 * @param array  $activity The activity array containing emoji definitions.
	 *
	 * @return string The processed text with emoji replacements.
	 */
	public static function replace_custom_emoji( $text, $activity ) {
		$emoji_data = self::extract_emoji_data( $activity );
		if ( empty( $emoji_data ) ) {
			return $text;
		}

		foreach ( $emoji_data as $emoji ) {
			$local_url = Attachments::import_emoji( $emoji['url'], $emoji['updated'] ?? null );

			// Only replace if the emoji was successfully uploaded locally.
			if ( $local_url ) {
				$text = self::replace_emoji_in_text( $text, $emoji['name'], $local_url );
			}
		}

		return $text;
	}

	/**
	 * Replace emoji from stored JSON data.
	 *
	 * Used for display-time replacement when emoji data was stored as JSON.
	 *
	 * @param string $text       The text to process.
	 * @param string $emoji_json JSON-encoded emoji tag data.
	 *
	 * @return string The processed text with emoji replacements.
	 */
	public static function replace_from_json( $text, $emoji_json ) {
		$tags = \json_decode( $emoji_json, true );

		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return $text;
		}

		return self::replace_custom_emoji( $text, array( 'tag' => $tags ) );
	}

	/**
	 * Extract emoji data from activity tags.
	 *
	 * @param array $data The data array containing emoji definitions in 'tag'.
	 *
	 * @return array {
	 *      Array of emoji data with url, name, and optional updated keys.
	 *
	 *      @type string      $url     The URL of the emoji image.
	 *      @type string      $name    The shortcode name of the emoji (e.g., ":emoji:").
	 *      @type string|null $updated Optional. The emoji's updated timestamp (ISO 8601).
	 *  }
	 */
	public static function extract_emoji_data( $data ) {
		if ( empty( $data['tag'] ) || ! is_array( $data['tag'] ) ) {
			return array();
		}

		$emoji_data = array();

		foreach ( $data['tag'] as $tag ) {
			if ( is_array( $tag ) && isset( $tag['type'] ) && 'Emoji' === $tag['type'] && ! empty( $tag['name'] ) && ! empty( $tag['icon']['url'] ) ) {
				$emoji_data[] = array(
					'url'     => $tag['icon']['url'],
					'name'    => $tag['name'],
					'updated' => $tag['updated'] ?? null,
				);
			}
		}

		return $emoji_data;
	}

	/**
	 * Replace emoji in text using a remote actor's stored emoji data.
	 *
	 * Looks up the remote actor by URL and uses their stored emoji data
	 * to replace emoji shortcodes in the text.
	 *
	 * @param string $text      The text to process.
	 * @param string $actor_url The actor's URL to look up emoji data.
	 *
	 * @return string The processed text with emoji replacements.
	 */
	public static function replace_for_actor( $text, $actor_url ) {
		$actor_post = Collection\Remote_Actors::get_by_uri( $actor_url );
		if ( ! $actor_post || \is_wp_error( $actor_post ) ) {
			return $text;
		}

		$emoji_data = \get_post_meta( $actor_post->ID, '_activitypub_emoji', true );
		if ( empty( $emoji_data ) ) {
			return $text;
		}

		return self::replace_from_json( $text, $emoji_data );
	}

	/**
	 * Replace emoji placeholder in text with image tag.
	 *
	 * @param string $text        The text to process.
	 * @param string $placeholder The emoji placeholder (e.g., ":kappa:").
	 * @param string $emoji_url   The URL of the emoji image.
	 *
	 * @return string The processed text.
	 */
	private static function replace_emoji_in_text( $text, $placeholder, $emoji_url ) {
		$name = trim( $placeholder, ':' );

		return str_ireplace(
			$placeholder,
			sprintf(
				'<img src="%s" alt="%s" title="%s" class="emoji" width="20" height="20" draggable="false" />',
				\esc_url( $emoji_url ),
				\esc_attr( $name ),
				\esc_attr( $name )
			),
			$text
		);
	}
}
