<?php
namespace Activitypub\Transformer;

use WP_Post;
use Activitypub\Shortcodes;
use Activitypub\Model\Blog_User;
use Activitypub\Transformer\Base;
use Activitypub\Collection\Users;
use Activitypub\Activity\Base_Object;

use function Activitypub\esc_hashtag;
use function Activitypub\is_single_user;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\site_supports_blocks;

/**
 * WordPress Post Transformer
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
	 * Returns the ID of the WordPress Post.
	 *
	 * @return int The ID of the WordPress Post
	 */
	public function get_wp_user_id() {
		return $this->object->post_author;
	}

	/**
	 * Change the User-ID of the WordPress Post.
	 *
	 * @return int The User-ID of the WordPress Post
	 */
	public function change_wp_user_id( $user_id ) {
		$this->object->post_author = $user_id;

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
		$post = $this->object;
		$object = parent::to_object();

		$published = \strtotime( $post->post_date_gmt );

		$object->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', $published ) );

		$updated = \strtotime( $post->post_modified_gmt );

		if ( $updated > $published ) {
			$object->set_updated( \gmdate( 'Y-m-d\TH:i:s\Z', $updated ) );
		}

		$object->set_content_map(
			array(
				$this->get_locale() => $this->get_content(),
			)
		);
		$path = sprintf( 'users/%d/followers', intval( $post->post_author ) );

		$object->set_to(
			array(
				'https://www.w3.org/ns/activitystreams#Public',
				get_rest_url_by_path( $path ),
			)
		);

		return $object;
	}

	/**
	 * Returns the ID of the Post.
	 *
	 * @return string The Posts ID.
	 */
	public function get_id() {
		return $this->get_url();
	}

	/**
	 * Returns the URL of the Post.
	 *
	 * @return string The Posts URL.
	 */
	public function get_url() {
		$post = $this->object;

		if ( 'trash' === get_post_status( $post ) ) {
			$permalink = \get_post_meta( $post->ID, 'activitypub_canonical_url', true );
		} else {
			$permalink = \get_permalink( $post );
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
		if ( is_single_user() ) {
			$user = new Blog_User();
			return $user->get_url();
		}

		return Users::get_by_id( $this->object->post_author )->get_url();
	}

	/**
	 * Generates all Media Attachments for a Post.
	 *
	 * @return array The Attachments.
	 */
	protected function get_attachment() {
		// Once upon a time we only supported images, but we now support audio/video as well.
		// We maintain the image-centric naming for backwards compatibility.
		$max_media = intval( \apply_filters( 'activitypub_max_image_attachments', \get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS ) ) );

		if ( site_supports_blocks() && \has_blocks( $this->object->post_content ) ) {
			return $this->get_block_attachments( $max_media );
		}

		return $this->get_classic_editor_images( $max_media );
	}

	/**
	 * Get media attachments from blocks. They will be formatted as ActivityPub attachments, not as WP attachments.
	 *
	 * @param int $max_media The maximum number of attachments to return.
	 *
	 * @return array The attachments.
	 */
	protected function get_block_attachments( $max_media ) {
		// max media can't be negative or zero
		if ( $max_media <= 0 ) {
			return array();
		}

		$id = $this->object->ID;

		$media_ids = array();

		// list post thumbnail first if this post has one
		if ( \function_exists( 'has_post_thumbnail' ) && \has_post_thumbnail( $id ) ) {
			$media_ids[] = \get_post_thumbnail_id( $id );
		}

		if ( $max_media > 0 ) {
			$blocks = \parse_blocks( $this->object->post_content );
			$media_ids = self::get_media_ids_from_blocks( $blocks, $media_ids, $max_media );
		}

		return \array_filter( \array_map( array( self::class, 'wp_attachment_to_activity_attachment' ), $media_ids ) );
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
		// max images can't be negative or zero
		if ( $max_images <= 0 ) {
			return array();
		}
		$image_ids = array();
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
		return $image_ids;
	}

	/**
	 * Get image embeds from the classic editor by parsing HTML.
	 *
	 * @param int $max_images The maximum number of images to return.
	 *
	 * @return array The attachment IDs.
	 */
	protected function get_classic_editor_image_embeds( $max_images ) {
		// max images can't be negative or zero
		if ( $max_images <= 0 ) {
			return array();
		}
		$image_ids = array();
		$base = \wp_get_upload_dir()['baseurl'];
		$content = \get_post_field( 'post_content', $this->object );
		$tags = new \WP_HTML_Tag_Processor( $content );
		while ( $tags->next_tag( 'img' ) && ( \count( $image_ids ) < $max_images ) ) {
			$src = $tags->get_attribute( 'src' );
			// If the img source is in our uploads dir, get the
			// associated ID. Note: if there's a -500x500 or -scaled
			// type suffix, we remove it, but we try the original
			// first in case the original image is actually called
			// that.
			if ( $src !== null && \str_starts_with( $src, $base ) ) {
				$img_id = \attachment_url_to_postid( $src );
				if ( $img_id === 0 ) {
					$src_repl = preg_replace( '/-(?:\d+x\d+|scaled)(\.[a-zA-Z]+)/', '$1', $src );
					$img_id = \attachment_url_to_postid( $src_repl );
				}
				if ( $img_id !== 0 ) {
					if ( ! \in_array( $img_id, $image_ids, true ) ) {
						$image_ids[] = $img_id;
					}
				}
			}
		}
		return $image_ids;
	}

	/**
	 * Get post images from the classic editor.
	 * Note that audio/video attachments are only supported in the block editor.
	 *
	 * @param int $max_images The maximum number of images to return.
	 *
	 * @return array The attachments.
	 */
	protected function get_classic_editor_images( $max_images ) {
		// max images can't be negative or zero
		if ( $max_images <= 0 ) {
			return array();
		}

		$id = $this->object->ID;

		$image_ids = array();

		// list post thumbnail first if this post has one
		if ( \function_exists( 'has_post_thumbnail' ) && \has_post_thumbnail( $id ) ) {
			$image_ids[] = \get_post_thumbnail_id( $id );
		}

		if ( \count( $image_ids ) < $max_images ) {
			if ( \class_exists( '\WP_HTML_Tag_Processor' ) ) {
				$image_ids = \array_merge ($image_ids, $this->get_classic_editor_image_embeds( $max_images ) );
			} else {
				$image_ids = \array_merge ($image_ids, $this->get_classic_editor_image_attachments( $max_images ) );
			}
		}
		// unique then slice as the thumbnail may duplicate another image
		$image_ids = \array_slice ( \array_unique( $image_ids ), 0, $max_images );

		return \array_filter( \array_map( array( self::class, 'wp_attachment_to_activity_attachment' ), $image_ids ) );
	}

	/**
	 * Recursively get media IDs from blocks.
	 * @param array $blocks The blocks to search for media IDs
	 * @param array $media_ids The media IDs to append new IDs to
	 * @param int $max_media The maximum number of media to return.
	 *
	 * @return array The image IDs.
	 */
	protected static function get_media_ids_from_blocks( $blocks, $media_ids, $max_media ) {

		foreach ( $blocks as $block ) {
			// recurse into inner blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$media_ids = self::get_media_ids_from_blocks( $block['innerBlocks'], $media_ids, $max_media );
			}

			switch ( $block['blockName'] ) {
				case 'core/image':
				case 'core/cover':
				case 'core/audio':
				case 'core/video':
				case 'videopress/video':
					if ( ! empty( $block['attrs']['id'] ) ) {
						$media_ids[] = $block['attrs']['id'];
					}
					break;
				case 'jetpack/slideshow':
				case 'jetpack/tiled-gallery':
					if ( ! empty( $block['attrs']['ids'] ) ) {
						$media_ids = array_merge( $media_ids, $block['attrs']['ids'] );
					}
					break;
				case 'jetpack/image-compare':
					if ( ! empty( $block['attrs']['beforeImageId'] ) ) {
						$media_ids[] = $block['attrs']['beforeImageId'];
					}
					if ( ! empty( $block['attrs']['afterImageId'] ) ) {
						$media_ids[] = $block['attrs']['afterImageId'];
					}
					break;
			}

			// depupe
			$media_ids = \array_unique( $media_ids );

			// stop doing unneeded work
			if ( count( $media_ids ) >= $max_media ) {
				break;
			}
		}

		// still need to slice it because one gallery could knock us over the limit
		return array_slice( $media_ids, 0, $max_media );
	}

	/**
	 * Converts a WordPress Attachment to an ActivityPub Attachment.
	 *
	 * @param int $id The Attachment ID.
	 *
	 * @return array The ActivityPub Attachment.
	 */
	public static function wp_attachment_to_activity_attachment( $id ) {
		$attachment = array();
		$mime_type = \get_post_mime_type( $id );
		$mime_type_parts = \explode( '/', $mime_type );
		// switching on image/audio/video
		switch ( $mime_type_parts[0] ) {
			case 'image':
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
					self::get_wordpress_attachment( $id, $image_size ),
					$id,
					$image_size
				);

				if ( $thumbnail ) {
					$alt   = \get_post_meta( $id, '_wp_attachment_image_alt', true );
					$image = array(
						'type'      => 'Image',
						'url'       => $thumbnail[0],
						'mediaType' => $mime_type,
					);

					if ( $alt ) {
						$image['name'] = $alt;
					}
					$attachment = $image;
				}
				break;

			case 'audio':
			case 'video':
				$attachment = array(
					'type'      => 'Document',
					'mediaType' => $mime_type,
					'url'       => \wp_get_attachment_url( $id ),
					'name'      => \get_the_title( $id ),
				);
				$meta = wp_get_attachment_metadata( $id );
				// height and width for videos
				if ( isset( $meta['width'] ) && isset( $meta['height'] ) ) {
					$attachment['width'] = $meta['width'];
					$attachment['height'] = $meta['height'];
				}
				// @todo: add `icon` support for audio/video attachments. Maybe use post thumbnail?
				break;
		}

		return \apply_filters( 'activitypub_attachment', $attachment, $id );
	}

	/**
	 * Return details about an image attachment.
	 *
	 * @param int    $id         The attachment ID.
	 * @param string $image_size The image size to retrieve. Set to 'full' by default.
	 *
	 * @return array|false Array of image data, or boolean false if no image is available.
	 */
	protected static function get_wordpress_attachment( $id, $image_size = 'full' ) {
		/**
		 * Hook into the image retrieval process. Before image retrieval.
		 *
		 * @param int    $id         The attachment ID.
		 * @param string $image_size The image size to retrieve. Set to 'full' by default.
		 */
		do_action( 'activitypub_get_image_pre', $id, $image_size );

		$image = \wp_get_attachment_image_src( $id, $image_size );

		/**
		 * Hook into the image retrieval process. After image retrieval.
		 *
		 * @param int    $id         The attachment ID.
		 * @param string $image_size The image size to retrieve. Set to 'full' by default.
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
		if ( 'wordpress-post-format' !== \get_option( 'activitypub_object_type', 'note' ) ) {
			return \ucfirst( \get_option( 'activitypub_object_type', 'note' ) );
		}

		// Default to Article.
		$object_type = 'Article';
		$post_type = \get_post_type( $this->object );
		switch ( $post_type ) {
			case 'post':
				$post_format = \get_post_format( $this->object );
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
			foreach ( $mentions as $url ) {
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
	protected function get_tag() {
		$tags = array();

		$post_tags = \get_the_tags( $this->object->ID );
		if ( $post_tags ) {
			foreach ( $post_tags as $post_tag ) {
				$tag = array(
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

		/**
		 * Provides an action hook so plugins can add their own hooks/filters before AP content is generated.
		 *
		 * Example: if a plugin adds a filter to `the_content` to add a button to the end of posts, it can also remove that filter here.
		 *
		 * @param WP_Post $post The post object.
		 */
		do_action( 'activitypub_before_get_content', $post );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = $this->object;
		$content = $this->get_post_content_template();

		// Register our shortcodes just in time.
		Shortcodes::register();
		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$content = \wpautop( $content );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );

		$content = \apply_filters( 'activitypub_the_content', $content, $post );

		// Don't need these any more, should never appear in a post.
		Shortcodes::unregister();

		return $content;
	}

	/**
	 * Gets the template to use to generate the content of the activitypub item.
	 *
	 * @return string The Template.
	 */
	protected function get_post_content_template() {
		$type = \get_option( 'activitypub_post_content_type', 'content' );

		switch ( $type ) {
			case 'excerpt':
				$template = "[ap_excerpt]\n\n[ap_permalink type=\"html\"]";
				break;
			case 'title':
				$template = "[ap_title]\n\n[ap_permalink type=\"html\"]";
				break;
			case 'content':
				$template = "[ap_content]\n\n[ap_permalink type=\"html\"]\n\n[ap_hashtags]";
				break;
			default:
				$template = \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );
				break;
		}

		return apply_filters( 'activitypub_object_content_template', $template, $this->object );
	}

	/**
	 * Helper function to get the @-Mentions from the post content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentions() {
		return apply_filters( 'activitypub_extract_mentions', array(), $this->object->post_content, $this->object );
	}

	/**
	 * Returns the locale of the post.
	 *
	 * @return string The locale of the post.
	 */
	public function get_locale() {
		$post_id = $this->object->ID;
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
		return apply_filters( 'activitypub_post_locale', $lang, $post_id, $this->object );
	}
}
