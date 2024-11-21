<?php
/**
 * WordPress Post Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use WP_Post;
use Activitypub\Shortcodes;
use Activitypub\Model\Blog;
use Activitypub\Collection\Actors;

use function Activitypub\esc_hashtag;
use function Activitypub\is_single_user;
use function Activitypub\get_enclosures;
use function Activitypub\get_upload_baseurl;
use function Activitypub\get_content_warning;
use function Activitypub\site_supports_blocks;
use function Activitypub\generate_post_summary;
use function Activitypub\get_content_visibility;

/**
 * WordPress Post Transformer.
 *
 * The Post Transformer is responsible for transforming a WP_Post object into different other
 * Object-Types.
 *
 * Currently supported are:
 *
 * - Activitypub\Activity\Base_Object
 */
class Post extends Base {
	/**
	 * The User as Actor Object.
	 *
	 * @var \Activitypub\Activity\Actor
	 */
	private $actor_object = null;

	/**
	 * Returns the ID of the WordPress Post.
	 *
	 * @return int The ID of the WordPress Post
	 */
	public function get_wp_user_id() {
		return $this->wp_object->post_author;
	}

	/**
	 * Change the User-ID of the WordPress Post.
	 *
	 * @param int $user_id The new user ID.
	 *
	 * @return Post The Post Object.
	 */
	public function change_wp_user_id( $user_id ) {
		$this->wp_object->post_author = $user_id;

		return $this;
	}

	/**
	 * Transforms the WP_Post object to an ActivityPub Object
	 *
	 * @see \Activitypub\Activity\Base_Object
	 *
	 * @return \Activitypub\Activity\Base_Object The ActivityPub Object
	 */
	public function to_object() {
		$post   = $this->wp_object;
		$object = parent::to_object();

		$content_warning = get_content_warning( $post );
		if ( ! empty( $content_warning ) ) {
			$object->set_sensitive( true );
			$object->set_summary( $content_warning );
			$object->set_summary_map( null );
		}

		$visibility = get_content_visibility( $post );

		switch ( $visibility ) {
			case ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC:
				$object->set_to( $this->get_cc() );
				$object->set_cc( $this->get_to() );
				break;
			case ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL:
				$object->set_to( array() );
				$object->set_cc( array() );
				break;
		}

		return $object;
	}

	/**
	 * Returns the User-Object of the Author of the Post.
	 *
	 * If `single_user` mode is enabled, the Blog-User is returned.
	 *
	 * @return \Activitypub\Activity\Actor The User-Object.
	 */
	public function get_actor_object() {
		if ( $this->actor_object ) {
			return $this->actor_object;
		}

		$blog_user          = new Blog();
		$this->actor_object = $blog_user;

		if ( is_single_user() ) {
			return $blog_user;
		}

		$user = Actors::get_by_id( $this->wp_object->post_author );

		if ( $user && ! is_wp_error( $user ) ) {
			$this->actor_object = $user;
			return $user;
		}

		return $blog_user;
	}

	/**
	 * Returns the ID of the Post.
	 *
	 * @return string The Posts ID.
	 */
	public function get_id() {
		$last_legacy_id = (int) \get_option( 'activitypub_last_post_with_permalink_as_id', 0 );
		$post_id        = (int) $this->wp_object->ID;

		if ( $post_id > $last_legacy_id ) {
			// Generate URI based on post ID.
			return \add_query_arg( 'p', $post_id, \trailingslashit( \home_url() ) );
		}

		return $this->get_url();
	}

