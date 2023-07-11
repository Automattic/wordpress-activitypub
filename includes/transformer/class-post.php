<?php
namespace Activitypub\Transformer;

use WP_Post;
use Activitypub\Collection\Users;
use Activitypub\Model\Blog_User;
use Activitypub\Activity\Base_Object;

use function Activitypub\is_single_user;
use function Activitypub\get_rest_url_by_path;

/**
 * WordPress Post Transformer
 *
 * The Post Transformer is responsible for transforming a WP_Post object into different othe
 * Object-Types.
 *
 * Currently supported are:
 *
 * - Activitypub\Activity\Base_Object
 */
class Post {

	/**
	 * The WP_Post object.
	 *
	 * @var WP_Post
	 */
	protected $wp_post;

	/**
	 * The Allowed Tags, used in the content.
	 *
	 * @var array
	 */
	protected $allowed_tags = array(
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
		'ol' => array(
			'reversed' => array(),
			'start'    => array(),
		),
		'li' => array(
			'value' => array(),
		),
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
	 * Static function to Transform a WP_Post Object.
	 *
	 * This helps to chain the output of the Transformer.
	 *
	 * @param WP_Post $wp_post The WP_Post object
	 *
	 * @return void
	 */
	public static function transform( WP_Post $wp_post ) {
		return new static( $wp_post );
	}

	/**
	 *
	 *
	 * @param WP_Post $wp_post
	 */
	public function __construct( WP_Post $wp_post ) {
		$this->wp_post = $wp_post;
	}

	/**
	 * Transforms the WP_Post object to an ActivityPub Object
	 *
	 * @see \Activitypub\Activity\Base_Object
	 *
	 * @return \Activitypub\Activity\Base_Object The ActivityPub Object
	 */
	public function to_object() {
		$wp_post = $this->wp_post;
		$object = new Base_Object();

		$object->set_id( \esc_url( \get_permalink( $wp_post->ID ) ) );
		$object->set_url( \esc_url( \get_permalink( $wp_post->ID ) ) );
		$object->set_type( $this->get_object_type() );
		$object->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $wp_post->post_date_gmt ) ) );
		$object->set_attributed_to( $this->get_attributed_to() );
		$object->set_content( $this->get_content() );
		$object->set_content_map(
			array(
				\strstr( \get_locale(), '_', true ) => $this->get_content(),
			)
		);
		$path = sprintf( 'users/%d/followers', intval( $wp_post->post_author ) );

		$object->set_to(
			array(
				'https://www.w3.org/ns/activitystreams#Public',
				get_rest_url_by_path( $path ),
			)
		);
		$object->set_cc( $this->get_cc() );
		$object->set_attachment( $this->get_attachments() );
		$object->set_tag( $this->get_tags() );

		return $object;
	}

	/**
	 * Returns the User-URL of the Author of the Post.
	 *
	 * If `single_user` mode is enabled, the URL of the Blog-User is returned.
	 *
	 * @return string The User-URL.
	 */
	protected function get_attributed_to() {
		if ( is_single_user() ) {
			$user = new Blog_User();
			return $user->get_url();
		}

		return Users::get_by_id( $this->wp_post->post_author )->get_url();
	}

	/**
	 * Generates all Image Attachments for a Post.
	 *
	 * @return array The Image Attachments.
	 */
	protected function get_attachments() {
		$max_images = intval( \apply_filters( 'activitypub_max_image_attachments', \get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS ) ) );

		$images = array();

		// max images can't be negative or zero
		if ( $max_images <= 0 ) {
			return $images;
		}

		$id = $this->wp_post->ID;

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
	protected function get_image( $id, $image_size = 'full' ) {
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
	 * Returns the ActivityStreams 2.0 Object-Type for a Post based on the
	 * settings and the Post-Type.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#activity-types
	 *
	 * @return string The Object-Type.
	 */
	protected function get_object_type() {
		if ( 'wordpress-post-format' !== \get_option( 'activitypub_object_type', 'note' ) ) {
			return \ucfirst( \get_option( 'activitypub_object_type', 'note' ) );
		}

		$post_type = \get_post_type( $this->wp_post );
		switch ( $post_type ) {
			case 'post':
				$post_format = \get_post_format( $this->wp_post );
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

	/**
	 * Returns a list of Mentions, used in the Post.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#Mention
	 *
	 * @return array The list of Mentions.
	 */
	protected function get_cc() {
		$cc = array();

		$mentions = $this->get_mentions();
		if ( $mentions ) {
			foreach ( $mentions as $mention => $url ) {
				$cc[] = $url;
			}
		}

		return $cc;
	}

	/**
	 * Returns a list of Tags, used in the Post.
	 *
	 * This includes Hash-Tags and Mentions.
	 *
	 * @return array The list of Tags.
	 */
	protected function get_tags() {
		$tags = array();

		$post_tags = \get_the_tags( $this->wp_post->ID );
		if ( $post_tags ) {
			foreach ( $post_tags as $post_tag ) {
				$tag = array(
					'type' => 'Hashtag',
					'href' => esc_url( \get_tag_link( $post_tag->term_id ) ),
					'name' => '#' . \esc_attr( $post_tag->slug ),
				);
				$tags[] = $tag;
			}
		}

		$mentions = $this->get_mentions();
		if ( $mentions ) {
			foreach ( $mentions as $mention => $url ) {
				$tag = array(
					'type' => 'Mention',
					'href' => \esc_url( $url ),
					'name' => \esc_html( $mention ),
				);
				$tags[] = $tag;
			}
		}

		return $tags;
	}

	/**
	 * Returns the content for the ActivityPub Item.
	 *
	 * The content will be generated based on the user settings.
	 *
	 * @return string The content.
	 */
	protected function get_content() {
		global $post;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = $this->wp_post;
		$content = $this->get_post_content_template();

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$content = \wp_kses( $content, $this->allowed_tags );
		$content = \wpautop( $content );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );

		$content = \apply_filters( 'activitypub_the_content', $content, $post );
		$content = \html_entity_decode( $content, \ENT_QUOTES, 'UTF-8' );

		return $content;
	}

	/**
	 * Gets the template to use to generate the content of the activitypub item.
	 *
	 * @return string The Template.
	 */
	protected function get_post_content_template() {
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

	/**
	 * Helper function to get the @-Mentions from the post content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentions() {
		return apply_filters( 'activitypub_extract_mentions', array(), $this->wp_post->post_content, $this->wp_post );
	}
}
