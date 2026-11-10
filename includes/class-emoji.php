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
 * Wraps emoji shortcodes with block patterns at insert time. The blocks are
 * rendered at display time by WordPress (posts) or via do_blocks() (comments).
 *
 * Also handles emoji replacement for comment author names (which don't use blocks).
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/9098/fep-9098.md FEP-9098: Custom Emojis
 */
class Emoji {

	/**
	 * Pattern of a valid emoji shortcode, without colons.
	 *
	 * Letters, digits, underscores and hyphens, 2 to 100 characters.
	 *
	 * @var string
	 */
	private const SHORTCODE = '[A-Za-z0-9_-]{2,100}';

	/**
	 * Wrap emoji shortcodes in content with block patterns.
	 *
	 * Called at insert time to wrap emoji shortcodes with activitypub/emoji blocks.
	 * The blocks are rendered at display time via their render_callback.
	 *
	 * @param string $content  The content to process.
	 * @param array  $activity The activity containing emoji definitions in 'tag'.
	 *
	 * @return string The content with wrapped emoji.
	 */
	public static function wrap_in_content( $content, $activity ) {
		if ( empty( $content ) || empty( $activity['tag'] ) || ! \is_array( $activity['tag'] ) ) {
			return $content;
		}

		$replacements = array();

		foreach ( $activity['tag'] as $tag ) {
			if ( ! \is_array( $tag ) || ! isset( $tag['type'] ) || 'Emoji' !== $tag['type'] ) {
				continue;
			}

			// Only valid shortcodes, the first tag of a name wins.
			$key = self::get_shortcode( $tag['name'] ?? null );
			if ( false === $key || isset( $replacements[ $key ] ) ) {
				continue;
			}

			$url = object_to_uri( $tag['icon'] ?? null );
			if ( empty( $url ) ) {
				continue;
			}

			$block_attrs = array( 'url' => \esc_url( $url ) );

			if ( ! empty( $tag['updated'] ) && \is_string( $tag['updated'] ) && \strtotime( $tag['updated'] ) ) {
				$block_attrs['updated'] = \sanitize_text_field( $tag['updated'] );
			}

			$replacements[ $key ] = \get_comment_delimited_block_content( 'activitypub/emoji', $block_attrs, \sprintf( ':%s:', \trim( $tag['name'], ':' ) ) );
		}

		return self::replace_names( $content, $replacements );
	}

	/**
	 * Generate an emoji img tag.
	 *
	 * @param string $url  The emoji image URL.
	 * @param string $name The emoji name (without colons).
	 *
	 * @return string The emoji img tag HTML.
	 */
	public static function get_img_tag( $url, $name ) {
		return \sprintf(
			'<img src="%s" alt="%s" title="%s" class="emoji" width="20" height="20" draggable="false" />',
			\esc_url( $url ),
			\esc_attr( $name ),
			\esc_attr( $name )
		);
	}

	/**
	 * Get the allowed HTML structure for emoji img tags.
	 *
	 * Used by Comment class for KSES validation of emoji in author names.
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
	 * By default, only allows locally cached emoji URLs for privacy.
	 * Remote URLs are only allowed when caching is explicitly disabled.
	 *
	 * @param string $value The src attribute value.
	 *
	 * @return bool True if the src is valid, false otherwise.
	 */
	public static function validate_emoji_src( $value ) {
		$upload_dir = \wp_upload_dir();
		$emoji_base = $upload_dir['baseurl'] . '/activitypub/emoji/';

		// Allow local cached emoji.
		if ( \str_starts_with( $value, $emoji_base ) ) {
			return true;
		}

		// Only allow remote URLs when caching is explicitly disabled.
		// This protects user privacy by defaulting to local-only emoji.
		$allow_remote = ! Cache::is_enabled();

		// Validate the URL format if remote is allowed.
		if ( $allow_remote ) {
			$allow_remote = (bool) \wp_http_validate_url( $value );
		}

		/**
		 * Filters whether a remote emoji URL is valid.
		 *
		 * Use this filter to explicitly allow remote emoji URLs when needed
		 * (e.g., for CDN proxying).
		 *
		 * @since 5.6.0
		 *
		 * @param bool   $valid Whether the URL is valid.
		 * @param string $value The emoji src URL.
		 */
		return \apply_filters( 'activitypub_validate_emoji_src', $allow_remote, $value );
	}

	/**
	 * Prepare actor meta for emoji storage.
	 *
	 * Used for storing actor emoji data for comment author name rendering.
	 *
	 * @param array $actor The actor array containing potential emoji in tags.
	 *
	 * @return array Meta input array with emoji data, or empty array if no emoji.
	 */
	public static function prepare_actor_meta( $actor ) {
		if ( empty( $actor['tag'] ) || ! \is_array( $actor['tag'] ) ) {
			return array();
		}

		$emoji_tags = array();

		foreach ( $actor['tag'] as $tag ) {
			// Only valid shortcodes are stored.
			if ( ! \is_array( $tag ) || ! isset( $tag['type'] ) || 'Emoji' !== $tag['type'] || false === self::get_shortcode( $tag['name'] ?? null ) ) {
				continue;
			}

			$emoji_tags[] = $tag;
		}

		if ( empty( $emoji_tags ) ) {
			return array();
		}

		return array(
			'_activitypub_emoji' => \wp_json_encode( $emoji_tags ),
		);
	}

	/**
	 * Replace emoji from stored JSON data.
	 *
	 * Used for comment author name replacement at display time.
	 *
	 * @param string $text       The text to process.
	 * @param string $emoji_json JSON-encoded emoji tag data.
	 *
	 * @return string The processed text with emoji replacements.
	 */
	public static function replace_from_json( $text, $emoji_json ) {
		$tags = \json_decode( $emoji_json, true );

		if ( empty( $tags ) || ! \is_array( $tags ) ) {
			return $text;
		}

		$replacements = array();

		foreach ( $tags as $tag ) {
			// Only valid shortcodes (actors stored earlier can have others), the first tag of a name wins.
			$key = self::get_shortcode( $tag['name'] ?? null );
			if ( false === $key || isset( $replacements[ $key ] ) ) {
				continue;
			}

			// Only emoji the text uses, so the cache lookups below scale with the text, not with the actor's emoji.
			if ( false === \stripos( $text, \sprintf( ':%s:', $key ) ) ) {
				continue;
			}

			$url = object_to_uri( $tag['icon'] ?? null );
			if ( empty( $url ) ) {
				continue;
			}

			/**
			 * Filters a remote media URL for caching.
			 *
			 * @param string      $url       The remote media URL.
			 * @param string      $context   The context ('emoji').
			 * @param string|null $entity_id The entity ID.
			 * @param array       $options   Additional options.
			 */
			$cached_url = \apply_filters(
				'activitypub_remote_media_url',
				$url,
				'emoji',
				null,
				array( 'updated' => $tag['updated'] ?? null )
			);

			$name = \trim( $tag['name'], ':' );

			$replacements[ $key ] = self::get_img_tag( $cached_url ?: $url, $name );
		}

		return self::replace_names( $text, $replacements );
	}

	/**
	 * Get the shortcode of an emoji name.
	 *
	 * Names that are not valid shortcodes are not stored or matched.
	 *
	 * @param mixed $name The emoji name, usually with colons.
	 *
	 * @return string|false The lowercase shortcode without colons, false if the name is not valid.
	 */
	private static function get_shortcode( $name ) {
		if ( ! \is_string( $name ) ) {
			return false;
		}

		$shortcode = \trim( $name, ':' );
		if ( ! \preg_match( \sprintf( '/^%s\z/', self::SHORTCODE ), $shortcode ) ) {
			return false;
		}

		return \strtolower( $shortcode );
	}

	/**
	 * Replace emoji shortcodes in one pass over the original text.
	 *
	 * Scans for `:shortcode:` with a fixed pattern and looks each one up, case-insensitively, so
	 * the pattern does not grow with the emoji.
	 *
	 * @param string $text         The text to process.
	 * @param array  $replacements The replacements, keyed by the lowercase shortcode without colons.
	 *
	 * @return string The text with the shortcodes replaced, or unchanged if PCRE bails.
	 */
	private static function replace_names( $text, $replacements ) {
		if ( empty( $replacements ) ) {
			return $text;
		}

		// A shortcode needs a boundary on both sides: no letter, digit or colon next to it.
		return \preg_replace_callback(
			\sprintf( '/(?<![\p{L}\p{N}:]):(%s):(?![\p{L}\p{N}:])/u', self::SHORTCODE ),
			function ( $matches ) use ( $replacements ) {
				return $replacements[ \strtolower( $matches[1] ) ] ?? $matches[0];
			},
			$text
		) ?? $text;
	}

	/**
	 * Replace emoji in text using a remote actor's stored emoji data.
	 *
	 * Used by Mailer class for actor name/summary in emails.
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
}
