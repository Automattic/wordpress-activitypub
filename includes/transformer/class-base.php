<?php
/**
 * Base Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Actors;
use Activitypub\Http;

use function Activitypub\get_upload_baseurl;
use function Activitypub\object_to_uri;

/**
 * WordPress Base Transformer.
 *
 * Transformers are responsible for transforming WordPress objects into different ActivityPub
 * Object-Types or Activities.
 */
abstract class Base {
	/**
	 * The WP_Post or WP_Comment object.
	 *
	 * This is the source object of the transformer.
	 *
	 * @var \WP_Post|\WP_Comment|Base_Object|string|array|\WP_Term
	 */
	protected $item;

	/**
	 * The WP_Post or WP_Comment object.
	 *
	 * @deprecated version 5.0.0
	 *
	 * @var \WP_Post|\WP_Comment
	 */
	protected $wp_object;

	/**
	 * The content visibility.
	 *
	 * @var string
	 */
	protected $content_visibility;

	/**
	 * Static function to Transform a WordPress Object.
	 *
	 * This helps to chain the output of the Transformer.
	 *
	 * @param \WP_Post|\WP_Comment|Base_Object|string|array|\WP_term $item The item that should be transformed.
	 *
	 * @return Base
	 */
	public static function transform( $item ) {
		return new static( $item );
	}

	/**
	 * Base constructor.
	 *
	 * @param \WP_Post|\WP_Comment|Base_Object|string|array|\WP_Term $item The item that should be transformed.
	 */
	public function __construct( $item ) {
		$this->item      = $item;
		$this->wp_object = $item;
	}

	/**
	 * Transform all properties with available get(ter) functions.
	 *
	 * @param Base_Object $activity_object The ActivityPub Object.
	 *
	 * @return Base_Object|\WP_Error The transformed ActivityPub Object or WP_Error on failure.
	 */
	protected function transform_object_properties( $activity_object ) {
		if ( ! $activity_object || \is_wp_error( $activity_object ) ) {
			return $activity_object;
		}

		// Save activity in the context of an activitypub request.
		\add_filter( 'activitypub_is_activitypub_request', '__return_true' );

		$vars = $activity_object->get_object_var_keys();

		foreach ( $vars as $var ) {
			$getter = 'get_' . $var;

			if ( \method_exists( $this, $getter ) ) {
				$value = \call_user_func( array( $this, $getter ) );

				if ( null !== $value ) {
					$setter = 'set_' . $var;

					/**
					 * Filter the value before it is set to the Activity-Object `$activity_object`.
					 *
					 * @param mixed $value The value that should be set.
					 * @param mixed $item  The Object.
					 */
					$value = \apply_filters( "activitypub_transform_{$setter}", $value, $this->item );

					/**
					 * Filter the value before it is set to the Activity-Object `$activity_object`.
					 *
					 * @param mixed  $value The value that should be set.
					 * @param string $var   The variable name.
					 * @param mixed  $item  The Object.
					 */
					$value = \apply_filters( 'activitypub_transform_set', $value, $var, $this->item );

					\call_user_func( array( $activity_object, $setter ), $value );
				}
			}
		}

		// Remove activity in the context of an activitypub request.
		\remove_filter( 'activitypub_is_activitypub_request', '__return_true' );

		return $activity_object;
	}

	/**
	 * Transform the item into an ActivityPub Object.
	 *
	 * @return Base_Object The Activity-Object.
	 */
	public function to_object() {
		$activity_object = new Base_Object();
		$activity_object = $this->transform_object_properties( $activity_object );

		if ( \is_wp_error( $activity_object ) ) {
			return $activity_object;
		}

		return $this->set_audience( $activity_object );
	}

	/**
	 * Get the content visibility.
	 *
	 * @return string The content visibility.
	 */
	public function get_content_visibility() {
		if ( ! $this->content_visibility ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
		}

		return $this->content_visibility;
	}

	/**
	 * Set the content visibility.
	 *
	 * @param string $content_visibility The content visibility.
	 */
	public function set_content_visibility( $content_visibility ) {
		$this->content_visibility = $content_visibility;

		return $this;
	}

