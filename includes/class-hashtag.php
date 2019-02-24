<?php
namespace Activitypub;

/**
 * ActivityPub Hashtag Class
 */
class Hashtag {
	public static function init() {
		add_filter( 'wp_insert_post', array( '\Activitypub\Hashtag', 'insert_post' ), 99, 2 );
		add_filter( 'the_content', array( '\Activitypub\Hashtag', 'the_content' ), 99, 2 );
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
}
