<?php
namespace Activitypub\Model;

use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Post Class
 *
 * @author Matthias Pfefferle
 */
class Post {
	/**
	 * The WordPress Post Object.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * The Post Author.
	 *
	 * @var string
	 */
	private $post_author;

	/**
	 * The Object ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The Object URL.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * The Object Summary.
	 *
	 * @var string
	 */
	private $summary;

	/**
	 * The Object Summary
	 *
	 * @var string
	 */
	private $content;

	/**
	 * The Object Attachments. This is usually a list of Images.
	 *
	 * @var array
	 */
	private $attachments;

	/**
	 * The Object Tags. This is usually the list of used Hashtags.
	 *
	 * @var array
	 */
	private $tags;

	/**
	 * The Onject Type
	 *
	 * @var string
	 */
	private $object_type;

	/**
	 * The Allowed Tags, used in the content.
	 *
	 * @var array
	 */
	private $allowed_tags = array(
		'a' => array(
			'href' => array(),
			'title' => array(),
			'class' => array(),
			'rel' => array(),
		),
		'br' => array(),
		'p' => array(
			'class' => array(),
		),
		'span' => array(
			'class' => array(),
		),
		'div' => array(
			'class' => array(),
		),
		'ul' => array(),
		'ol' => array(),
		'li' => array(),
		'strong' => array(
			'class' => array(),
		),
		'b' => array(
			'class' => array(),
		),
		'i' => array(
			'class' => array(),
		),
		'em' => array(
			'class' => array(),
		),
		'blockquote' => array(),
		'cite' => array(),
		'code' => array(
			'class' => array(),
		),
		'pre' => array(
			'class' => array(),
		),
	);

	/**
	 * List of audience
	 *
	 * Also used for visibility
	 *
	 * @var array
	 */
	private $to = array( 'https://www.w3.org/ns/activitystreams#Public' );

	/**
	 * List of audience
	 *
	 * Also used for visibility
	 *
	 * @var array
	 */
	private $cc = array();

	/**
	 * Constructor
	 *
	 * @param WP_Post $post
	 */
	public function __construct( $post ) {
		$this->post = \get_post( $post );
		$path = sprintf( 'users/%d/followers', intval( $this->get_post_author() ) );
		$this->add_to( get_rest_url_by_path( $path ) );
	}

