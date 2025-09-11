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

		$emoji_attachments = self::get_emoji_attachments( $emoji_data );

		foreach ( $emoji_data as $emoji ) {
			$attachment_id = self::get_or_create_emoji_attachment( $emoji, $emoji_attachments );
			$text          = self::replace_emoji_in_text( $text, $emoji, $attachment_id );
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
	 *      @type string $url The URL of the emoji image.
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
	 * Get existing emoji attachments for the given emoji data.
	 *
	 * @param array $emoji_data {
	 *      Array of emoji data with url and name keys.
	 *
	 *      @type string $url The URL of the emoji image.
	 *      @type string $name The shortcode name of the emoji (e.g., ":emoji:").
	 * }
	 *
	 * @return array {
	 *      Array containing two lookup arrays:
	 *
	 *      @type array $url_to_attachment  Lookup array mapping emoji URLs to attachment IDs.
	 *      @type array $name_to_attachment Lookup array mapping emoji names to attachment IDs.
	 * }
	 */
	private static function get_emoji_attachments( $emoji_data ) {
		// Single query for all emoji at once.
		$meta_query = array( 'relation' => 'OR' );

		foreach ( $emoji_data as $emoji ) {
			$meta_query[] = array(
				'key'   => 'activitypub_emoji_source_url',
				'value' => $emoji['url'],
			);
			$meta_query[] = array(
				'key'   => 'activitypub_emoji_placeholder',
				'value' => $emoji['name'],
			);
		}

		$existing_attachments = \get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'fields'         => 'ids',

				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => $meta_query,
			)
		);

		// Build lookup arrays for fast access.
		$url_to_attachment  = array();
		$name_to_attachment = array();

		foreach ( $existing_attachments as $post_id ) {
			$source_url  = \get_post_meta( $post_id, 'activitypub_emoji_source_url', true );
			$placeholder = \get_post_meta( $post_id, 'activitypub_emoji_placeholder', true );

			if ( $source_url ) {
				$url_to_attachment[ $source_url ] = $post_id;
			}
			if ( $placeholder ) {
				$name_to_attachment[ $placeholder ] = $post_id;
			}
		}

		return array(
			'url_to_attachment'  => $url_to_attachment,
			'name_to_attachment' => $name_to_attachment,
		);
	}

	/**
	 * Get existing emoji attachment or create a new one.
	 *
	 * @param array $emoji             Single emoji data with url and name.
	 * @param array $emoji_attachments Lookup arrays from get_emoji_attachments.
	 *
	 * @return int Attachment ID or 0 if failed.
	 */
	private static function get_or_create_emoji_attachment( $emoji, $emoji_attachments ) {
		$existing_attachment  = $emoji_attachments['url_to_attachment'][ $emoji['url'] ] ?? 0;
		$existing_placeholder = $emoji_attachments['name_to_attachment'][ $emoji['name'] ] ?? 0;

		// If we have a different image for this placeholder, or no existing image, download the new one.
		if ( empty( $existing_attachment ) || ! empty( $existing_placeholder ) ) {
			return self::download_emoji( $emoji['name'], $emoji['url'] );
		}

		return $existing_attachment;
	}

	/**
	 * Download and store emoji as attachment.
	 *
	 * @param string $emoji_name The emoji placeholder name.
	 * @param string $emoji_url  The emoji source URL.
	 *
	 * @return int Attachment ID or 0 if failed.
	 */
	private static function download_emoji( $emoji_name, $emoji_url ) {
		if ( ! function_exists( '\download_url' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_file = \download_url( $emoji_url, 10 ); // 10 second timeout for emoji downloads.
		if ( \is_wp_error( $temp_file ) ) {
			return 0;
		}

		$file_array = array(
			'name'     => \wp_basename( $emoji_url ),
			'tmp_name' => $temp_file,
		);

		if ( ! function_exists( '\media_handle_sideload' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/media.php';
			require_once \ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = \media_handle_sideload( $file_array, 0, $emoji_name );

		// Clean up temp file after processing.
		if ( \is_file( $temp_file ) ) {
			\wp_delete_file( $temp_file );
		}

		if ( \is_wp_error( $attachment_id ) ) {
			return 0;
		}

		\update_post_meta( $attachment_id, 'activitypub_emoji_source_url', $emoji_url );
		\update_post_meta( $attachment_id, 'activitypub_emoji_placeholder', $emoji_name );

		return $attachment_id;
	}

	/**
	 * Replace emoji shortcode in text with image or fallback.
	 *
	 * @param string $text          The text to process.
	 * @param array  $emoji         Single emoji data with url and name.
	 * @param int    $attachment_id The attachment ID or 0 if none.
	 *
	 * @return string The processed text.
	 */
	private static function replace_emoji_in_text( $text, $emoji, $attachment_id ) {
		$emoji_url = $emoji['url'];
		if ( $attachment_id ) {
			$emoji_url = \wp_get_attachment_url( $attachment_id );
		}

		return str_replace(
			$emoji['name'],
			sprintf(
				'<img src="%s" alt="%s" class="emoji" />',
				\esc_url( $emoji_url ),
				\esc_attr( trim( $emoji['name'], ':' ) )
			),
			$text
		);
	}
}
