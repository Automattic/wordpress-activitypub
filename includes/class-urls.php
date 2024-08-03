<?php
namespace Activitypub;

/**
 * ActivityPub Summery Links Class
 */
class Urls {

	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'activitypub_activity_object_array', [ __CLASS__, 'filter_summary' ], 99 );
	}

	/**
	 * Filter only the summery and replace it with URLs
	 *
	 * @param $object_array array of activity
	 *
	 * @return array the activity object array
	 */
	public static function filter_summary( $object_array ) {
		if ( empty( $object_array['summary'] ) ) {
			return $object_array;
		}

		$object_array['summary'] = self::the_content( $object_array['summary'] );
		$object_array['summary'] = Mention::the_content( $object_array['summary'] );
		if ( '1' === \get_option( 'activitypub_use_hashtags', '1' ) ) {
			$object_array['summary'] = Hashtag::the_content( $object_array['summary'] );
		}

		return $object_array;
	}

	/**
	 * Filter to replace the URLS in the content with links
	 *
	 * @param string $the_content the post-content
	 *
	 * @return string the filtered post-content
	 */
	public static function the_content( $the_content ) {
		// small protection against execution timeouts: limit to 1 MB
		if ( mb_strlen( $the_content ) > MB_IN_BYTES ) {
			return $the_content;
		}
		$tag_stack = array();
		$protected_tags = array(
			'pre',
			'code',
			'textarea',
			'style',
			'a',
		);
		$content_with_links = '';
		$in_protected_tag = false;
		foreach ( \wp_html_split( $the_content ) as $chunk ) {
			if ( preg_match( '#^<!--[\s\S]*-->$#i', $chunk, $m ) ) {
				$content_with_links .= $chunk;
				continue;
			}

			if ( preg_match( '#^<(/)?([a-z-]+)\b[^>]*>$#i', $chunk, $m ) ) {
				$tag = strtolower( $m[2] );
				if ( '/' === $m[1] ) {
					// Closing tag.
					$i = array_search( $tag, $tag_stack, true );
					// We can only remove the tag from the stack if it is in the stack.
					if ( false !== $i ) {
						$tag_stack = array_slice( $tag_stack, 0, $i );
					}
				} else {
					// Opening tag, add it to the stack.
					$tag_stack[] = $tag;
				}

				// If we're in a protected tag, the tag_stack contains at least one protected tag string.
				// The protected tag state can only change when we encounter a start or end tag.
				$in_protected_tag = array_intersect( $tag_stack, $protected_tags );

				// Never inspect tags.
				$content_with_links .= $chunk;
				continue;
			}

			if ( $in_protected_tag ) {
				// Don't inspect a chunk inside an inspected tag.
				$content_with_links .= $chunk;
				continue;
			}

			// Only reachable when there is no protected tag in the stack.
			$content_with_links .= \preg_replace_callback( '/' . ACTIVITYPUB_URLS_REGEXP . '/i', [ __CLASS__, 'replace_with_links' ], $chunk );
		}

		return $content_with_links;
	}

	/**
	 * A callback for preg_replace to build the term links
	 *
	 * @param array $result the preg_match results
	 * @return string the final string
	 */
	public static function replace_with_links( $result ) {
		$parsed_url = \wp_parse_url( html_entity_decode( $result[0] ) );
		if ( ! $parsed_url ) {
			return $result[0];
		}

		$invisible_prefix = $parsed_url['scheme'] . '://';
		if ( ! empty( $parsed_url['user'] ) ) {
			$invisible_prefix .= $parsed_url['user'];
		}
		if ( ! empty( $parsed_url['pass'] ) ) {
			$invisible_prefix .= ':' . $parsed_url['pass'];
		}
		if ( ! empty( $parsed_url['user'] ) ) {
			$invisible_prefix .= '@';
		}

		$text_url = $parsed_url['host'];
		if ( ! empty( $parsed_url['port'] ) ) {
			$text_url .= ':' . $parsed_url['port'];
		}
		if ( ! empty( $parsed_url['path'] ) ) {
			$text_url .= $parsed_url['path'];
		}
		if ( ! empty( $parsed_url['query'] ) ) {
			$text_url .= '?' . $parsed_url['query'];
		}
		if ( ! empty( $parsed_url['fragment'] ) ) {
			$text_url .= '#' . $parsed_url['fragment'];
		}

		$invisible_prefix = \esc_html( $invisible_prefix );
		$display = \esc_html( \substr( $text_url, 0, 30 ) );
		$invisible_suffix = \esc_html( \substr( $text_url, 30 ) );
		if ( ! empty( $invisible_suffix ) ) {
			$display .= '&hellip;';
		}

		return '<a href="' . $result[0] . '" target="_blank" rel="nofollow noopener noreferrer" translate="no"><span class="invisible">' . $invisible_prefix . '</span>' . $display . '<span class="invisible">' . $invisible_suffix . '</span></a>';
	}
}
