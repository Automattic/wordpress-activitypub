<?php
namespace Activitypub;

use WP_Error;
use Activitypub\Webfinger;

use function Activitypub\object_to_uri;

/**
 * ActivityPub Mention Class
 *
 * @author Alex Kirk
 */
class Mention {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'the_content', array( self::class, 'the_content' ), 99, 1 );
		\add_filter( 'comment_text', array( self::class, 'the_content' ), 10, 1 );
		\add_filter( 'activitypub_extract_mentions', array( self::class, 'extract_mentions' ), 99, 2 );
		\add_filter(
			'activitypub_activity_object_array',
			function ( array $object_array ) {
				if ( ! empty( $object_array['summary'] ) ) {
					$object_array['summary'] = \preg_replace_callback( '/(http|https):\/\/([-a-zA-Z0-9@:;\/%_;\+.~#?&=]*)/i', 'Activitypub\replace_urls_with_html', $object_array['summary'] );
					$object_array['summary'] = self::the_content( $object_array['summary'] );
				}
				return $object_array;
			},
			99,
			1
		);
	}

	/**
	 * Filter to replace the mentions in the content with links
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
		foreach ( wp_html_split( $the_content ) as $chunk ) {
			if ( preg_match( '#^<!--[\s\S]*-->$#i', $chunk, $m ) ) {
				$content_with_links .= $chunk;
				continue;
			}

			if ( preg_match( '#^<(/)?([a-z-]+)\b[^>]*>$#i', $chunk, $m ) ) {
				$tag = strtolower( $m[2] );
				if ( '/' === $m[1] ) {
					// Closing tag.
					$i = array_search( $tag, $tag_stack );
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
			$content_with_links .= \preg_replace_callback( '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/', array( self::class, 'replace_with_links' ), $chunk );
		}

		return $content_with_links;
	}

	/**
	 * A callback for preg_replace to build the user links
	 *
	 * @param array $result the preg_match results
	 *
	 * @return string the final string
	 */
	public static function replace_with_links( $result ) {
		$metadata = get_remote_metadata_by_actor( $result[0] );

		if (
			! empty( $metadata ) &&
			! is_wp_error( $metadata ) &&
			( ! empty( $metadata['id'] ) || ! empty( $metadata['url'] ) )
		) {
			$username = ltrim( $result[0], '@' );
			if ( ! empty( $metadata['name'] ) ) {
				$username = $metadata['name'];
			}
			if ( ! empty( $metadata['preferredUsername'] ) ) {
				$username = $metadata['preferredUsername'];
			}

			$url = isset( $metadata['url'] ) ? object_to_uri( $metadata['url'] ) : object_to_uri( $metadata['id'] );

			return \sprintf( '<a rel="mention" class="u-url mention" href="%s">@<span>%s</span></a>', esc_url( $url ), esc_html( $username ) );
		}

		return $result[0];
	}

	/**
	 * Get the Inboxes for the mentioned Actors
	 *
	 * @param array $mentioned The list of Actors that were mentioned
	 *
	 * @return array The list of Inboxes
	 */
	public static function get_inboxes( $mentioned ) {
		$inboxes = array();

		foreach ( $mentioned as $actor ) {
			$inbox = self::get_inbox_by_mentioned_actor( $actor );

			if ( ! is_wp_error( $inbox ) && $inbox ) {
				$inboxes[] = $inbox;
			}
		}

		return $inboxes;
	}

	/**
	 * Get the inbox from the Remote-Profile of a mentioned Actor
	 *
	 * @param string $actor The Actor-URL
	 *
	 * @return string The Inbox-URL
	 */
	public static function get_inbox_by_mentioned_actor( $actor ) {
		$metadata = get_remote_metadata_by_actor( $actor );

		if ( \is_wp_error( $metadata ) ) {
			return $metadata;
		}

		if ( isset( $metadata['endpoints'] ) && isset( $metadata['endpoints']['sharedInbox'] ) ) {
			return $metadata['endpoints']['sharedInbox'];
		}

		if ( \array_key_exists( 'inbox', $metadata ) ) {
			return $metadata['inbox'];
		}

		return new WP_Error( 'activitypub_no_inbox', \__( 'No "Inbox" found', 'activitypub' ), $metadata );
	}

	/**
	 * Extract the mentions from the post_content.
	 *
	 * @param array  $mentions The already found mentions.
	 * @param string $post_content The post content.
	 *
	 * @return mixed The discovered mentions.
	 */
	public static function extract_mentions( $mentions, $post_content ) {
		\preg_match_all( '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/i', $post_content, $matches );
		foreach ( $matches[0] as $match ) {
			$link = Webfinger::resolve( $match );
			if ( ! is_wp_error( $link ) ) {
				$mentions[ $match ] = $link;
			}
		}
		return $mentions;
	}
}