	/**
	 * Returns the URL of the Post.
	 *
	 * @return string The Posts URL.
	 */
	public function get_url() {
		$post = $this->wp_object;

		switch ( \get_post_status( $post ) ) {
			case 'trash':
				$permalink = \get_post_meta( $post->ID, 'activitypub_canonical_url', true );
				break;
			case 'draft':
				// Get_sample_permalink is in wp-admin, not always loaded.
				if ( ! \function_exists( '\get_sample_permalink' ) ) {
					require_once ABSPATH . 'wp-admin/includes/post.php';
				}
				$sample    = \get_sample_permalink( $post->ID );
				$permalink = \str_replace( array( '%pagename%', '%postname%' ), $sample[1], $sample[0] );
				break;
			default:
				$permalink = \get_permalink( $post );
				break;
		}

		return \esc_url( $permalink );
	}

	/**
	 * Returns the User-URL of the Author of the Post.
	 *
	 * If `single_user` mode is enabled, the URL of the Blog-User is returned.
	 *
	 * @return string The User-URL.
	 */
	protected function get_attributed_to() {
		return $this->get_actor_object()->get_id();
	}

	/**
	 * Returns the featured image as `Image`.
	 *
	 * @return array|null The Image or null if no image is available.
	 */
	protected function get_image() {
		$post_id = $this->wp_object->ID;

		// List post thumbnail first if this post has one.
		if (
			! \function_exists( 'has_post_thumbnail' ) ||
			! \has_post_thumbnail( $post_id )
		) {
			return null;
		}

		$id         = \get_post_thumbnail_id( $post_id );
		$image_size = 'large';

		/**
		 * Filter the image URL returned for each post.
		 *
		 * @param array|false $thumbnail  The image URL, or false if no image is available.
		 * @param int         $id         The attachment ID.
		 * @param string      $image_size The image size to retrieve. Set to 'large' by default.
		 */
		$thumbnail = apply_filters(
			'activitypub_get_image',
			self::get_wordpress_attachment( $id, $image_size ),
			$id,
			$image_size
		);

		if ( ! $thumbnail ) {
			return null;
		}

		$mime_type = \get_post_mime_type( $id );

		$image = array(
			'type'      => 'Image',
			'url'       => \esc_url( $thumbnail[0] ),
			'mediaType' => \esc_attr( $mime_type ),
		);

		$alt = \get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( $alt ) {
			$image['name'] = \wp_strip_all_tags( \html_entity_decode( $alt ) );
		}

		return $image;
	}

	/**
	 * Generates all Media Attachments for a Post.
	 *
	 * @return array The Attachments.
	 */
	protected function get_attachment() {
		// Remove attachments from drafts.
		if ( 'draft' === \get_post_status( $this->wp_object ) ) {
			return array();
		}

		// Once upon a time we only supported images, but we now support audio/video as well.
		// We maintain the image-centric naming for backwards compatibility.
		$max_media = \intval(
			\apply_filters(
				'activitypub_max_image_attachments',
				\get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS )
			)
		);

		$media = array(
			'audio' => array(),
			'video' => array(),
			'image' => array(),
		);
		$id    = $this->wp_object->ID;

		// List post thumbnail first if this post has one.
		if ( \function_exists( 'has_post_thumbnail' ) && \has_post_thumbnail( $id ) ) {
			$media['image'][] = array( 'id' => \get_post_thumbnail_id( $id ) );
		}

		$media = $this->get_enclosures( $media );

		if ( site_supports_blocks() && \has_blocks( $this->wp_object->post_content ) ) {
			$media = $this->get_block_attachments( $media, $max_media );
		} else {
			$media = $this->get_classic_editor_images( $media, $max_media );
		}

		$media      = self::filter_media_by_object_type( $media, \get_post_format( $this->wp_object ), $this->wp_object );
		$unique_ids = \array_unique( \array_column( $media, 'id' ) );
		$media      = \array_intersect_key( $media, $unique_ids );
		$media      = \array_slice( $media, 0, $max_media );

		/**
		 * Filter the attachment IDs for a post.
		 *
		 * @param array   $media           The media array grouped by type.
		 * @param WP_Post $this->wp_object The post object.
		 *
		 * @return array The filtered attachment IDs.
		 */
		$media = \apply_filters( 'activitypub_attachment_ids', $media, $this->wp_object );

