<?php
namespace Activitypub;

use function Activitypub\esc_hashtag;

class Shortcodes {
	/**
	 * Register the shortcodes
	 */
	public static function register() {
		foreach ( get_class_methods( self::class ) as $shortcode ) {
			if ( 'init' !== $shortcode ) {
				add_shortcode( 'ap_' . $shortcode, array( self::class, $shortcode ) );
			}
		}
	}

	/**
	 * Unregister the shortcodes
	 */
	public static function unregister() {
		foreach ( get_class_methods( self::class ) as $shortcode ) {
			if ( 'init' !== $shortcode ) {
				remove_shortcode( 'ap_' . $shortcode );
			}
		}
	}

	/**
	 * Generates output for the 'ap_hashtags' shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post tags as hashtags.
	 */
	public static function hashtags( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$tags = \get_the_tags( $item->ID );

		if ( ! $tags ) {
			return '';
		}

		$hash_tags = array();

		foreach ( $tags as $tag ) {
			$hash_tags[] = \sprintf(
				'<a rel="tag" class="hashtag u-tag u-category" href="%s">%s</a>',
				\esc_url( \get_tag_link( $tag ) ),
				esc_hashtag( $tag->name )
			);
		}

		return \implode( ' ', $hash_tags );
	}

	/**
	 * Generates output for the 'ap_title' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post title.
	 */
	public static function title( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		return \wp_strip_all_tags( \get_the_title( $item->ID ), true );
	}

	/**
	 * Generates output for the 'ap_excerpt' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post excerpt.
	 */
	public static function excerpt( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$atts = shortcode_atts(
			array( 'length' => ACTIVITYPUB_EXCERPT_LENGTH ),
			$atts,
			$tag
		);

		$excerpt_length = intval( $atts['length'] );

		if ( 0 === $excerpt_length ) {
			$excerpt_length = ACTIVITYPUB_EXCERPT_LENGTH;
		}

		$excerpt = \get_post_field( 'post_excerpt', $item );

		if ( '' === $excerpt ) {

			$content = \get_post_field( 'post_content', $item );

			// An empty string will make wp_trim_excerpt do stuff we do not want.
			if ( '' !== $content ) {
				$excerpt = \strip_shortcodes( $content );

				/** This filter is documented in wp-includes/post-template.php */
				$excerpt = \apply_filters( 'the_content', $excerpt );
				$excerpt = \str_replace( ']]>', ']]&gt;', $excerpt );
			}
		}

		// Strip out any remaining tags.
		$excerpt = \wp_strip_all_tags( $excerpt );

		$excerpt_more = \apply_filters( 'activitypub_excerpt_more', ' [&hellip;]' );
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

	/**
	 * Generates output for the 'ap_content' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post content.
	 */
	public static function content( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		// prevent inception
		remove_shortcode( 'ap_content' );

		$atts = shortcode_atts(
			array( 'apply_filters' => 'yes' ),
			$atts,
			$tag
		);

		$content = \get_post_field( 'post_content', $item );

		if ( 'yes' === $atts['apply_filters'] ) {
			$content = \apply_filters( 'the_content', $content );
		} else {
			$content = do_blocks( $content );
			$content = wptexturize( $content );
			$content = wp_filter_content_tags( $content );
		}

		// replace script and style elements
		$content = \preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $content );
		$content = strip_shortcodes( $content );
		$content = \trim( \preg_replace( '/[\n\r\t]/', '', $content ) );

		add_shortcode( 'ap_content', array( 'Activitypub\Shortcodes', 'content' ) );

		return $content;
	}

