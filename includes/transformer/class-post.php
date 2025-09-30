<?php
/**
 * WordPress Post Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Blocks;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Interactions;
use Activitypub\Collection\Replies;
use Activitypub\Model\Blog;
use Activitypub\Shortcodes;

use function Activitypub\esc_hashtag;
use function Activitypub\generate_post_summary;
use function Activitypub\get_content_visibility;
use function Activitypub\get_content_warning;
use function Activitypub\get_enclosures;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\is_single_user;
use function Activitypub\site_supports_blocks;

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
	 * Transforms the WP_Post object to an ActivityPub Object
	 *
	 * @return \Activitypub\Activity\Base_Object The ActivityPub Object
	 */
	public function to_object() {
		$post   = $this->item;
		$object = parent::to_object();

		$content_warning = get_content_warning( $post );
		if ( ! empty( $content_warning ) ) {
			$object->set_sensitive( true );
			$object->set_summary( $content_warning );
			$object->set_summary_map( null );
			$object->set_dcterms( array( 'subject' => $content_warning ) );
		}

		return $object;
	}

	/**
	 * Get the content visibility.
	 *
	 * @return string The content visibility.
	 */
	public function get_content_visibility() {
		if ( ! $this->content_visibility ) {
			return get_content_visibility( $this->item );
		}

		return $this->content_visibility;
	}

	/**
	 * Get the Interaction Policy.
	 *
	 * @see https://docs.gotosocial.org/en/latest/federation/interaction_policy/
	 *
	 * @return array The interaction policy.
	 */
	public function get_interaction_policy() {
		return array(
			'canAnnounce' => $this->get_public_interaction_policy(),
			'canLike'     => $this->get_public_interaction_policy(),
			'canQuote'    => $this->get_quote_policy(),
			'canReply'    => $this->get_public_interaction_policy(),
		);
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

		$user = Actors::get_by_id( $this->item->post_author );

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
		$post_id        = (int) $this->item->ID;

		if ( $post_id > $last_legacy_id ) {
			// Generate URI based on post ID.
			return \add_query_arg( 'p', $post_id, \home_url( '/' ) );
		}

		return $this->get_url();
	}

	/**
	 * Returns the URL of the Post.
	 *
	 * @return string The Posts URL.
	 */
	public function get_url() {
		$post = $this->item;

		switch ( \get_post_status( $post ) ) {
			case 'trash':
				$permalink = \get_post_meta( $post->ID, '_activitypub_canonical_url', true );
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
		$post_id = $this->item->ID;

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
			$this->get_attachment_image_src( $id, $image_size ),
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
			$image['name'] = \html_entity_decode( \wp_strip_all_tags( $alt ), ENT_QUOTES, 'UTF-8' );
		}

		return $image;
	}

	/**
	 * Returns an Icon, based on the Featured Image with a fallback to the site-icon.
	 *
	 * @return array|null The Icon or null if no icon is available.
	 */
	protected function get_icon() {
		$post_id = $this->item->ID;

		// List post thumbnail first if this post has one.
		if ( \has_post_thumbnail( $post_id ) ) {
			$id = \get_post_thumbnail_id( $post_id );
		} else {
			// Try site_logo, falling back to site_icon, first.
			$id = get_option( 'site_icon' );
		}

		if ( ! $id ) {
			return null;
		}

		$image_size = 'thumbnail';

		/**
		 * Filter the image URL returned for each post.
		 *
		 * @param array|false $thumbnail  The image URL, or false if no image is available.
		 * @param int         $id         The attachment ID.
		 * @param string      $image_size The image size to retrieve. Set to 'large' by default.
		 */
		$thumbnail = apply_filters(
			'activitypub_get_image',
			$this->get_attachment_image_src( $id, $image_size ),
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
			$image['name'] = \html_entity_decode( \wp_strip_all_tags( $alt ), ENT_QUOTES, 'UTF-8' );
		}

		return $image;
	}

	/**
	 * Generates all Media Attachments for a Post.
	 *
	 * @return array The Attachments.
	 */
	protected function get_attachment() {
		/*
		 * Remove attachments from the Fediverse if a post was federated and then set back to draft.
		 * Except in preview mode, where we want to show attachments.
		 */
		if ( ! $this->is_preview() && 'draft' === \get_post_status( $this->item ) ) {
			return array();
		}

		$max_media = \get_post_meta( $this->item->ID, 'activitypub_max_image_attachments', true );

		if ( ! is_numeric( $max_media ) ) {
			$max_media = \get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS );
		}

		/**
		 * Filters the maximum number of media attachments allowed in a post.
		 *
		 * Despite the name suggesting only images, this filter controls the maximum number
		 * of all media attachments (images, audio, and video) that can be included in an
		 * ActivityPub post. The name is maintained for backwards compatibility.
		 *
		 * @param int $max_media Maximum number of media attachments. Default ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS.
		 */
		$max_media = (int) \apply_filters( 'activitypub_max_image_attachments', $max_media );

		if ( 0 === $max_media ) {
			return array();
		}

		$media = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);
		$id    = $this->item->ID;

		// List post thumbnail first if this post has one.
		if ( \has_post_thumbnail( $id ) ) {
			$media['image'][] = array( 'id' => \get_post_thumbnail_id( $id ) );
		}

		$media = $this->get_enclosures( $media );

		if ( site_supports_blocks() && \has_blocks( $this->item->post_content ) ) {
			$media = $this->get_block_attachments( $media, $max_media );
		} else {
			$media = $this->parse_html_images( $media, $max_media, $this->item->post_content );
		}

		$media = $this->filter_media_by_object_type( $media, \get_post_format( $this->item ), $this->item );
		$media = $this->filter_unique_attachments( $media );
		$media = \array_slice( $media, 0, $max_media );

		/**
		 * Filter the attachment IDs for a post.
		 *
		 * @param array    $media The media array grouped by type.
		 * @param \WP_Post $item  The post object.
		 *
		 * @return array The filtered attachment IDs.
		 */
		$media = \apply_filters( 'activitypub_attachment_ids', $media, $this->item );

		$attachments = \array_filter( \array_map( array( $this, 'transform_attachment' ), $media ) );

		/**
		 * Filter the attachments for a post.
		 *
		 * @param array    $attachments The attachments.
		 * @param \WP_Post $item        The post object.
		 *
		 * @return array The filtered attachments.
		 */
		return \apply_filters( 'activitypub_attachments', $attachments, $this->item );
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

		$has_title = \post_type_supports( $this->item->post_type, 'title' );
		$content   = \wp_strip_all_tags( $this->item->post_content );

		// Check if the post has a title.
		if (
			! $has_title ||
			! $this->item->post_title ||
			\strlen( $content ) <= ACTIVITYPUB_NOTE_LENGTH
		) {
			return 'Note';
		}

		// Default to Note.
		$object_type = 'Note';
		$post_type   = \get_post_type( $this->item );

		if ( 'page' === $post_type ) {
			$object_type = 'Page';
		} elseif ( ! \get_post_format( $this->item ) ) {
			$object_type = 'Article';
		}

		return $object_type;
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
		$tags = parent::get_tag();

		$post_tags = \get_the_tags( $this->item->ID );
		if ( $post_tags ) {
			foreach ( $post_tags as $post_tag ) {
				// Tag can be empty.
				if ( ! $post_tag ) {
					continue;
				}

				$tags[] = array(
					'type' => 'Hashtag',
					'href' => \esc_url( \get_tag_link( $post_tag->term_id ) ),
					'name' => esc_hashtag( $post_tag->name ),
				);
			}
		}

		return \array_unique( $tags, SORT_REGULAR );
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
		if ( ! $this->is_preview() && 'draft' === \get_post_status( $this->item ) ) {
			return \__( '(This post is being modified)', 'activitypub' );
		}

		return generate_post_summary( $this->item );
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

		$title = \get_the_title( $this->item->ID );

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
		// Remove Content from drafts.
		if ( ! $this->is_preview() && 'draft' === \get_post_status( $this->item ) ) {
			return \__( '(This post is being modified)', 'activitypub' );
		}

		global $post;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = $this->item;
		$content = $this->get_post_content_template();

		/**
		 * Provides an action hook so plugins can add their own hooks/filters before AP content is generated.
		 *
		 * Example: if a plugin adds a filter to `the_content` to add a button to the end of posts, it can also remove that filter here.
		 *
		 * @param \WP_Post $post The post object.
		 */
		\do_action( 'activitypub_before_get_content', $post );

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

		// Don't need these anymore, should never appear in a post.
		Shortcodes::unregister();

		/**
		 * Filters the post content after it was transformed for ActivityPub.
		 *
		 * @param string   $content The transformed post content.
		 * @param \WP_Post $post    The post object being transformed.
		 */
		return \apply_filters( 'activitypub_the_content', $content, $post );
	}

	/**
	 * Generate HTML @ link for reply block.
	 *
	 * @deprecated 7.4.0 Use {@see Blocks::generate_reply_link()}.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string The HTML @ link.
	 */
	public function generate_reply_link( $block_content, $block ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Blocks::generate_reply_link' );

		return Blocks::generate_reply_link( $block_content, $block );
	}

	/**
	 * Returns the in-reply-to URL of the post.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-inreplyto
	 *
	 * @return string|array|null The in-reply-to URL of the post.
	 */
	protected function get_in_reply_to() {
		if ( ! site_supports_blocks() ) {
			return null;
		}

		$reply_urls = array();
		$blocks     = \parse_blocks( $this->item->post_content );

		foreach ( $blocks as $block ) {
			if ( 'activitypub/reply' === $block['blockName'] && isset( $block['attrs']['url'] ) ) {
				// We only support one reply block per post for now.
				$reply_urls[] = $block['attrs']['url'];
			}
		}

		if ( empty( $reply_urls ) ) {
			return null;
		}

		if ( 1 === count( $reply_urls ) ) {
			return \current( $reply_urls );
		}

		return \array_values( \array_unique( $reply_urls ) );
	}

	/**
	 * Returns the published date of the post.
	 *
	 * @return string The published date of the post.
	 */
	protected function get_published() {
		$published = \strtotime( $this->item->post_date_gmt );

		return \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, $published );
	}

	/**
	 * Returns the updated date of the post.
	 *
	 * @return string|null The updated date of the post.
	 */
	protected function get_updated() {
		$published = \strtotime( $this->item->post_date_gmt );
		$updated   = \strtotime( $this->item->post_modified_gmt );

		if ( $updated > $published ) {
			return \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, $updated );
		}

		return null;
	}

	/**
	 * Helper function to extract the @-Mentions from the post content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentions() {
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
			$this->item->post_content . ' ' . $this->item->post_excerpt,
			$this->item
		);
	}

	/**
	 * Transform Embed blocks to block level link.
	 *
	 * Remote servers will simply drop iframe elements, rendering incomplete content.
	 *
	 * @deprecated 7.4.0 Use {@see Blocks::revert_embed_links()}.
	 *
	 * @see https://www.w3.org/TR/activitypub/#security-sanitizing-content
	 * @see https://www.w3.org/wiki/ActivityPub/Primer/HTML
	 *
	 * @param string $block_content The block content (html).
	 * @param object $block         The block object.
	 *
	 * @return string A block level link
	 */
	public function revert_embed_links( $block_content, $block ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Blocks::revert_embed_links' );

		return Blocks::revert_embed_links( $block_content, $block );
	}

	/**
	 * Check if the post is a preview.
	 *
	 * @return boolean True if the post is a preview, false otherwise.
	 */
	private function is_preview() {
		return defined( 'ACTIVITYPUB_PREVIEW' ) && ACTIVITYPUB_PREVIEW;
	}

	/**
	 * Get enclosures for a post.
	 *
	 * @param array $media The media array grouped by type.
	 *
	 * @return array The media array extended with enclosures.
	 */
	protected function get_enclosures( $media ) {
		$enclosures = get_enclosures( $this->item->ID );

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

			$mime_type         = $enclosure['mediaType'];
			$media_type        = \strtok( $mime_type, '/' );
			$enclosure['type'] = \ucfirst( $media_type );

			switch ( $media_type ) {
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

		$blocks = \parse_blocks( $this->item->post_content );

		return $this->get_media_from_blocks( $blocks, $media );
	}

	/**
	 * Recursively get media IDs from blocks.
	 *
	 * @param array $blocks The blocks to search for media IDs.
	 * @param array $media  The media IDs to append new IDs to.
	 *
	 * @return array The image IDs.
	 */
	protected function get_media_from_blocks( $blocks, $media ) {
		foreach ( $blocks as $block ) {
			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$media = $this->get_media_from_blocks( $block['innerBlocks'], $media );
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

						$found = false;
						foreach ( $media['image'] as $i => $image ) {
							if ( isset( $image['id'] ) && $image['id'] === $block['attrs']['id'] ) {
								$media['image'][ $i ]['alt'] = $alt;
								$found                       = true;
								break;
							}
						}

						if ( ! $found ) {
							$media['image'][] = array(
								'id'  => $block['attrs']['id'],
								'alt' => $alt,
							);
						}
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
	 * Filter media IDs by object type.
	 *
	 * @param array    $media The media array grouped by type.
	 * @param string   $type  The object type.
	 * @param \WP_Post $item  The post object.
	 *
	 * @return array The filtered media IDs.
	 */
	protected function filter_media_by_object_type( $media, $type, $item ) {
		/**
		 * Filter the object type for media attachments.
		 *
		 * @param string   $type      The object type.
		 * @param \WP_Post $item The post object.
		 *
		 * @return string The filtered object type.
		 */
		$type = \apply_filters( 'filter_media_by_object_type', \strtolower( $type ), $item );

		if ( ! empty( $media[ $type ] ) ) {
			return $media[ $type ];
		}

		return array_filter( array_merge( ...array_values( $media ) ) );
	}

	/**
	 * Converts a WordPress Attachment to an ActivityPub Attachment.
	 *
	 * @deprecated 7.2.0 Use {@see Base::transform_attachment()} instead.
	 *
	 * @param array $media The Attachment array.
	 *
	 * @return array The ActivityPub Attachment.
	 */
	public function wp_attachment_to_activity_attachment( $media ) {
		_deprecated_function( __METHOD__, '7.2.0', '\Activitypub\Transformer\Base::transform_attachment()' );

		return parent::transform_attachment( $media );
	}

	/**
	 * Get the context of the post.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-context
	 *
	 * @return string The context of the post.
	 */
	protected function get_context() {
		return get_rest_url_by_path( sprintf( 'posts/%d/context', $this->item->ID ) );
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

		/**
		 * Filters the template used to generate ActivityPub object content.
		 *
		 * This filter allows developers to modify the template that determines how post
		 * content is formatted in ActivityPub objects. The template can include special
		 * shortcodes like [ap_title] and [ap_content] that are processed during content
		 * generation.
		 *
		 * @param string   $template  The template string containing shortcodes.
		 * @param \WP_Post $item The WordPress post object being transformed.
		 */
		return apply_filters( 'activitypub_object_content_template', $template, $this->item );
	}

	/**
	 * Get the replies Collection.
	 *
	 * @return array|null The replies collection on success or null on failure.
	 */
	public function get_replies() {
		return Replies::get_collection( $this->item );
	}

	/**
	 * Get the likes Collection.
	 *
	 * @return array The likes collection.
	 */
	public function get_likes() {
		return array(
			'id'         => get_rest_url_by_path( sprintf( 'posts/%d/likes', $this->item->ID ) ),
			'type'       => 'Collection',
			'totalItems' => Interactions::count_by_type( $this->item->ID, 'like' ),
		);
	}

	/**
	 * Get the shares Collection.
	 *
	 * @return array The Shares collection.
	 */
	public function get_shares() {
		return array(
			'id'         => get_rest_url_by_path( sprintf( 'posts/%d/shares', $this->item->ID ) ),
			'type'       => 'Collection',
			'totalItems' => Interactions::count_by_type( $this->item->ID, 'repost' ),
		);
	}

	/**
	 * Get the preview of the post.
	 *
	 * @return array|null The preview of the post or null if the post is not an Article.
	 */
	public function get_preview() {
		if ( 'Article' !== $this->get_type() ) {
			return null;
		}

		return array(
			'type'    => 'Note',
			'content' => $this->get_summary(),
		);
	}

	/**
	 * Get the quote policy.
	 *
	 * @return array The quote policy.
	 */
	private function get_quote_policy() {
		switch ( \get_post_meta( $this->item->ID, 'activitypub_interaction_policy_quote', true ) ) {
			case ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS:
				return array( 'automaticApproval' => get_rest_url_by_path( sprintf( 'actors/%d/followers', $this->item->post_author ) ) );

			case ACTIVITYPUB_INTERACTION_POLICY_ME:
				return array( 'automaticApproval' => $this->get_self_interaction_policy() );

			default:
				return $this->get_public_interaction_policy();
		}
	}

	/**
	 * Get the public interaction policy.
	 *
	 * @return array The public interaction policy.
	 */
	private function get_public_interaction_policy() {
		return array(
			'automaticApproval' => 'https://www.w3.org/ns/activitystreams#Public',
			'always'            => 'https://www.w3.org/ns/activitystreams#Public',
		);
	}

	/**
	 * Get the actor ID(s) for the `me` audience for use in interaction policies.
	 *
	 * @return string|array The actor ID(s).
	 */
	private function get_self_interaction_policy() {
		switch ( \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ) {
			case ACTIVITYPUB_BLOG_MODE:
				return ( new Blog() )->get_id();

			case ACTIVITYPUB_ACTOR_AND_BLOG_MODE:
				return array(
					$this->get_actor_object()->get_id(),
					( new Blog() )->get_id(),
				);

			default:
				return $this->get_actor_object()->get_id();
		}
	}
}
