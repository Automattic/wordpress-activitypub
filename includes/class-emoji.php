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
	 * Extract emoji data from activity tags.
	 *
	 * @param array $activity The activity array containing emoji definitions.
	 *
	 * @return array {
	 *      Array of emoji data with url and name keys.
	 *
	 *      @type string $url  The URL of the emoji image.
	 *      @type string $name The shortcode name of the emoji (e.g., ":emoji:").
	 *  }
	 */
	private static function extract_emoji_data( $activity ) {
		if ( empty( $activity['tag'] ) || ! is_array( $activity['tag'] ) ) {
			return array();
		}

		$emoji_data = array();

		foreach ( $activity['tag'] as $tag ) {
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