	/**
	 * Set the audience.
	 *
	 * @param Base_Object $activity_object The ActivityPub Object.
	 *
	 * @return Base_Object The ActivityPub Object.
	 */
	protected function set_audience( $activity_object ) {
		$public     = 'https://www.w3.org/ns/activitystreams#Public';
		$followers  = null;
		$replied_to = null;

		$actor = Actors::get_by_resource( $this->get_attributed_to() );
		if ( ! \is_wp_error( $actor ) ) {
			$followers = $actor->get_followers();
		}

		$mentions = array_values( $this->get_mentions() );

		if ( $this->get_in_reply_to() ) {
			$object = Http::get_remote_object( $this->get_in_reply_to() );
			if ( $object && ! \is_wp_error( $object ) && isset( $object['attributedTo'] ) ) {
				$replied_to = array( object_to_uri( $object['attributedTo'] ) );
			}
		}

		switch ( $this->get_content_visibility() ) {
			case ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC:
				$activity_object->add_to( $public );
				$activity_object->add_cc( $followers );
				$activity_object->add_cc( $mentions );
				$activity_object->add_cc( $replied_to );
				break;
			case ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC:
				$activity_object->add_to( $followers );
				$activity_object->add_to( $mentions );
				$activity_object->add_to( $replied_to );
				$activity_object->add_cc( $public );
				break;
			case ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE:
				$activity_object->add_to( $mentions );
				$activity_object->add_to( $replied_to );
		}

		return $activity_object;
	}

	/**
	 * Transform the item to an ActivityPub ID.
	 *
	 * @return string The ID of the WordPress Object.
	 */
	public function to_id() {
		/* @var Attachment|Comment|Json|Post|User $this Object transformer. */
		return $this->get_id();
	}

	/**
	 * Transforms the ActivityPub Object to an Activity
	 *
	 * @param string $type The Activity-Type.
	 *
	 * @return Activity The Activity.
	 */
	public function to_activity( $type ) {
		$object = $this->to_object();

		$activity = new Activity();
		$activity->set_type( $type );

		// Pre-fill the Activity with data (for example, cc and to).
		$activity->set_object( $object );

		// Use simple Object (only ID-URI) for Like and Announce.
		if ( 'Like' === $type ) {
			$activity->set_object( $object->get_id() );
		}

		return $activity;
	}

	/**
	 * Returns a generic locale based on the Blog settings.
	 *
	 * @return string The locale of the blog.
	 */
	protected function get_locale() {
		$lang = \strtolower( \strtok( \get_locale(), '_-' ) );

		/**
		 * Filter the locale of the post.
		 *
		 * @param string $lang    The locale of the post.
		 * @param mixed  $item    The post object.
		 *
		 * @return string The filtered locale of the post.
		 */
		return apply_filters( 'activitypub_locale', $lang, $this->item );
	}

	/**
	 * Returns the default media type for an Object.
	 *
	 * @return string The media type.
	 */
	public function get_media_type() {
		return 'text/html';
	}

	/**
	 * Returns the content map for the post.
	 *
	 * @return array|null The content map for the post or null if not set.
	 */
	protected function get_content_map() {
		if ( ! \method_exists( $this, 'get_content' ) || ! $this->get_content() ) {
			return null;
		}

		return array(
			$this->get_locale() => $this->get_content(),
		);
	}

	/**
	 * Returns the name map for the post.
	 *
	 * @return array|null The name map for the post or null if not set.
	 */
	protected function get_name_map() {
		if ( ! \method_exists( $this, 'get_name' ) || ! $this->get_name() ) {
			return null;
		}

		return array(
			$this->get_locale() => $this->get_name(),
		);
	}

	/**
	 * Returns the summary map for the post.
	 *
	 * @return array|null The summary map for the post or null if not set.
	 */
	protected function get_summary_map() {
		if ( ! \method_exists( $this, 'get_summary' ) || ! $this->get_summary() ) {
			return null;
		}

		return array(
			$this->get_locale() => $this->get_summary(),
		);
	}

	/**
	 * Returns the tags for the post.
	 *
	 * @return array The tags for the post.
	 */
	protected function get_tag() {
		$tags     = array();
		$mentions = $this->get_mentions();

		foreach ( $mentions as $mention => $url ) {
			$tags[] = array(
				'type' => 'Mention',
				'href' => \esc_url( $url ),
				'name' => \esc_html( $mention ),
			);
		}

		return \array_unique( $tags, SORT_REGULAR );
	}

	/**
	 * Get the attributed to.
	 *
	 * @return string The attributed to.
	 */
	protected function get_attributed_to() {
		return null;
	}

	/**
	 * Extracts mentions from the content.
	 *
	 * @return array The mentions.
	 */
	protected function get_mentions() {
		$content = '';

		if ( method_exists( $this, 'get_content' ) ) {
			$content = $content . ' ' . $this->get_content();
		}

		if ( method_exists( $this, 'get_summary' ) ) {
			$content = $content . ' ' . $this->get_summary();
		}

		/**
		 * Filter the mentions in the post content.
		 *
		 * @param array    $mentions The mentions.
		 * @param string   $content  The post content.
		 * @param \WP_Post $post     The post object.
		 *
		 * @return array The filtered mentions.
		 */
		return apply_filters(
			'activitypub_extract_mentions',
			array(),
			$content,
			$this->item
		);
	}

	/**
	 * Returns the in reply to.
	 *
	 * @return string|array|null The in reply to.
	 */
	protected function get_in_reply_to() {
		return null;
	}

