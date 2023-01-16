<?php
namespace Activitypub\Model;

/**
 * ActivityPub Post Class
 *
 * @author Matthias Pfefferle
 */
class Post {
	private $post;
	private $post_author;
	private $id;
	private $summary;
	private $content;
	private $attachments;
	private $tags;
	private $object_type;

	public function __construct( $post = null ) {
		$this->post = \get_post( $post );

		$this->post_author = $this->post->post_author;
		$this->id          = $this->generate_id();
		$this->summary     = $this->generate_the_title();
		$this->content     = $this->generate_the_content();
		$this->attachments = $this->generate_attachments();
		$this->tags        = $this->generate_tags();
		$this->object_type = $this->generate_object_type();
	}

	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
		}
	}

	public function to_array() {
		$post = $this->post;

		$array = array(
			'id' => $this->id,
			'type' => $this->object_type,
			'published' => \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $post->post_date_gmt ) ),
			'attributedTo' => \get_author_posts_url( $post->post_author ),
			'summary' => $this->summary,
			'inReplyTo' => null,
			'content' => $this->content,
			'contentMap' => array(
				\strstr( \get_locale(), '_', true ) => $this->content,
			),
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'attachment' => $this->attachments,
			'tag' => $this->tags,
		);

		return \apply_filters( 'activitypub_post', $array );
	}

	public function to_json() {
		return \wp_json_encode( $this->to_array(), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT );
	}

	public function generate_id() {
		$post = $this->post;

		if ( 'trash' === get_post_status( $post ) ) {
			$permalink = \get_post_meta( $post->ID, 'activitypub_canonical_url', true );
		} else {
			$permalink = \get_permalink( $post );
		}

		return $permalink;
	}

	public function generate_attachments() {
		$max_images = intval( \apply_filters( 'activitypub_max_image_attachments', \get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS ) ) );

		$images = array();

		// max images can't be negative or zero
		if ( $max_images <= 0 ) {
			return $images;
		}

		$id = $this->post->ID;

		$image_ids = array();

		// list post thumbnail first if this post has one
		if ( \function_exists( 'has_post_thumbnail' ) && \has_post_thumbnail( $id ) ) {
			$image_ids[] = \get_post_thumbnail_id( $id );
			$max_images--;
		}

		if ( $max_images > 0 ) {
			// then list any image attachments
			$query = new \WP_Query(
				array(
					'post_parent' => $id,
					'post_status' => 'inherit',
					'post_type' => 'attachment',
					'post_mime_type' => 'image',
					'order' => 'ASC',
					'orderby' => 'menu_order ID',
					'posts_per_page' => $max_images,
				)
			);
			foreach ( $query->get_posts() as $attachment ) {
				if ( ! \in_array( $attachment->ID, $image_ids, true ) ) {
					$image_ids[] = $attachment->ID;
				}
			}
		}

		$image_ids = \array_unique( $image_ids );

		// get URLs for each image
		foreach ( $image_ids as $id ) {
			$alt = \get_post_meta( $id, '_wp_attachment_image_alt', true );
			$thumbnail = \wp_get_attachment_image_src( $id, 'full' );
			$mimetype = \get_post_mime_type( $id );

			if ( $thumbnail ) {
				$image = array(
					'type' => 'Image',
					'url' => $thumbnail[0],
					'mediaType' => $mimetype,
				);
				if ( $alt ) {
					$image['name'] = $alt;
				}
				$images[] = $image;
			}
		}

		return $images;
	}

	public function generate_tags() {
		$tags = array();

		$post_tags = \get_the_tags( $this->post->ID );
		if ( $post_tags ) {
			foreach ( $post_tags as $post_tag ) {
				$tag = array(
					'type' => 'Hashtag',
					'href' => \get_tag_link( $post_tag->term_id ),
					'name' => '#' . $post_tag->slug,
				);
				$tags[] = $tag;
			}
		}

		return $tags;
	}

	/**
	 * Returns the as2 object-type for a given post
	 *
	 * @param string $type the object-type
	 * @param Object $post the post-object
	 *
	 * @return string the object-type
	 */
	public function generate_object_type() {
		if ( 'wordpress-post-format' !== \get_option( 'activitypub_object_type', 'note' ) ) {
			return \ucfirst( \get_option( 'activitypub_object_type', 'note' ) );
		}

		$post_type = \get_post_type( $this->post );
		switch ( $post_type ) {
			case 'post':
				$post_format = \get_post_format( $this->post );
				switch ( $post_format ) {
					case 'aside':
					case 'status':
					case 'quote':
					case 'note':
						$object_type = 'Note';
						break;
					case 'gallery':
					case 'image':
						$object_type = 'Image';
						break;
					case 'video':
						$object_type = 'Video';
						break;
					case 'audio':
						$object_type = 'Audio';
						break;
					default:
						$object_type = 'Article';
						break;
				}
				break;
			case 'page':
				$object_type = 'Page';
				break;
			case 'attachment':
				$mime_type = \get_post_mime_type();
				$media_type = \preg_replace( '/(\/[a-zA-Z]+)/i', '', $mime_type );
				switch ( $media_type ) {
					case 'audio':
						$object_type = 'Audio';
						break;
					case 'video':
						$object_type = 'Video';
						break;
					case 'image':
						$object_type = 'Image';
						break;
				}
				break;
			default:
				$object_type = 'Article';
				break;
		}

		return $object_type;
	}

	public function generate_the_content() {
		$post = $this->post;
		$content = $this->get_post_content_template();

		global $post;
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$content = \str_replace( '%title%', \get_the_title( $post->ID ), $content );
		$content = \str_replace( '%excerpt%', $this->get_the_post_excerpt(), $content );
		$content = \str_replace( '%content%', $this->get_the_post_content(), $content );
		$content = \str_replace( '%permalink%', $this->get_the_post_link( 'permalink' ), $content );
		$content = \str_replace( '%shortlink%', $this->get_the_post_link( 'shortlink' ), $content );
		$content = \str_replace( '%hashtags%', $this->get_the_post_hashtags(), $content );
		// backwards compatibility
		$content = \str_replace( '%tags%', $this->get_the_post_hashtags(), $content );

		$content = \trim( \preg_replace( '/[\r\n]{2,}/', '', $content ) );

		$filtered_content = \apply_filters( 'activitypub_the_content', $content, $this->post );
		$decoded_content = \html_entity_decode( $filtered_content, \ENT_QUOTES, 'UTF-8' );

		$allowed_html = \apply_filters( 'activitypub_allowed_html', \get_option( 'activitypub_allowed_html', ACTIVITYPUB_ALLOWED_HTML ) );

		if ( $allowed_html ) {
			return \strip_tags( $decoded_content, $allowed_html );
		}

		return $decoded_content;
	}

	public function get_post_content_template() {
		if ( 'excerpt' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return "%excerpt%\n\n<p>%permalink%</p>";
		}

		if ( 'title' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return "<p><strong>%title%</strong></p>\n\n<p>%permalink%</p>";
		}

		if ( 'content' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return "%content%\n\n<p>%hashtags%</p>\n\n<p>%permalink%</p>";
		}

		return \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );
	}

	/**
	 * Get the excerpt for a post for use outside of the loop.
	 *
	 * @param int     Optional excerpt length.
	 *
	 * @return string The excerpt.
	 */
	public function get_the_post_excerpt( $excerpt_length = 400 ) {
		$post = $this->post;

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

	/**
	 * Get the content for a post for use outside of the loop.
	 *
	 * @return string The content.
	 */
	public function get_the_post_content() {
		$post = $this->post;

		$content = \get_post_field( 'post_content', $post );

		return \apply_filters( 'the_content', $content );
	}

	/**
	 * Adds a backlink to the post/summary content
	 *
	 * @param string  $content
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public function get_the_post_link( $type = 'permalink' ) {
		$post = $this->post;

		if ( 'shortlink' === $type ) {
			$link = \esc_url( \wp_get_shortlink( $post->ID ) );
		} elseif ( 'permalink' === $type ) {
			$link = \esc_url( \get_permalink( $post->ID ) );
		} else {
			return '';
		}

		return \sprintf( '<a href="%1$s">%1$s</a>', $link );
	}

	/**
	 * Adds all tags as hashtags to the post/summary content
	 *
	 * @param string  $content
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public function get_the_post_hashtags() {
		$post = $this->post;
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
}