	/**
	 * Generates output for the 'ap_permalink' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post permalink.
	 */
	public static function permalink( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'type' => 'url',
			),
			$atts,
			$tag
		);

		if ( 'url' === $atts['type'] ) {
			return \esc_url( \get_permalink( $item->ID ) );
		}

		return \sprintf(
			'<a href="%1$s">%1$s</a>',
			\esc_url( \get_permalink( $item->ID ) )
		);
	}

	/**
	 * Generates output for the 'ap_shortlink' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post shortlink.
	 */
	public static function shortlink( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'type' => 'url',
			),
			$atts,
			$tag
		);

		if ( 'url' === $atts['type'] ) {
			return \esc_url( \wp_get_shortlink( $item->ID ) );
		}

		return \sprintf(
			'<a href="%1$s">%1$s</a>',
			\esc_url( \wp_get_shortlink( $item->ID ) )
		);
	}

	/**
	 * Generates output for the 'ap_image' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string
	 */
	public static function image( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'type' => 'full',
			),
			$atts,
			$tag
		);

		$size = 'full';

		if ( in_array(
			$atts['type'],
			array( 'thumbnail', 'medium', 'large', 'full' ),
			true
		) ) {
			$size = $atts['type'];
		}

		$image = \get_the_post_thumbnail_url( $item->ID, $size );

		if ( ! $image ) {
			return '';
		}

		return \esc_url( $image );
	}

	/**
	 * Generates output for the 'ap_hashcats' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post categories as hashtags.
	 */
	public static function hashcats( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$categories = \get_the_category( $item->ID );

		if ( ! $categories ) {
			return '';
		}

		$hash_tags = array();

		foreach ( $categories as $category ) {
			$hash_tags[] = \sprintf(
				'<a rel="tag" class="hashtag u-tag u-category" href="%s">%s</a>',
				\esc_url( \get_category_link( $category ) ),
				esc_hashtag( $category->name )
			);
		}

		return \implode( ' ', $hash_tags );
	}

	/**
	 * Generates output for the 'ap_author' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The author name.
	 */
	public static function author( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$author_id = \get_post_field( 'post_author', $item->ID );
		$name = \get_the_author_meta( 'display_name', $author_id );

		if ( ! $name ) {
			return '';
		}

		return wp_strip_all_tags( $name );
	}

	/**
	 * Generates output for the 'ap_authorurl' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The author URL.
	 */
	public static function authorurl( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$author_id = \get_post_field( 'post_author', $item->ID );
		$url = \get_the_author_meta( 'user_url', $author_id );

		if ( ! $url ) {
			return '';
		}

		return \esc_url( $url );
	}

	/**
	 * Generates output for the 'ap_blogurl' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The site URL.
	 */
	public static function blogurl( $atts, $content, $tag ) {
		return \esc_url( \get_bloginfo( 'url' ) );
	}

	/**
	 * Generates output for the 'ap_blogname' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string
	 */
	public static function blogname( $atts, $content, $tag ) {
		return \wp_strip_all_tags( \get_bloginfo( 'name' ) );
	}

	/**
	 * Generates output for the 'ap_blogdesc' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The site description.
	 */
	public static function blogdesc( $atts, $content, $tag ) {
		return \wp_strip_all_tags( \get_bloginfo( 'description' ) );
	}

	/**
	 * Generates output for the 'ap_date' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post date.
	 */
	public static function date( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$datetime = \get_post_datetime( $item );
		$dateformat = \get_option( 'date_format' );
		$timeformat = \get_option( 'time_format' );

		$date = $datetime->format( $dateformat );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Generates output for the 'ap_time' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post time.
	 */
	public static function time( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$datetime = \get_post_datetime( $item );
		$dateformat = \get_option( 'date_format' );
		$timeformat = \get_option( 'time_format' );

		$date = $datetime->format( $timeformat );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Generates output for the 'ap_datetime' Shortcode
	 *
	 * @param array  $atts    The Shortcode attributes.
	 * @param string $content The ActivityPub post-content.
	 * @param string $tag     The tag/name of the Shortcode.
	 *
	 * @return string The post date/time.
	 */
	public static function datetime( $atts, $content, $tag ) {
		$item = self::get_item();

		if ( ! $item ) {
			return '';
		}

		$datetime = \get_post_datetime( $item );
		$dateformat = \get_option( 'date_format' );
		$timeformat = \get_option( 'time_format' );

		$date = $datetime->format( $dateformat . ' @ ' . $timeformat );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Get a WordPress item to federate.
	 *
	 * Checks if item (WP_Post) is "public", a supported post type
	 * and not password protected.
	 *
	 * @return null|WP_Post The WordPress item.
	 */
	protected static function get_item() {
		$post = \get_post();

		if ( ! $post ) {
			return null;
		}

		if ( 'publish' !== \get_post_status( $post ) ) {
			return null;
		}

		if ( \post_password_required( $post ) ) {
			return null;
		}

		if ( ! \in_array( \get_post_type( $post ), \get_post_types_by_support( 'activitypub' ), true ) ) {
			return null;
		}

		return $post;
	}
}
