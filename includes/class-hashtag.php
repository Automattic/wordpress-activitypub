<?php
/**
 * Hashtag Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * ActivityPub Hashtag Class.
 *
 * @author Matthias Pfefferle
 */
class Hashtag {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		if ( '1' === \get_option( 'activitypub_use_hashtags', '1' ) ) {
			\add_action( 'wp_insert_post', array( self::class, 'insert_post' ), 10, 2 );
			\add_filter( 'the_content', array( self::class, 'the_content' ) );
			\add_filter( 'activitypub_activity_object_array', array( self::class, 'filter_activity_object' ), 99 );
		}
	}

	/**
	 * Filter only the activity object and replace summery it with URLs.
	 *
	 * @param array $activity The activity object array.
	 *
	 * @return array The filtered activity object array.
	 */
	public static function filter_activity_object( $activity ) {
		/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		Only changed it for Person and Group as long is not merged: https://github.com/mastodon/mastodon/pull/28629
		*/
		if ( ! empty( $activity['summary'] ) && in_array( $activity['type'], array( 'Person', 'Group' ), true ) ) {
			$activity['summary'] = self::the_content( $activity['summary'] );
		}

		if ( ! empty( $activity['content'] ) ) {
			$activity['content'] = self::the_content( $activity['content'] );
		}

		return $activity;
	}

	/**
	 * Filter to save #tags as real WordPress tags.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function insert_post( $post_id, $post ) {
		$tags = array();

		// Skip hashtags in HTML attributes, like hex colors.
		$content = wp_strip_all_tags( $post->post_content . "\n" . $post->post_excerpt );

		if ( \preg_match_all( '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', $content, $match ) ) {
			$tags = array_unique( $match[1] );
		}

		\wp_add_post_tags( $post->ID, \implode( ', ', $tags ) );
	}

	/**
	 * Filter to replace the #tags in the content with links.
	 *
	 * @param string $the_content The post content.
	 *
	 * @return string The filtered post content.
	 */
	public static function the_content( $the_content ) {
		return enrich_content_data( $the_content, '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', array( self::class, 'replace_with_links' ) );
	}

	/**
	 * A callback for preg_replace to build the term links.
	 *
	 * @param array $result The preg_match results.
	 * @return string the final string
	 */
	public static function replace_with_links( $result ) {
		$tag        = $result[1];
		$tag_object = \get_term_by( 'name', $tag, 'post_tag' );
		if ( ! $tag_object ) {
			$tag_object = \get_term_by( 'name', $tag, 'category' );
		}

		if ( $tag_object ) {
			$link = \get_term_link( $tag_object, 'post_tag' );
			return \sprintf( '<a rel="tag" class="hashtag u-tag u-category" href="%s">#%s</a>', esc_url( $link ), $tag );
		}

		return '#' . $tag;
	}
}
