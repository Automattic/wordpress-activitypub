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
		if ( '1' === \get_option( 'activitypub_use_hashtags', '1' ) ) {
			\add_filter( 'wp_insert_post', array( self::class, 'insert_post' ), 10, 2 );
			\add_filter( 'the_content', array( self::class, 'the_content' ), 10, 2 );
		}
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
			if ( preg_match( '#^<(/)?([a-z-]+)\b[^>]*>$#i', $chunk, $m ) ) {
				$tag = strtolower( $m[2] );
				if ( '/' === $m[1] ) {
					$i = array_search( $tag, $tag_stack );
					if ( false !== $i ) {
						$tag_stack = array_slice( $tag_stack, 0, $i );
					}
				} else {
					$tag_stack[] = $tag;
				}

				$in_protected_tag = count( array_intersect( $tag_stack, $protected_tags ) );
				$content_with_links .= $chunk;
				continue;
			}

			if ( $in_protected_tag ) {
				$content_with_links .= $chunk;
				continue;
			}

			$content_with_links .= \preg_replace_callback( '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', array( '\Activitypub\Hashtag', 'replace_with_links' ), $chunk );
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
		$tag = $result[1];
		$tag_object = \get_term_by( 'name', $tag, 'post_tag' );

		if ( $tag_object ) {
			$link = \get_term_link( $tag_object, 'post_tag' );
			return \sprintf( '<a rel="tag" class="hashtag u-tag u-category" href="%s">#%s</a>', $link, $tag );
		}

		return '#' . $tag;
	}
}
