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
		if ( '1' === \get_option( 'activitypub_use_hashtags', '0' ) ) {
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
		if ( ! empty( $activity['summary'] ) && is_actor( $activity ) ) {
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
		// Check if the post supports ActivityPub.
		if ( ! \post_type_supports( \get_post_type( $post ), 'activitypub' ) ) {
			return;
		}

		// Check if the (custom) post supports tags.
		$taxonomies = \get_object_taxonomies( $post );
		if ( ! in_array( 'post_tag', $taxonomies, true ) ) {
			return;
		}

		$content = $post->post_content . "\n" . $post->post_excerpt;
		$content = self::extract_text_outside_protected_tags( $content );

		$tags = array();
		if ( \preg_match_all( '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', $content, $match ) ) {
			$tags = \array_unique( $match[1] );
		}

		\wp_add_post_tags( $post->ID, \implode( ', ', $tags ) );
	}

	/**
	 * Extract text content from outside protected HTML elements.
	 *
	 * Uses WP_HTML_Tag_Processor to properly parse HTML and skip content inside
	 * protected tags, matching the behavior of enrich_content_data().
	 *
	 * @param string $content The HTML content to process.
	 *
	 * @return string Text content from non-protected areas only.
	 */
	private static function extract_text_outside_protected_tags( $content ) {
		$processor = new \WP_HTML_Tag_Processor( $content );

		/*
		 * Do not process content inside protected tags.
		 *
		 * Note: SCRIPT, STYLE, and TEXTAREA are "atomic" elements in
		 * WP_HTML_Tag_Processor, meaning their content is bundled with the tag
		 * token and won't appear as separate #text nodes. Because of this they
		 * do not need to be listed in $protected_tags: their inner text is
		 * never surfaced as #text tokens for us to process.
		 * See https://github.com/WordPress/wordpress-develop/blob/0fb3bb29596918864d808d156268a2df63c83620/src/wp-includes/html-api/class-wp-html-tag-processor.php#L276
		 */
		$protected_tags   = array( 'PRE', 'CODE', 'A' );
		$tag_stack        = array();
		$filtered_content = '';

		while ( $processor->next_token() ) {
			$token_type = $processor->get_token_type();

			if ( '#tag' === $token_type ) {
				$tag_name = $processor->get_tag();

				if ( $processor->is_tag_closer() ) {
					// Closing tag: remove from stack.
					$i = \array_search( $tag_name, $tag_stack, true );
					if ( false !== $i ) {
						$tag_stack = \array_slice( $tag_stack, 0, $i );
					}
				} elseif ( \in_array( $tag_name, $protected_tags, true ) ) {
					// Opening tag: add to stack.
					$tag_stack[] = $tag_name;
				}
			} elseif ( '#text' === $token_type && empty( $tag_stack ) ) {
				// Only include text chunks that are outside protected tags.
				$filtered_content .= $processor->get_modifiable_text();
			}
		}

		return $filtered_content;
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
