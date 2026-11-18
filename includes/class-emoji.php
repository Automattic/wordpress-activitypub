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
		return self::replace_names( $text, self::get_replacements( $emoji_json, $text ) );
	}

	/**
	 * Get the img tags for the emoji a text uses.
	 *
	 * @param string $emoji_json JSON-encoded emoji tag data.
	 * @param string $text       The text, only emoji it names as `:shortcode:` are looked up.
	 *
	 * @return string[] The img tags, keyed by the lowercase shortcode without colons.
	 */
	private static function get_replacements( $emoji_json, $text ) {
		$tags = \json_decode( $emoji_json, true );

		if ( empty( $tags ) || ! \is_array( $tags ) ) {
			return array();
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

		return $replacements;
	}

	/**
	 * Restore the emoji img tags in an escaped text.
	 *
	 * An img in the form get_img_tag() writes, as esc_html() leaves it, is replaced by the img of
	 * the emoji with that name, built from the emoji data, and has to pass the emoji rules.
	 * Anything else stays escaped.
	 *
	 * @since unreleased
	 *
	 * @param string $text       The escaped text.
	 * @param string $emoji_json JSON-encoded emoji tag data.
	 *
	 * @return string The text with the emoji img tags restored.
	 */
	public static function restore_img_tags( $text, $emoji_json ) {
		$pattern = \sprintf(
			'/&lt;img src=&quot;\S*?&quot; alt=&quot;(%s)&quot; title=&quot;\1&quot; class=&quot;emoji&quot; width=&quot;20&quot; height=&quot;20&quot; draggable=&quot;false&quot; \/&gt;/',
			self::SHORTCODE
		);

		if ( ! \preg_match_all( $pattern, $text, $matches ) ) {
			return $text;
		}

		// Look up only the emoji the text names.
		$shortcodes   = \array_map(
			static function ( $name ) {
				return \sprintf( ':%s:', $name );
			},
			$matches[1]
		);
		$replacements = self::get_replacements( $emoji_json, \implode( ' ', $shortcodes ) );
		if ( empty( $replacements ) ) {
			return $text;
		}

		$allowed_html = self::get_kses_allowed_html();

		return \preg_replace_callback(
			$pattern,
			static function ( $found ) use ( $replacements, $allowed_html ) {
				$img = $replacements[ \strtolower( $found[1] ) ] ?? null;

				if ( null === $img ) {
					return $found[0];
				}

				// An img the rules reject leaves its shortcode, so the name stays readable.
				return \wp_kses( $img, $allowed_html ) ?: \sprintf( ':%s:', $found[1] );
			},
			$text
		) ?? $text;
	}

	/**
	 * Turn emoji blocks inside a tag or an HTML comment back into their shortcode text.
	 *
	 * Emoji blocks only belong in text, which is where wrap_in_content() puts them. This runs
	 * before the blocks are rendered: for comments in Comment::render_blocks(), for posts before
	 * do_blocks().
	 *
	 * @since unreleased
	 *
	 * @param string $content The content.
	 *
	 * @return string The content with emoji blocks only in text.
	 */
	public static function remove_blocks_in_tags( $content ) {
		if ( ! \is_string( $content ) || ! \str_contains( $content, '<!-- wp:activitypub/emoji' ) ) {
			return $content;
		}

		// Each block becomes a placeholder without `<` or `>`, so splitting the HTML does not see the block comments.
		$token  = \md5( $content );
		$blocks = array();
		$text   = array();
		$marked = \preg_replace_callback(
			'#<!-- wp:activitypub/emoji\s[^>]*[^/]-->([^<]*)<!-- /wp:activitypub/emoji -->#',
			static function ( $matches ) use ( $token, &$blocks, &$text ) {
				$key            = \sprintf( '%1$s%2$d%1$s', $token, \count( $blocks ) );
				$blocks[ $key ] = $matches[0];
				$text[ $key ]   = $matches[1];
				return $key;
			},
			$content
		);

		if ( null === $marked || empty( $blocks ) ) {
			return $content;
		}

		// Placeholders in tags and comments become text, the ones left are blocks again.
		return \strtr( \wp_replace_in_html_tags( $marked, $text ), $blocks );
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
	 * Replace emoji shortcodes in the text between tags.
	 *
	 * Scans for `:shortcode:` with a fixed pattern and looks each one up, case-insensitively, so
	 * the pattern does not grow with the emoji. A replacement is markup, so it only goes into
	 * text: a shortcode inside a tag or an HTML comment stays as it is. The text is split the way
	 * kses splits HTML. In plain text (a name), a `<` starts what the split takes for a tag, and
	 * shortcodes after it stay as text.
	 *
	 * @since unreleased Only replaces shortcodes outside of tags and HTML comments.
	 *
	 * @param string $text         The text to process.
	 * @param array  $replacements The replacements, keyed by the lowercase shortcode without colons.
	 *
	 * @return string The text with the shortcodes replaced. A part PCRE bails on stays unchanged.
	 */
	private static function replace_names( $text, $replacements ) {
		if ( empty( $replacements ) ) {
			return $text;
		}

		// A shortcode needs a boundary on both sides: no letter, digit or colon next to it.
		$pattern  = \sprintf( '/(?<![\p{L}\p{N}:]):(%s):(?![\p{L}\p{N}:])/u', self::SHORTCODE );
		$callback = static function ( $matches ) use ( $replacements ) {
			return $replacements[ \strtolower( $matches[1] ) ] ?? $matches[0];
		};

		$parts = \wp_html_split( $text );
		foreach ( $parts as $i => $part ) {
			// Tags and comments start with `<`, text never does.
			if ( \str_starts_with( $part, '<' ) ) {
				continue;
			}

			$parts[ $i ] = \preg_replace_callback( $pattern, $callback, $part ) ?? $part;
		}

		return \implode( '', $parts );
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