	/**
	 * Parse HTML content for image tags and extract attachment information.
	 *
	 * This method is used by both Post and Comment transformers to find images
	 * embedded in HTML content and extract their attachment IDs and alt text.
	 *
	 * @param array  $media      The existing media array grouped by type.
	 * @param int    $max_images Maximum number of images to extract.
	 * @param string $content    The HTML content to parse.
	 *
	 * @return array The updated media array with found images.
	 */
	protected function parse_html_images( $media, $max_images, $content ) {
		// If someone calls that function directly, bail.
		if ( ! \class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $media;
		}

		// Max images can't be negative or zero.
		if ( $max_images <= 0 ) {
			return $media;
		}

		$images = array();
		$base   = get_upload_baseurl();
		$tags   = new \WP_HTML_Tag_Processor( $content );

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

		if ( \count( $media['image'] ) <= $max_images ) {
			$media['image'] = \array_merge( $media['image'], $images );
		}

		return $media;
	}

	/**
	 * Transforms a WordPress attachment array to ActivityStreams attachment format.
	 *
	 * @param array $media The WordPress attachment array with 'id' and optional 'alt'.
	 *
	 * @return array The ActivityStreams attachment array.
	 */
	protected function transform_attachment( $media ) {
		if ( ! isset( $media['id'] ) ) {
			return $media;
		}

		$id         = $media['id'];
		$attachment = array();
		$mime_type  = \get_post_mime_type( $id );
		$media_type = \strtok( $mime_type, '/' );

		// Switching on image/audio/video.
		switch ( $media_type ) {
			case 'image':
				$image_size = 'large';

				/**
				 * Filter the image URL returned for each post.
				 *
				 * @param array|false $thumbnail  The image URL, or false if no image is available.
				 * @param int         $id         The attachment ID.
				 * @param string      $image_size The image size to retrieve. Set to 'large' by default.
				 */
				$thumbnail = \apply_filters( 'activitypub_get_image', $this->get_attachment_image_src( $id, $image_size ), $id, $image_size );

				if ( $thumbnail ) {
					$image = array(
						'type'      => 'Image',
						'url'       => \esc_url( $thumbnail[0] ),
						'mediaType' => \esc_attr( $mime_type ),
					);

					if ( ! empty( $media['alt'] ) ) {
						$image['name'] = \html_entity_decode( \wp_strip_all_tags( $media['alt'] ), ENT_QUOTES, 'UTF-8' );
					} else {
						$alt = \get_post_meta( $id, '_wp_attachment_image_alt', true );
						if ( $alt ) {
							$image['name'] = \html_entity_decode( \wp_strip_all_tags( $alt ), ENT_QUOTES, 'UTF-8' );
						}
					}

					$attachment = $image;
				}
				break;

			case 'audio':
			case 'video':
				$meta       = \wp_get_attachment_metadata( $id );
				$attachment = array(
					'type'      => \ucfirst( $media_type ),
					'mediaType' => \esc_attr( $mime_type ),
					'url'       => \esc_url( \wp_get_attachment_url( $id ) ),
					'name'      => \esc_attr( \get_the_title( $id ) ),
				);

				// Height and width for videos.
				if ( isset( $meta['width'], $meta['height'] ) ) {
					$attachment['width']  = \esc_attr( $meta['width'] );
					$attachment['height'] = \esc_attr( $meta['height'] );
				}

				if ( \method_exists( $this, 'get_icon' ) && $this->get_icon() ) {
					$attachment['icon'] = object_to_uri( $this->get_icon() );
				}
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
	protected function get_attachment_image_src( $id, $image_size = 'large' ) {
		/**
		 * Hook into the image retrieval process. Before image retrieval.
		 *
		 * @param int    $id         The attachment ID.
		 * @param string $image_size The image size to retrieve. Set to 'large' by default.
		 */
		\do_action( 'activitypub_get_image_pre', $id, $image_size );

		$image = \wp_get_attachment_image_src( $id, $image_size );

		/**
		 * Hook into the image retrieval process. After image retrieval.
		 *
		 * @param int    $id         The attachment ID.
		 * @param string $image_size The image size to retrieve. Set to 'large' by default.
		 */
		\do_action( 'activitypub_get_image_post', $id, $image_size );

		return $image;
	}

	/**
	 * Filter attachments to ensure uniqueness based on their ID.
	 *
	 * @param array $attachments Array of attachments with 'id' field.
	 *
	 * @return array Array with duplicate attachments removed.
	 */
	protected function filter_unique_attachments( $attachments ) {
		$seen_ids = array();

		return \array_filter(
			$attachments,
			function ( $attachment ) use ( &$seen_ids ) {
				if ( isset( $attachment['id'] ) && ! in_array( $attachment['id'], $seen_ids, true ) ) {
					$seen_ids[] = $attachment['id'];
					return true;
				}
				return false;
			}
		);
	}
}
