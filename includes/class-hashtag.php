<?php
namespace Activitypub;

use function Activitypub\enrich_content_data;

/**
 * ActivityPub Hashtag Class
 *
 * @author Matthias Pfefferle
 */
class Hashtag {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		if ( '1' === \get_option( 'activitypub_use_hashtags', '1' ) ) {
			\add_action( 'wp_insert_post', array( self::class, 'insert_post' ), 10, 2 );
			\add_filter( 'the_content', array( self::class, 'the_content' ), 10, 1 );
			\add_filter( 'activitypub_activity_object_array', array( self::class, 'filter_activity_object' ), 99 );
		}
	}

	/**
	 * Filter only the activity object and replace summery it with URLs
	 *
	 * @param $object_array array of activity
	 *
	 * @return array the activity object array
	 */
	public static function filter_activity_object( $object_array ) {
		if ( empty( $object_array['summary'] ) ) {
			return $object_array;
		}

		\preg_match_all( '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/', $object_array['summary'], $matches );
		foreach ( $matches[0] as $match_id => $match ) {
			$tag_object = \get_term_by( 'name', $matches[1][ $match_id ], 'post_tag' );
			if ( ! $tag_object ) {
				$tag_object = \get_term_by( 'name', $matches[1][ $match_id ], 'category' );
			}

			if ( $tag_object ) {
				$link                  = \get_term_link( $tag_object, 'post_tag' );
				$object_array['tag'][] = [
					'type' => 'Hashtag',
					'href' => $link,
					'name' => $match,
				];
			}
		}

		$object_array['summary'] = self::the_content( $object_array['summary'] );

		return $object_array;
	}

	/**
	 * Filter to save #tags as real WordPress tags
	 *
	 * @param int     $id the rev-id
	 * @param WP_Post $post the post
	 *
	 * @return
	 */
	public static function insert_post( $id, $post ) {
		if ( \preg_match_all( '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', $post->post_content, $match ) ) {
			$tags = \implode( ', ', $match[1] );

			\wp_add_post_tags( $post->post_parent, $tags );
		}

		return $id;
	}

	/**
	 * Filter to replace the #tags in the content with links
	 *
	 * @param string $the_content the post-content
	 *
	 * @return string the filtered post-content
	 */
	public static function the_content( $the_content ) {
		return enrich_content_data( $the_content, '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', array( self::class, 'replace_with_links' ) );
	}

	/**
	 * A callback for preg_replace to build the term links
	 *
	 * @param array $result the preg_match results
	 * @return string the final string
	 */
	public static function replace_with_links( $result ) {
		$tag = $result[1];
		$tag_object = \get_term_by( 'name', $tag, 'post_tag' );
		if ( ! $tag_object ) {
			$tag_object = \get_term_by( 'name', $tag, 'category' );
		}

		if ( $tag_object ) {
			$link = \get_term_link( $tag_object, 'post_tag' );
			return \sprintf( '<a rel="tag" class="hashtag u-tag u-category" href="%s">#%s</a>', $link, $tag );
		}

		return '#' . $tag;
	}
}
