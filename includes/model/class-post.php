<?php
namespace Activitypub\Model;

/**
 * ActivityPub Post Class
 *
 * @author Matthias Pfefferle
 */
class Post {
	private $post;

	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'activitypub_the_summary', array( '\Activitypub\Model\Post', 'add_backlink_to_content' ), 15, 2 );
		\add_filter( 'activitypub_the_content', array( '\Activitypub\Model\Post', 'add_backlink_to_content' ), 15, 2 );
	}

	public function __construct( $post = null ) {
		$this->post = \get_post( $post );
	}

	public function get_post() {
		return $this->post;
	}

	public function get_post_author() {
		return $this->post->post_author;
	}

	public function to_array() {
		$post = $this->post;

		$array = array(
			'id' => \get_permalink( $post ),
			'type' => $this->get_object_type(),
			'published' => \date( 'Y-m-d\TH:i:s\Z', \strtotime( $post->post_date ) ),
			'attributedTo' => \get_author_posts_url( $post->post_author ),
			'summary' => $this->get_the_title(),
			'inReplyTo' => null,
			'content' => $this->get_the_content(),
			'contentMap' => array(
				\strstr( \get_locale(), '_', true ) => $this->get_the_content(),
			),
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'attachment' => $this->get_attachments(),
			'tag' => $this->get_tags(),
		);

		return \apply_filters( 'activitypub_post', $array );
	}

	public function to_json() {
		return \wp_json_encode( $this->to_array(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT );
	}

	public function get_attachments() {
		$max_images = \apply_filters( 'activitypub_max_images', 3 );

		$images = array();

		// max images can't be negative or zero
		if ( $max_images <= 0 ) {
			$max_images = 1;
		}

		$id = $this->post->ID;

		$image_ids = array();
		// list post thumbnail first if this post has one
		if ( \function_exists( 'has_post_thumbnail' ) && \has_post_thumbnail( $id ) ) {
			$image_ids[] = \get_post_thumbnail_id( $id );
			$max_images--;
		}
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
					'mediaType' => $mimetype
				);
				if ( $alt ) {
					$image['name'] = $alt;
				}
				$images[] = $image;
			}
		}

		return $images;
	}

	public function get_tags() {
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
	public function get_object_type() {
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

	public function get_the_content() {
		if ( 'excerpt' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return $this->get_the_post_summary();
		}

	        if ( 'titlelink' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
	   	        return $this->get_the_title();
		}

		return $this->get_the_post_content();
	}

	public function get_the_title() {
		if ( 'Article' === $this->get_object_type() ) {
			$title = \get_the_title( $this->post );

			return \html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
		}

		return null;
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

				$excerpt_length = \apply_filters( 'excerpt_length', $excerpt_length );

				/** This filter is documented in wp-includes/formatting.php */
				$excerpt_more = \apply_filters( 'excerpt_more', ' [...]' );

				$excerpt = \wp_trim_words( $excerpt, $excerpt_length, $excerpt_more );
			}
		}

		return $excerpt;
	}

	/**
	 * Get the content for a post for use outside of the loop.
	 *
	 * @return string The content.
	 */
	public function get_the_post_content() {
		$post = $this->post;

		$content = \get_post_field( 'post_content', $post );

		$filtered_content = \apply_filters( 'the_content', $content );
		$filtered_content = \apply_filters( 'activitypub_the_content', $filtered_content, $this->post );

		$decoded_content = \html_entity_decode( $filtered_content, ENT_QUOTES, 'UTF-8' );

		$allowed_html = \apply_filters( 'activitypub_allowed_html', '<a><p><ul><ol><li><code><blockquote><pre>' );

		return \trim( \preg_replace( '/[\r\n]{2,}/', '', \strip_tags( $decoded_content, $allowed_html ) ) );
	}

	/**
	 * Get the excerpt for a post for use outside of the loop.
	 *
	 * @param int     Optional excerpt length.
	 *
	 * @return string The excerpt.
	 */
	public function get_the_post_summary( $summary_length = 400 ) {
		$summary = $this->get_the_post_excerpt( $summary_length );

		$filtered_summary = \apply_filters( 'the_excerpt', $summary );
		$filtered_summary = \apply_filters( 'activitypub_the_summary', $filtered_summary, $this->post );

		$decoded_summary = \html_entity_decode( $filtered_summary, ENT_QUOTES, 'UTF-8' );

		$allowed_html = \apply_filters( 'activitypub_allowed_html', '<a><p>' );

		return \trim( \preg_replace( '/[\r\n]{2,}/', '', \strip_tags( $decoded_summary, $allowed_html ) ) );
	}

	/**
	 * Adds a backlink to the post/summary content
	 *
	 * @param string  $content
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public static function add_backlink_to_content( $content, $post ) {
		$link = '';

		if ( \get_option( 'activitypub_use_shortlink', 0 ) ) {
			$link = \esc_url( \wp_get_shortlink( $post->ID ) );
		} else {
			$link = \esc_url( \get_permalink( $post->ID ) );
		}

		return $content . '<p><a href="' . $link . '">' . $link . '</a></p>';
	}
}