		$attachments = \array_filter( \array_map( array( self::class, 'wp_attachment_to_activity_attachment' ), $media ) );

		/**
		 * Filter the attachments for a post.
		 *
		 * @param array   $attachments     The attachments.
		 * @param WP_Post $this->wp_object The post object.
		 *
		 * @return array The filtered attachments.
		 */
		return \apply_filters( 'activitypub_attachments', $attachments, $this->wp_object );
	}

	/**
	 * Get enclosures for a post.
	 *
	 * @param array $media The media array grouped by type.
	 *
	 * @return array The media array extended with enclosures.
	 */
	public function get_enclosures( $media ) {
		$enclosures = get_enclosures( $this->wp_object->ID );

		if ( ! $enclosures ) {
			return $media;
		}

		foreach ( $enclosures as $enclosure ) {
			// Check if URL is an attachment.
			$attachment_id = \attachment_url_to_postid( $enclosure['url'] );
			if ( $attachment_id ) {
				$enclosure['id']        = $attachment_id;
				$enclosure['url']       = \wp_get_attachment_url( $attachment_id );
				$enclosure['mediaType'] = \get_post_mime_type( $attachment_id );
			}

			$mime_type       = $enclosure['mediaType'];
			$mime_type_parts = \explode( '/', $mime_type );

			switch ( $mime_type_parts[0] ) {
				case 'image':
					$media['image'][] = $enclosure;
					break;
				case 'audio':
					$media['audio'][] = $enclosure;
					break;
				case 'video':
					$media['video'][] = $enclosure;
					break;
			}
		}

		return $media;
	}

	/**
	 * Get media attachments from blocks. They will be formatted as ActivityPub attachments, not as WP attachments.
	 *
	 * @param array $media     The media array grouped by type.
	 * @param int   $max_media The maximum number of attachments to return.
	 *
	 * @return array The attachments.
	 */
	protected function get_block_attachments( $media, $max_media ) {
		// Max media can't be negative or zero.
		if ( $max_media <= 0 ) {
			return array();
		}

		$blocks = \parse_blocks( $this->wp_object->post_content );

		return self::get_media_from_blocks( $blocks, $media );
	}

	/**
	 * Recursively get media IDs from blocks.
	 *
	 * @param array $blocks The blocks to search for media IDs.
	 * @param array $media  The media IDs to append new IDs to.
	 *
	 * @return array The image IDs.
	 */
	protected static function get_media_from_blocks( $blocks, $media ) {
		foreach ( $blocks as $block ) {
			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$media = self::get_media_from_blocks( $block['innerBlocks'], $media );
			}

			switch ( $block['blockName'] ) {
				case 'core/image':
				case 'core/cover':
					if ( ! empty( $block['attrs']['id'] ) ) {
						$alt   = '';
						$check = preg_match( '/<img.*?alt\s*=\s*([\"\'])(.*?)\1.*>/i', $block['innerHTML'], $match );

						if ( $check ) {
							$alt = $match[2];
						}

						$media['image'][] = array(
							'id'  => $block['attrs']['id'],
							'alt' => $alt,
						);
					}
					break;
				case 'core/audio':
					if ( ! empty( $block['attrs']['id'] ) ) {
						$media['audio'][] = array( 'id' => $block['attrs']['id'] );
					}
					break;
				case 'core/video':
				case 'videopress/video':
					if ( ! empty( $block['attrs']['id'] ) ) {
						$media['video'][] = array( 'id' => $block['attrs']['id'] );
					}
					break;
				case 'jetpack/slideshow':
				case 'jetpack/tiled-gallery':
					if ( ! empty( $block['attrs']['ids'] ) ) {
						$media['image'] = array_merge(
							$media['image'],
							array_map(
								function ( $id ) {
									return array( 'id' => $id );
								},
								$block['attrs']['ids']
							)
						);
					}
					break;
				case 'jetpack/image-compare':
					if ( ! empty( $block['attrs']['beforeImageId'] ) ) {
						$media['image'][] = array( 'id' => $block['attrs']['beforeImageId'] );
					}
					if ( ! empty( $block['attrs']['afterImageId'] ) ) {
						$media['image'][] = array( 'id' => $block['attrs']['afterImageId'] );
					}
					break;
			}
		}

		return $media;
	}

	/**
	 * Get post images from the classic editor.
	 * Note that audio/video attachments are only supported in the block editor.
	 *
	 * @param array $media      The media array grouped by type.
	 * @param int   $max_images The maximum number of images to return.
	 *
	 * @return array The attachments.
	 */
	protected function get_classic_editor_images( $media, $max_images ) {
		// Max images can't be negative or zero.
		if ( $max_images <= 0 ) {
			return array();
		}

		if ( \count( $media['image'] ) <= $max_images ) {
			if ( \class_exists( '\WP_HTML_Tag_Processor' ) ) {
				$media['image'] = \array_merge( $media['image'], $this->get_classic_editor_image_embeds( $max_images ) );
			} else {
				$media['image'] = \array_merge( $media['image'], $this->get_classic_editor_image_attachments( $max_images ) );
			}
		}

		return $media;
	}

	/**
	 * Get image embeds from the classic editor by parsing HTML.
	 *
	 * @param int $max_images The maximum number of images to return.
	 *
	 * @return array The attachments.
	 */
	protected function get_classic_editor_image_embeds( $max_images ) {
		// If someone calls that function directly, bail.
		if ( ! \class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return array();
		}

		// Max images can't be negative or zero.
		if ( $max_images <= 0 ) {
			return array();
		}

		$images  = array();
		$base    = get_upload_baseurl();
		$content = \get_post_field( 'post_content', $this->wp_object );
		$tags    = new \WP_HTML_Tag_Processor( $content );

		// This linter warning is a false positive - we have to re-count each time here as we modify $images.
		// phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found
		while ( $tags->next_tag( 'img' ) && ( \count( $images ) <= $max_images ) ) {
			/**
			 * Filter the image source URL.
			 *
			 * This can be used to modify the image source URL before it is used to
			 * determine the attachment ID.
			 *
			 * @param string $src The image source URL.
			 */
			$src = \apply_filters( 'activitypub_image_src', $tags->get_attribute( 'src' ) );

			/*
			 * If the img source is in our uploads dir, get the
			 * associated ID. Note: if there's a -500x500
			 * type suffix, we remove it, but we try the original
			 * first in case the original image is actually called
			 * that. Likewise, we try adding the -scaled suffix for
			 * the case that this is a small version of an image
			 * that was big enough to get scaled down on upload:
			 * https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/
			 */
			if ( null !== $src && \str_starts_with( $src, $base ) ) {
				$img_id = \attachment_url_to_postid( $src );

				if ( 0 === $img_id ) {
					$count  = 0;
					$src    = \strtok( $src, '?' );
					$img_id = \attachment_url_to_postid( $src );
				}

				if ( 0 === $img_id ) {
					$count = 0;
					$src   = \preg_replace( '/-(?:\d+x\d+)(\.[a-zA-Z]+)$/', '$1', $src, 1, $count );
					if ( $count > 0 ) {
						$img_id = \attachment_url_to_postid( $src );
					}
				}

				if ( 0 === $img_id ) {
					$src    = \preg_replace( '/(\.[a-zA-Z]+)$/', '-scaled$1', $src );
					$img_id = \attachment_url_to_postid( $src );
				}

				if ( 0 !== $img_id ) {
					$images[] = array(
						'id'  => $img_id,
						'alt' => $tags->get_attribute( 'alt' ),
					);
				}
			}
		}

		return $images;
	}

	/**
	 * Get image attachments from the classic editor.
	 * This is imperfect as the contained images aren't necessarily the
	 * same as the attachments.
	 *
	 * @param int $max_images The maximum number of images to return.
	 *
	 * @return array The attachment IDs.
	 */
	protected function get_classic_editor_image_attachments( $max_images ) {
		// Max images can't be negative or zero.
		if ( $max_images <= 0 ) {
			return array();
		}

		$images = array();
		$query  = new \WP_Query(
			array(
				'post_parent'    => $this->wp_object->ID,
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'order'          => 'ASC',
				'orderby'        => 'menu_order ID',
				'posts_per_page' => $max_images,
			)
		);

		foreach ( $query->get_posts() as $attachment ) {
			if ( ! \in_array( $attachment->ID, $images, true ) ) {
				$images[] = array( 'id' => $attachment->ID );
			}
		}

		return $images;
	}

	/**
	 * Filter media IDs by object type.
	 *
	 * @param array   $media     The media array grouped by type.
	 * @param string  $type      The object type.
	 * @param WP_Post $wp_object The post object.
	 *
	 * @return array The filtered media IDs.
	 */
	protected static function filter_media_by_object_type( $media, $type, $wp_object ) {
		/**
		 * Filter the object type for media attachments.
		 *
		 * @param string  $type      The object type.
		 * @param WP_Post $wp_object The post object.
		 *
		 * @return string The filtered object type.
		 */
		$type = \apply_filters( 'filter_media_by_object_type', \strtolower( $type ), $wp_object );

		if ( ! empty( $media[ $type ] ) ) {
			return $media[ $type ];
		}

		return array_filter( array_merge( ...array_values( $media ) ) );
	}

	/**
	 * Converts a WordPress Attachment to an ActivityPub Attachment.
	 *
	 * @param array $media The Attachment array.
	 *
	 * @return array The ActivityPub Attachment.
	 */
	public static function wp_attachment_to_activity_attachment( $media ) {
		if ( ! isset( $media['id'] ) ) {
			return $media;
		}

		$id              = $media['id'];
		$attachment      = array();
		$mime_type       = \get_post_mime_type( $id );
		$mime_type_parts = \explode( '/', $mime_type );
		// Switching on image/audio/video.
		switch ( $mime_type_parts[0] ) {
			case 'image':
				$image_size = 'large';

				/**
				 * Filter the image URL returned for each post.
				 *
				 * @param array|false $thumbnail  The image URL, or false if no image is available.
				 * @param int         $id         The attachment ID.
				 * @param string      $image_size The image size to retrieve. Set to 'large' by default.
				 */
				$thumbnail = apply_filters(
					'activitypub_get_image',
					self::get_wordpress_attachment( $id, $image_size ),
					$id,
					$image_size
				);

				if ( $thumbnail ) {
					$image = array(
						'type'      => 'Image',
						'url'       => \esc_url( $thumbnail[0] ),
						'mediaType' => \esc_attr( $mime_type ),
					);

					if ( ! empty( $media['alt'] ) ) {
						$image['name'] = \wp_strip_all_tags( \html_entity_decode( $media['alt'] ) );
					} else {
						$alt = \get_post_meta( $id, '_wp_attachment_image_alt', true );
						if ( $alt ) {
							$image['name'] = \wp_strip_all_tags( \html_entity_decode( $alt ) );
						}
					}

					$attachment = $image;
				}
				break;

			case 'audio':
			case 'video':
				$attachment = array(
					'type'      => 'Document',
					'mediaType' => \esc_attr( $mime_type ),
					'url'       => \esc_url( \wp_get_attachment_url( $id ) ),
					'name'      => \esc_attr( \get_the_title( $id ) ),
				);
				$meta       = wp_get_attachment_metadata( $id );
				// Height and width for videos.
				if ( isset( $meta['width'] ) && isset( $meta['height'] ) ) {
					$attachment['width']  = \esc_attr( $meta['width'] );
					$attachment['height'] = \esc_attr( $meta['height'] );
				}
				// @todo: add `icon` support for audio/video attachments. Maybe use post thumbnail?
				break;
		}

		/**
		 * Filter the attachment for a post.
		 *
		 * @param array $attachment The attachment.
		 * @param int   $id         The attachment ID.
		 *
		 * @return array The filtered attachment.
		 */
		return \apply_filters( 'activitypub_attachment', $attachment, $id );
	}

	/**
	 * Return details about an image attachment.
	 *
	 * @param int    $id         The attachment ID.
	 * @param string $image_size The image size to retrieve. Set to 'large' by default.
	 *
	 * @return array|false Array of image data, or boolean false if no image is available.
	 */
	protected static function get_wordpress_attachment( $id, $image_size = 'large' ) {
		/**
		 * Hook into the image retrieval process. Before image retrieval.
		 *
		 * @param int    $id         The attachment ID.
		 * @param string $image_size The image size to retrieve. Set to 'large' by default.
		 */
		do_action( 'activitypub_get_image_pre', $id, $image_size );

		$image = \wp_get_attachment_image_src( $id, $image_size );

		/**
		 * Hook into the image retrieval process. After image retrieval.
		 *
		 * @param int    $id         The attachment ID.
		 * @param string $image_size The image size to retrieve. Set to 'large' by default.
		 */
		do_action( 'activitypub_get_image_post', $id, $image_size );

		return $image;
	}

	/**
	 * Returns the ActivityStreams 2.0 Object-Type for a Post based on the
	 * settings and the Post-Type.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#activity-types
	 *
	 * @return string The Object-Type.
	 */
	protected function get_type() {
		$post_format_setting = \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE );

		if ( 'wordpress-post-format' !== $post_format_setting ) {
			return \ucfirst( $post_format_setting );
		}

		$has_title = \post_type_supports( $this->wp_object->post_type, 'title' );
		$content   = \wp_strip_all_tags( $this->wp_object->post_content );

		// Check if the post has a title.
		if (
			! $has_title ||
			! $this->wp_object->post_title ||
			\strlen( $content ) <= ACTIVITYPUB_NOTE_LENGTH
		) {
			return 'Note';
		}

		// Default to Note.
		$object_type = 'Note';
		$post_type   = \get_post_type( $this->wp_object );

		if ( 'page' === $post_type ) {
			$object_type = 'Page';
		} elseif ( ! \get_post_format( $this->wp_object ) ) {
			$object_type = 'Article';
		}

		return $object_type;
	}

	/**
	 * Returns the recipient of the post.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-to
	 *
	 * @return array The recipient URLs of the post.
	 */
	public function get_to() {
		return array(
			'https://www.w3.org/ns/activitystreams#Public',
		);
	}

	/**
	 * Returns a list of Mentions, used in the Post.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#Mention
	 *
	 * @return array The list of Mentions.
	 */
	protected function get_cc() {
		$cc = array(
			$this->get_actor_object()->get_followers(),
		);

		$mentions = $this->get_mentions();
		if ( $mentions ) {
			foreach ( $mentions as $url ) {
				$cc[] = $url;
			}
		}

		return $cc;
	}

	/**
	 * Returns the Audience for the Post.
	 *
	 * @return string|null The audience.
	 */
	public function get_audience() {
		$actor_mode = \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		if ( ACTIVITYPUB_ACTOR_AND_BLOG_MODE === $actor_mode ) {
			$blog = new Blog();
			return $blog->get_id();
		}

		return null;
	}

	/**
	 * Returns a list of Tags, used in the Post.
	 *
	 * This includes Hash-Tags and Mentions.
	 *
	 * @return array The list of Tags.
	 */
	protected function get_tag() {
		$tags = array();

		$post_tags = \get_the_tags( $this->wp_object->ID );
		if ( $post_tags ) {
			foreach ( $post_tags as $post_tag ) {
				$tag    = array(
					'type' => 'Hashtag',
					'href' => \esc_url( \get_tag_link( $post_tag->term_id ) ),
					'name' => esc_hashtag( $post_tag->name ),
				);
				$tags[] = $tag;
			}
		}

		$mentions = $this->get_mentions();
		if ( $mentions ) {
			foreach ( $mentions as $mention => $url ) {
				$tag    = array(
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
	 * Returns the summary for the ActivityPub Item.
	 *
	 * The summary will be generated based on the user settings and only if the
	 * object type is not set to `note`.
	 *
	 * @return string|null The summary or null if the object type is `note`.
	 */
	protected function get_summary() {
		if ( 'Note' === $this->get_type() ) {
			return null;
		}

		// Remove Teaser from drafts.
		if ( ! $this->is_preview() && 'draft' === \get_post_status( $this->wp_object ) ) {
			return \__( '(This post is being modified)', 'activitypub' );
		}

		return generate_post_summary( $this->wp_object );
	}

	/**
	 * Returns the title for the ActivityPub Item.
	 *
	 * The title will be generated based on the user settings and only if the
	 * object type is not set to `note`.
	 *
	 * @return string|null The title or null if the object type is `note`.
	 */
	protected function get_name() {
		if ( 'Note' === $this->get_type() ) {
			return null;
		}

		$title = \get_the_title( $this->wp_object->ID );

		if ( ! $title ) {
			return null;
		}

		return \wp_strip_all_tags(
			\html_entity_decode(
				$title
			)
		);
	}

	/**
	 * Returns the content for the ActivityPub Item.
	 *
	 * The content will be generated based on the user settings.
	 *
	 * @return string The content.
	 */
	protected function get_content() {
		add_filter( 'activitypub_reply_block', '__return_empty_string' );

		// Remove Content from drafts.
		if ( ! $this->is_preview() && 'draft' === \get_post_status( $this->wp_object ) ) {
			return \__( '(This post is being modified)', 'activitypub' );
		}

		global $post;

		/**
		 * Provides an action hook so plugins can add their own hooks/filters before AP content is generated.
		 *
		 * Example: if a plugin adds a filter to `the_content` to add a button to the end of posts, it can also remove that filter here.
		 *
		 * @param WP_Post $post The post object.
		 */
		do_action( 'activitypub_before_get_content', $post );

		add_filter( 'render_block_core/embed', array( self::class, 'revert_embed_links' ), 10, 2 );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = $this->wp_object;
		$content = $this->get_post_content_template();

		// It seems that shortcodes are only applied to published posts.
		if ( is_preview() ) {
			$post->post_status = 'publish';
		}

		// Register our shortcodes just in time.
		Shortcodes::register();
		// Fill in the shortcodes.
		\setup_postdata( $post );
		$content = \do_shortcode( $content );
		\wp_reset_postdata();

		$content = \wpautop( $content );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );

		$content = \apply_filters( 'activitypub_the_content', $content, $post );

		// Don't need these anymore, should never appear in a post.
		Shortcodes::unregister();

		return $content;
	}

	/**
	 * Gets the template to use to generate the content of the activitypub item.
	 *
	 * @return string The Template.
	 */
	protected function get_post_content_template() {
		$content  = \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );
		$template = $content ?? ACTIVITYPUB_CUSTOM_POST_CONTENT;

		$post_format_setting = \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE );

		if ( 'wordpress-post-format' === $post_format_setting ) {
			$template = '';

			if ( 'Note' === $this->get_type() ) {
				$template .= "[ap_title type=\"html\"]\n\n";
			}

			$template .= '[ap_content]';
		}

		return apply_filters( 'activitypub_object_content_template', $template, $this->wp_object );
	}

	/**
	 * Helper function to get the @-Mentions from the post content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentions() {
		/**
		 * Filter the mentions in the post content.
		 *
		 * @param array   $mentions The mentions.
		 * @param string  $content  The post content.
		 * @param WP_Post $post     The post object.
		 *
		 * @return array The filtered mentions.
		 */
		return apply_filters(
			'activitypub_extract_mentions',
			array(),
			$this->wp_object->post_content . ' ' . $this->wp_object->post_excerpt,
			$this->wp_object
		);
	}

	/**
	 * Returns the locale of the post.
	 *
	 * @return string The locale of the post.
	 */
	public function get_locale() {
		$post_id = $this->wp_object->ID;
		$lang    = \strtolower( \strtok( \get_locale(), '_-' ) );

		/**
		 * Filter the locale of the post.
		 *
		 * @param string  $lang    The locale of the post.
		 * @param int     $post_id The post ID.
		 * @param WP_Post $post    The post object.
		 *
		 * @return string The filtered locale of the post.
		 */
		return apply_filters( 'activitypub_post_locale', $lang, $post_id, $this->wp_object );
	}

	/**
	 * Returns the in-reply-to URL of the post.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-inreplyto
	 *
	 * @return string|null The in-reply-to URL of the post.
	 */
	public function get_in_reply_to() {
		$blocks = \parse_blocks( $this->wp_object->post_content );

		foreach ( $blocks as $block ) {
			if ( 'activitypub/reply' === $block['blockName'] ) {
				// We only support one reply block per post for now.
				return $block['attrs']['url'];
			}
		}

		return null;
	}

	/**
	 * Returns the published date of the post.
	 *
	 * @return string The published date of the post.
	 */
	public function get_published() {
		$published = \strtotime( $this->wp_object->post_date_gmt );

		return \gmdate( 'Y-m-d\TH:i:s\Z', $published );
	}

	/**
	 * Returns the updated date of the post.
	 *
	 * @return string|null The updated date of the post.
	 */
	public function get_updated() {
		$published = \strtotime( $this->wp_object->post_date_gmt );
		$updated   = \strtotime( $this->wp_object->post_modified_gmt );

		if ( $updated > $published ) {
			return \gmdate( 'Y-m-d\TH:i:s\Z', $updated );
		}

		return null;
	}

	/**
	 * Returns the content map for the post.
	 *
	 * @return array The content map for the post.
	 */
	public function get_content_map() {
		return array(
			$this->get_locale() => $this->get_content(),
		);
	}

	/**
	 * Returns the name map for the post.
	 *
	 * @return array The name map for the post.
	 */
	public function get_name_map() {
		if ( ! $this->get_name() ) {
			return null;
		}

		return array(
			$this->get_locale() => $this->get_name(),
		);
	}

	/**
	 * Returns the summary map for the post.
	 *
	 * @return array The summary map for the post.
	 */
	public function get_summary_map() {
		if ( ! $this->get_summary() ) {
			return null;
		}

		return array(
			$this->get_locale() => $this->get_summary(),
		);
	}

	/**
	 * Transform Embed blocks to block level link.
	 *
	 * Remote servers will simply drop iframe elements, rendering incomplete content.
	 *
	 * @see https://www.w3.org/TR/activitypub/#security-sanitizing-content
	 * @see https://www.w3.org/wiki/ActivityPub/Primer/HTML
	 *
	 * @param string $block_content The block content (html).
	 * @param object $block         The block object.
	 *
	 * @return string A block level link
	 */
	public static function revert_embed_links( $block_content, $block ) {
		if ( ! isset( $block['attrs']['url'] ) ) {
			return $block_content;
		}
		return '<p><a href="' . esc_url( $block['attrs']['url'] ) . '">' . $block['attrs']['url'] . '</a></p>';
	}

	/**
	 * Check if the post is a preview.
	 *
	 * @return boolean True if the post is a preview, false otherwise.
	 */
	private function is_preview() {
		return defined( 'ACTIVITYPUB_PREVIEW' ) && ACTIVITYPUB_PREVIEW;
	}
}
