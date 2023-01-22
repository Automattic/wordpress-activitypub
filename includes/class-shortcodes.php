<?php
namespace Activitypub;

class Shortcodes {
	/**
	 * The post object we're currently working on
	 *
	 * @var WP_Post  $post    A WordPress Post Object
	 */
	private $post;

	/**
	 * Class constructor, registering WordPress then shortcodes
	 *
	 * @param WP_Post  $post    A WordPress Post Object
	 */
	public function __construct( $post = null ) {
		if( $post == null ) {
			$post = \get_post();
		}

		if( \is_object( $post ) && ! $post instanceof WP_POST ) {
			$this->post = $post;
		} else {
			$this->post = false;
		}

		foreach( get_class_methods( $this ) as $shortcode ) {
			if( strpos( $shortcode, '__' ) !== 0 ) {
				add_shortcode( 'ap_' . $shortcode, array( $this, $shortcode ) );
			}
		}
	}

	/**
	 * Generates output for the ap_hashtags shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function hashtags( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$tags = \get_the_tags( $this->post->ID );

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
	 * Generates output for the ap_title shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function title( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		return \get_the_title( $this->post->ID );;
	}

	/**
	 * Generates output for the ap_excerpt shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function excerpt( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$length = ACTIVITYPUB_EXCERPT_LENGTH;

		if( is_array( $atts ) && array_key_exists( 'length', $atts ) ) {
			$length = intval( $atts['length'] );
		}

		if( $length == 0 ) { $length = ACTIVITYPUB_EXCERPT_LENGTH; }

		$excerpt = \get_post_field( 'post_excerpt', $this->post );

		if ( '' === $excerpt ) {

			$content = \get_post_field( 'post_content', $this->post );

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

	/**
	 * Generates output for the ap_content shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function content( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$content = \get_post_field( 'post_content', $this->post );

		return \apply_filters( 'the_content', $content );
	}

	/**
	 * Generates output for the ap_permalink shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function permalink( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		return \sprintf( '<a href="%1$s">%1$s</a>', \esc_url( \get_permalink( $this->post->ID ) ) );
	}

	/**
	 * Generates output for the ap_shortlink shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function shortlink( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		return \sprintf( '<a href="%1$s">%1$s</a>', \esc_url( \wp_get_shortlink( $this->post->ID ) ) );
	}

	/**
	 * Generates output for the ap_thumbnail shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function thumbnail( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$image = \get_the_post_thumbnail_url( $this->post->ID, 'thumbnail' );

		if ( ! $image ) {
			return '';
		}

		return $image;
	}

	/**
	 * Generates output for the ap_image shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function image( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$size = 'full';

		if( is_array( $atts ) && array_key_exists( 'size', $atts ) ) {
			$registered_sizes = wp_get_registered_image_subsizes();

			if( array_key_exists( $atts['size'], $registered_sizes ) ) {
				$size = intval( $atts['size'] );
			}
		}

		if( ! $size ) { $size = 'full'; }

		$image = \get_the_post_thumbnail_url( $this->post->ID, $size );

		if ( ! $image ) {
			return '';
		}

		return $image;
	}

	/**
	 * Generates output for the ap_hashcats shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function hashcats( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$categories = \get_the_category( $this->post->ID );

		if ( ! $categories ) {
			return '';
		}

		$hash_tags = array();

		foreach ( $categories as $category ) {
			$hash_tags[] = \sprintf( '<a rel="tag" class="u-tag u-category" href="%s">#%s</a>', \get_category_link( $category ), $category->slug );
		}

		return \implode( ' ', $hash_tags );
	}

	/**
	 * Generates output for the ap_author shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function author( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$name = \get_the_author_meta( 'display_name', $this->post->post_author );

		if ( ! $name ) {
			return '';
		}

		return $name;
	}

	/**
	 * Generates output for the ap_authorurl shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function authorurl( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$url = \get_the_author_meta( 'user_url', $this->post->post_author );

		if ( ! $url ) {
			return '';
		}

		return $url;
	}

	/**
	 * Generates output for the ap_blogurl shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function blogurl( $atts, $content, $tag ) {
		return \get_bloginfo('url');
	}

	/**
	 * Generates output for the ap_blogname shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function blogname( $atts, $content, $tag ) {
		return \get_bloginfo('name');
	}

	/**
	 * Generates output for the ap_blogdesc shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function blogdesc( $atts, $content, $tag ) {
		return \get_bloginfo('description');
	}

	/**
	 * Generates output for the ap_date shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function date( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$datetime = \get_post_datetime( $this->post );
		$dateformat = \get_option( 'date_format' );
		$timeformat = \get_option( 'time_format' );

		$date = $datetime->format( $dateformat );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Generates output for the ap_time shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function time( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$datetime = \get_post_datetime( $this->post );
		$dateformat = \get_option( 'date_format' );
		$timeformat = \get_option( 'time_format' );

		$date = $datetime->format( $timeformat );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Generates output for the ap_datetime shortcode
	 *
	 * @param array  $atts 		shortcode attributes
	 * @param string $content 	shortcode content
	 * @param string $tag 		shortcode tag name
	 *
	 * @return string
	 */
	public function datetime( $atts, $content, $tag ) {
		if( $this->post === false ) {
			return '';
		}

		$datetime = \get_post_datetime( $this->post );
		$dateformat = \get_option( 'date_format' );
		$timeformat = \get_option( 'time_format' );

		$date = $datetime->format( $dateformat . ' @ ' . $timeformat );

		if ( ! $date ) {
			return '';
		}

		return $date;
	}

}