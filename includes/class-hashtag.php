<?php
namespace Activitypub;

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
		if ( '1' === get_option( 'activitypub_use_hashtags', '1' ) ) {
			add_filter( 'wp_insert_post', array( '\Activitypub\Hashtag', 'insert_post' ), 99, 2 );
			add_filter( 'the_content', array( '\Activitypub\Hashtag', 'the_content' ), 99, 2 );
		}
		if ( '1' === get_option( 'activitypub_add_tags_as_hashtags', '1' ) ) {
			add_filter( 'activitypub_the_summary', array( '\Activitypub\Hashtag', 'add_hashtags_to_content' ), 10, 2 );
			add_filter( 'activitypub_the_content', array( '\Activitypub\Hashtag', 'add_hashtags_to_content' ), 10, 2 );
		}
	}

	/**
	 * Filter to save #tags as real WordPress tags
	 *
	 * @param int $id the rev-id
	 * @param array $data the post-data as array
	 */
	public static function insert_post( $id, $data ) {
		if ( preg_match_all( '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', $data->post_content, $match ) ) {
			$tags = implode( ', ', $match[2] );

			wp_add_post_tags( $data->post_parent, $tags );
		}

		return $id;
	}

	/**
	 * Filter to replace the #tags in the content with links
	 *
	 * @param string $the_content the post-content
	 */
	public static function the_content( $the_content ) {
		$the_content = preg_replace_callback( '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', array( '\Activitypub\Hashtag', 'replace_with_links' ), $the_content );

		return $the_content;
	}

	/**
	 * A callback for preg_replace to build the term links
	 *
	 * @param array $result the preg_match results
	 * @return string the final string
	 */
	public static function replace_with_links( $result ) {
		$tag = $result[2];
		$space = $result[1];
		$tag_object = get_term_by( 'name', $result[2], 'post_tag' );

		if ( $tag_object ) {
			$link = get_term_link( $tag_object, 'post_tag' );
			return "$space<a href='$link' rel='tag'>#$tag</a>";
		}

		return $space . '#' . $tag;
	}

	/**
	 * Adds all tags as hashtags to the post/summary content
	 *
	 * @param string  $content
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public static function add_hashtags_to_content( $content, $post ) {
		$tags = get_the_tags( $post->ID );

		if ( ! $tags ) {
			return $content;
		}

		$hash_tags = array();

		foreach ( $tags as $tag ) {
			$hash_tags[] = sprintf( '<a rel="tag" class="u-tag u-category" href="%s">#%s</a>', get_tag_link( $tag ), $tag->slug );
		}

		return $content . '<p>' . implode( ' ', $hash_tags ) . '</p>';
	}
}
