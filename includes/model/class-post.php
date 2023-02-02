<?php
namespace Activitypub\Model;

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
	private $object_type = 'Note';

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
	);

	/**
	 * Constructor
	 *
	 * @param WP_Post $post
	 */
	public function __construct( $post ) {
		$this->post = \get_post( $post );
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
			'type' => $this->get_object_type(),
			'published' => \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $post->post_date_gmt ) ),
			'attributedTo' => \get_author_posts_url( $post->post_author ),
			'summary' => $this->get_summary(),
			'inReplyTo' => null,
			'content' => $this->get_content(),
			'contentMap' => array(
				\strstr( \get_locale(), '_', true ) => $this->get_content(),
			),
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'attachment' => $this->get_attachments(),
			'tag' => $this->get_tags(),
		);

		return \apply_filters( 'activitypub_post', $array );
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
	 * Returns the ID of an Activity Object
	 *
	 * @return string
	 */
	public function get_id() {
		if ( $this->id ) {
			return $this->id;
		}

		$post = $this->post;

		if ( 'trash' === get_post_status( $post ) ) {
			$permalink = \get_post_meta( $post->ID, 'activitypub_canonical_url', true );
		} else {
			$permalink = \get_permalink( $post );
		}

		$this->id = $permalink;

		return $permalink;
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

		$this->attachments = $images;

		return $images;
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

		$filtered_content = \apply_filters( 'activitypub_the_content', $content, $post );
		$decoded_content = \html_entity_decode( $filtered_content, \ENT_QUOTES, 'UTF-8' );

		$content = \trim( \preg_replace( '/[\n\r\t]/', '', $content ) );

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
			return "[ap_excerpt]\n\n[ap_permalink]";
		}

		if ( 'title' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return "[ap_title]\n\n[ap_permalink]";
		}

		if ( 'content' === \get_option( 'activitypub_post_content_type', 'content' ) ) {
			return "[ap_content]\n\n[ap_hashtags]\n\n[ap_permalink]";
		}

		// Upgrade from old template codes to shortcodes.
		$content = self::upgrade_post_content_template();

		return $content;
	}

	/**
	 * Updates the custom template to use shortcodes instead of the deprecated templates.
	 *
	 * @return string the updated template content
	 */
	public static function upgrade_post_content_template() {
		// Get the custom template.
		$old_content = \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );

		// If the old content exists but is a blank string, we're going to need a flag to updated it even
		// after setting it to the default contents.
		$need_update = false;

		// If the old contents is blank, use the defaults.
		if ( '' === $old_content ) {
			$old_content = ACTIVITYPUB_CUSTOM_POST_CONTENT;
			$need_update = true;
		}

		// Set the new content to be the old content.
		$content = $old_content;

		// Convert old templates to shortcodes.
		$content = \str_replace( '%title%', '[ap_title]', $content );
		$content = \str_replace( '%excerpt%', '[ap_excerpt]', $content );
		$content = \str_replace( '%content%', '[ap_content]', $content );
		$content = \str_replace( '%permalink%', '[ap_permalink type="html"]', $content );
		$content = \str_replace( '%shortlink%', '[ap_shortlink type="html"]', $content );
		$content = \str_replace( '%hashtags%', '[ap_hashtags]', $content );
		$content = \str_replace( '%tags%', '[ap_hashtags]', $content );

		// Store the new template if required.
		if ( $content !== $old_content || $need_update ) {
			\update_option( 'activitypub_custom_post_content', $content );
		}

		return $content;
	}
}
