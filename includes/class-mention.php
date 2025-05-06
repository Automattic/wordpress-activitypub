<?php
/**
 * Mention class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_Error;

/**
 * ActivityPub Mention Class.
 *
 * @author Alex Kirk
 */
class Mention {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'the_content', array( self::class, 'the_content' ), 99 );
		\add_filter( 'comment_text', array( self::class, 'the_content' ) );
		\add_filter( 'activitypub_extra_field_content', array( self::class, 'the_content' ) );
		\add_filter( 'activitypub_extract_mentions', array( self::class, 'extract_mentions' ), 99, 2 );
		\add_filter( 'activitypub_activity_object_array', array( self::class, 'filter_activity_object' ), 99 );
	}

	/**
	 * Filter only the activity object and replace summery it with URLs
	 * add tag to user.
	 *
	 * @param array $object_array Array of activity.
	 *
	 * @return array The activity object array.
	 */
	public static function filter_activity_object( $object_array ) {
		if ( ! empty( $object_array['summary'] ) ) {
			$object_array['summary'] = self::the_content( $object_array['summary'] );
		}

		if ( ! empty( $object_array['content'] ) ) {
			$object_array['content'] = self::the_content( $object_array['content'] );
		}

		return $object_array;
	}

	/**
	 * Filter to replace the mentions in the content with links.
	 *
	 * @param string $the_content The post content.
	 *
	 * @return string The filtered post-content.
	 */
	public static function the_content( $the_content ) {
		return enrich_content_data( $the_content, '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/', array( self::class, 'replace_with_links' ) );
	}

	/**
	 * A callback for preg_replace to build the user links.
	 *
	 * @param array $result The preg_match results.
	 *
	 * @return string The final string.
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

			return \sprintf( '<a rel="mention" class="u-url mention" href="%1$s">@%2$s</a>', esc_url( $url ), esc_html( $username ) );
		}

		return $result[0];
	}

	/**
	 * Get the Inboxes for the mentioned Actors.
	 *
	 * @param array $mentioned The list of Actors that were mentioned.
	 *
	 * @return array The list of Inboxes.
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
	 * Get the inbox from the Remote-Profile of a mentioned Actor.
	 *
	 * @param string $actor The Actor URL.
	 *
	 * @return string|WP_Error The Inbox-URL or WP_Error if not found.
	 */
	public static function get_inbox_by_mentioned_actor( $actor ) {
		$metadata = get_remote_metadata_by_actor( $actor );

		if ( \is_wp_error( $metadata ) ) {
			return $metadata;
		}

		if ( isset( $metadata['endpoints']['sharedInbox'] ) ) {
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
	 * @param array  $mentions     The already found mentions.
	 * @param string $post_content The post content.
	 *
	 * @return array The discovered mentions.
	 */
	public static function extract_mentions( $mentions, $post_content ) {
		\preg_match_all( '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/i', $post_content, $matches );
		foreach ( $matches[0] as $match ) {
			$link = Webfinger::resolve( $match );
			if ( ! is_wp_error( $link ) ) {
				$mentions[ $match ] = $link;
			}
		}
		return \array_unique( $mentions );
	}
}