	/**
	 * Magic function to implement getter and setter
	 *
	 * @param string $method
	 * @param string $params
	 *
	 * @return void
	 */
	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			if ( empty( $this->$var ) && ! empty( $this->post->$var ) ) {
				return $this->post->$var;
			}
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
		}

		if ( \strncasecmp( $method, 'add', 3 ) === 0 ) {
			if ( ! is_array( $this->$var ) ) {
				$this->$var = $params[0];
			}

			if ( is_array( $params[0] ) ) {
				$this->$var = array_merge( $this->$var, $params[0] );
			} else {
				array_push( $this->$var, $params[0] );
			}

			$this->$var = array_unique( $this->$var );
		}
	}

	/**
	 * Converts this Object into an Array.
	 *
	 * @return array
	 */
	public function to_array() {
		$post = $this->post;

		$array = array(
			'id' => $this->get_id(),
			'url' => $this->get_url(),
			'type' => $this->get_object_type(),
			'published' => \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $post->post_date_gmt ) ),
			'attributedTo' => \get_author_posts_url( $post->post_author ),
			'summary' => $this->get_summary(),
			'inReplyTo' => null,
			'content' => $this->get_content(),
			'contentMap' => array(
				\strstr( \get_locale(), '_', true ) => $this->get_content(),
			),
			'to' => $this->get_to(),
			'cc' => $this->get_cc(),
			'attachment' => $this->get_attachments(),
			'tag' => $this->get_tags(),
		);

		return \apply_filters( 'activitypub_post', $array, $this->post );
	}

	/**
	 * Converts this Object into a JSON String
	 *
	 * @return string
	 */
	public function to_json() {
		return \wp_json_encode( $this->to_array(), \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_QUOT );
	}

	/**
	 * Returns the URL of an Activity Object
	 *
	 * @return string
	 */
	public function get_url() {
		if ( $this->url ) {
			return $this->url;
		}

		$post = $this->post;

		if ( 'trash' === get_post_status( $post ) ) {
			$permalink = \get_post_meta( $post->ID, 'activitypub_canonical_url', true );
		} else {
			$permalink = \get_permalink( $post );
		}

		$this->url = $permalink;

		return $permalink;
	}

	/**
	 * Returns the ID of an Activity Object
	 *
	 * @return string
	 */
	public function get_id() {
		if ( $this->id ) {
			return $this->id;
		}

		$this->id = $this->get_url();

		return $this->id;
	}

	/**
	 * Returns a list of Image Attachments
	 *
	 * @return array
	 */
	public function get_attachments() {
		if ( $this->attachments ) {
			return $this->attachments;
		}

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
			$image_size = 'full';

			/**
			 * Filter the image URL returned for each post.
			 *
			 * @param array|false $thumbnail The image URL, or false if no image is available.
			 * @param int         $id        The attachment ID.
			 * @param string      $image_size The image size to retrieve. Set to 'full' by default.
			 */
			$thumbnail = apply_filters(
				'activitypub_get_image',
				$this->get_image( $id, $image_size ),
				$id,
				$image_size
			);

			if ( $thumbnail ) {
				$mimetype = \get_post_mime_type( $id );
				$alt      = \get_post_meta( $id, '_wp_attachment_image_alt', true );
				$image    = array(
					'type'      => 'Image',
					'url'       => $thumbnail[0],
					'mediaType' => $mimetype,
				);

				if ( $alt ) {
					$image['name'] = $alt;
				}
				$images[] = $image;
			}
		}

		$this->attachments = $images;

		return $images;
	}

	/**
	 * Return details about an image attachment.
	 *
	 * @param int    $id         The attachment ID.
	 * @param string $image_size The image size to retrieve. Set to 'full' by default.
	 *
	 * @return array|false Array of image data, or boolean false if no image is available.
	 */
	public function get_image( $id, $image_size = 'full' ) {
		/**
		 * Hook into the image retrieval process. Before image retrieval.
		 *
		 * @param int    $id         The attachment ID.
		 * @param string $image_size The image size to retrieve. Set to 'full' by default.
		 */
		do_action( 'activitypub_get_image_pre', $id, $image_size );

		$thumbnail = \wp_get_attachment_image_src( $id, $image_size );

		/**
		 * Hook into the image retrieval process. After image retrieval.
		 *
		 * @param int    $id         The attachment ID.
		 * @param string $image_size The image size to retrieve. Set to 'full' by default.
		 */
		do_action( 'activitypub_get_image_pre', $id, $image_size );

		return $thumbnail;
	}

	/**
	 * Returns a list of Tags, used in the Post
	 *
	 * @return array
	 */
	public function get_tags() {
		if ( $this->tags ) {
			return $this->tags;
		}

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

		$mentions = apply_filters( 'activitypub_extract_mentions', array(), $this->post->post_content, $this );
		if ( $mentions ) {
			foreach ( $mentions as $mention => $url ) {
				$tag = array(
					'type' => 'Mention',
					'href' => $url,
					'name' => $mention,
				);
				$tags[] = $tag;
			}
		}

		$this->tags = $tags;

		return $tags;
	}

	/**
	 * Returns the as2 object-type for a given post
	 *
	 * @return string the object-type
	 */
	public function get_object_type() {
		if ( $this->object_type ) {
			return $this->object_type;
		}

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

		$this->object_type = $object_type;

		return $object_type;
	}

	/**
	 * Returns the content for the ActivityPub Item.
	 *
	 * @return string the content
	 */
	public function get_content() {
		global $post;

		if ( $this->content ) {
			return $this->content;
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = $this->post;
		$content = $this->get_post_content_template();

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$content = \wpautop( \wp_kses( $content, $this->allowed_tags ) );
		$content = \trim( \preg_replace( '/[\n\r\t]/', '', $content ) );

		$content = \apply_filters( 'activitypub_the_content', $content, $post );
		$content = \html_entity_decode( $content, \ENT_QUOTES, 'UTF-8' );

		$this->content = $content;

		return $content;
	}

	/**
	 * Gets the template to use to generate the content of the activitypub item.
	 *
	 * @return string the template
	 */
	public function get_post_content_template() {
		if ( 'excerpt' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return "[ap_excerpt]\n\n[ap_permalink type=\"html\"]";
		}

		if ( 'title' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return "[ap_title]\n\n[ap_permalink type=\"html\"]";
		}

		if ( 'content' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return "[ap_content]\n\n[ap_hashtags]\n\n[ap_permalink type=\"html\"]";
		}

		return \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );
	}
}
