<?php
/**
 * ActivityPub Emoji file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Handles custom emoji processing for ActivityPub content.
 */
class Emoji {

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
			$comment_data['comment_content'] = self::replace_custom_emoji( $comment_data['comment_content'], $activity['object'] );
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
					return isset( $tag['type'] ) && 'Emoji' === $tag['type'];
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
			$local_url = Attachments::import_emoji( $emoji['url'] );
			$emoji_url = $local_url ? $local_url : $emoji['url'];
			$text      = self::replace_emoji_in_text( $text, $emoji['name'], $emoji_url );
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
	 *      Array of emoji data with url and name keys.
	 *
	 *      @type string $url  The URL of the emoji image.
	 *      @type string $name The shortcode name of the emoji (e.g., ":emoji:").
	 *  }
	 */
	public static function extract_emoji_data( $data ) {
		if ( empty( $data['tag'] ) || ! is_array( $data['tag'] ) ) {
			return array();
		}

		$emoji_data = array();

		foreach ( $data['tag'] as $tag ) {
			if ( isset( $tag['type'] ) && 'Emoji' === $tag['type'] && ! empty( $tag['name'] ) && ! empty( $tag['icon']['url'] ) ) {
				$emoji_data[] = array(
					'url'  => $tag['icon']['url'],
					'name' => $tag['name'],
				);
			}
		}

		return $emoji_data;
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
		return str_replace(
			$placeholder,
			sprintf(
				'<img src="%s" alt="%s" class="emoji" />',
				\esc_url( $emoji_url ),
				\esc_attr( trim( $placeholder, ':' ) )
			),
			$text
		);
	}
}
