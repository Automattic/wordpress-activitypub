<?php
namespace Activitypub;

class Shortcode {
	public static function init() {
		add_shortcode( 'ap_hashtags', array( 'Activitypub\Shortcode', 'ap_hashtags' ) );
		add_shortcode( 'ap_excerpt', array( 'Activitypub\Shortcode', 'ap_excerpt' ) );
	}

	/**
	 * Adds all tags as hashtags to the post/summary content
	 *
	 * @param string  $content
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public static function ap_hashtags( $content, $ignore_html = false ) {
		$post = get_post();
		$tags = \get_the_tags( $post->ID );

		if ( ! $tags ) {
			return '';
		}

		$hash_tags = array();

		foreach ( $tags as $tag ) {
			$hash_tags[] = \sprintf( '<a rel="tag" class="u-tag u-category" href="%s">#%s</a>', \get_tag_link( $tag ), $tag->slug );
		}

		return \implode( ' ', $hash_tags );
	}

	/**
	 * Get the excerpt for a post for use outside of the loop.
	 *
	 * @param int     Optional excerpt length.
	 *
	 * @return string The excerpt.
	 */
	public static function ap_excerpt( $content, $ignore_html = false ) {
		$excerpt_length = 400;
		$post = get_post();

		$excerpt = \get_post_field( 'post_excerpt', $post );

		if ( '' === $excerpt ) {

			$content = \get_post_field( 'post_content', $post );

			// An empty string will make wp_trim_excerpt do stuff we do not want.
			if ( '' !== $content ) {

				$excerpt = \strip_shortcodes( $content );

				/** This filter is documented in wp-includes/post-template.php */
				$excerpt = \apply_filters( 'the_content', $excerpt );
				$excerpt = \str_replace( ']]>', ']]>', $excerpt );

			}
		}

		// Strip out any remaining tags.
		$excerpt = \wp_strip_all_tags( $excerpt );

		/** This filter is documented in wp-includes/formatting.php */
		$excerpt_more = \apply_filters( 'excerpt_more', ' [...]' );
		$excerpt_more_len = strlen( $excerpt_more );

		// We now have a excerpt, but we need to check it's length, it may be longer than we want for two reasons:
		//
		//   * The user has entered a manual excerpt which is longer that what we want.
		//   * No manual excerpt exists so we've used the content which might be longer than we want.
		//
		// Either way, let's trim it up if we need too.  Also, don't forget to take into account the more indicator
		// as part of the total length.
		//

		// Setup a variable to hold the current excerpts length.
		$current_excerpt_length = strlen( $excerpt );

		// Setup a variable to keep track of our target length.
		$target_excerpt_length = $excerpt_length - $excerpt_more_len;

		// Setup a variable to keep track of the current max length.
		$current_excerpt_max = $target_excerpt_length;

		// This is a loop since we can't calculate word break the string after 'the_excpert' filter has run (we would break
		// all kinds of html tags), so we have to cut the excerpt down a bit at a time until we hit our target length.
		while ( $current_excerpt_length > $target_excerpt_length && $current_excerpt_max > 0 ) {
			// Trim the excerpt based on wordwrap() positioning.
			// Note: we're using <br> as the linebreak just in case there are any newlines existing in the excerpt from the user.
			//       There won't be any <br> left after we've run wp_strip_all_tags() in the code above, so they're
			//       safe to use here.  It won't be included in the final excerpt as the substr() will trim it off.
			$excerpt = substr( $excerpt, 0, strpos( wordwrap( $excerpt, $current_excerpt_max, '<br>' ), '<br>' ) );

			// If something went wrong, or we're in a language that wordwrap() doesn't understand,
			// just chop it off and don't worry about breaking in the middle of a word.
			if ( strlen( $excerpt ) > $excerpt_length - $excerpt_more_len ) {
				$excerpt = substr( $excerpt, 0, $current_excerpt_max );
			}

			// Add in the more indicator.
			$excerpt = $excerpt . $excerpt_more;

			// Run it through the excerpt filter which will add some html tags back in.
			$excerpt_filtered = apply_filters( 'the_excerpt', $excerpt );

			// Now set the current excerpt length to this new filtered length.
			$current_excerpt_length = strlen( $excerpt_filtered );

			// Check to see if we're over the target length.
			if ( $current_excerpt_length > $target_excerpt_length ) {
				// If so, remove 20 characters from the current max and run the loop again.
				$current_excerpt_max = $current_excerpt_max - 20;
			}
		}

		return \apply_filters( 'the_excerpt', $excerpt );
	}
}
