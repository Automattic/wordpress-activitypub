<?php
namespace Activitypub;

use WP_Error;

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
		\add_filter( 'the_content', array( self::class, 'the_content' ), 99, 2 );
		\add_filter( 'activitypub_extract_mentions', array( self::class, 'extract_mentions' ), 99, 2 );
	}

	/**
	 * Filter to replace the mentions in the content with links
	 *
	 * @param string $the_content the post-content
	 *
	 * @return string the filtered post-content
	 */
	public static function the_content( $the_content ) {
		$protected_tags = array();
		$protect = function ( $m ) use ( &$protected_tags ) {
			$c = \wp_rand( 100000, 999999 );
			$protect = '!#!#PROTECT' . $c . '#!#!';
			while ( isset( $protected_tags[ $protect ] ) ) {
				$c = \wp_rand( 100000, 999999 );
				$protect = '!#!#PROTECT' . $c . '#!#!';
			}
			$protected_tags[ $protect ] = $m[0];
			return $protect;
		};
		$the_content = preg_replace_callback(
			'#<!\[CDATA\[.*?\]\]>#is',
			$protect,
			$the_content
		);
		$the_content = preg_replace_callback(
			'#<(pre|code|textarea|style)\b[^>]*>.*?</\1[^>]*>#is',
			$protect,
			$the_content
		);
		$the_content = preg_replace_callback(
			'#<a.*?href=[^>]+>.*?</a>#i',
			$protect,
			$the_content
		);

		$the_content = preg_replace_callback(
			'#<img.*?[^>]+>#i',
			$protect,
			$the_content
		);

		$the_content = \preg_replace_callback( '/@' . ACTIVITYPUB_USERNAME_REGEXP . '/', array( self::class, 'replace_with_links' ), $the_content );
		$the_content = \str_replace( array_reverse( array_keys( $protected_tags ) ), array_reverse( array_values( $protected_tags ) ), $the_content );

		return $the_content;
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

		if ( ! is_wp_error( $metadata ) && ! empty( $metadata['url'] ) ) {
			$username = ltrim( $result[0], '@' );
			if ( ! empty( $metadata['name'] ) ) {
				$username = $metadata['name'];
			}
			if ( ! empty( $metadata['preferredUsername'] ) ) {
				$username = $metadata['preferredUsername'];
			}
			return \sprintf( '<a rel="mention" class="u-url mention" href="%s">@<span>%s</span></a>', esc_url( $metadata['url'] ), esc_html( $username ) );
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
