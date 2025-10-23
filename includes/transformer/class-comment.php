<?php
/**
 * WordPress Comment Transformer file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Replies;
use Activitypub\Comment as Comment_Utils;
use Activitypub\Model\Blog;
use Activitypub\Webfinger;

use function Activitypub\get_comment_ancestors;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\is_single_user;
use function Activitypub\was_comment_received;

/**
 * WordPress Comment Transformer.
 *
 * The Comment Transformer is responsible for transforming a WP_Comment object into different
 * Object-Types.
 *
 * Currently supported are:
 *
 * - Activitypub\Activity\Base_Object
 */
class Comment extends Base {
	/**
	 * The User as Actor Object.
	 *
	 * @var \Activitypub\Activity\Actor
	 */
	private $actor_object = null;

	/**
	 * Transforms the WP_Comment object to an ActivityPub Object.
	 *
	 * @return \Activitypub\Activity\Base_Object The ActivityPub Object.
	 */
	public function to_object() {
		$comment = $this->item;
		$object  = parent::to_object();

		$object->set_url( $this->get_id() );
		$object->set_type( 'Note' );

		$published = \strtotime( $comment->comment_date_gmt );
		$object->set_published( \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, $published ) );

		$updated = \get_comment_meta( $comment->comment_ID, 'activitypub_comment_modified', true );
		if ( $updated > $published ) {
			$object->set_updated( \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, $updated ) );
		}

		$object->set_content_map(
			array(
				$this->get_locale() => $this->get_content(),
			)
		);

		return $object;
	}

	/**
	 * Get the content visibility.
	 *
	 * @return string The content visibility.
	 */
	public function get_content_visibility() {
		if ( $this->content_visibility ) {
			return $this->content_visibility;
		}

		$comment = $this->item;
		$post    = \get_post( $comment->comment_post_ID );

		if ( ! $post ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
		}

		$content_visibility = \get_post_meta( $post->ID, 'activitypub_content_visibility', true );

		if ( ! $content_visibility ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
		}

		$this->content_visibility = $content_visibility;

		return $this->content_visibility;
	}

	/**
	 * Returns the User-URL of the Author of the Post.
	 *
	 * If `single_user` mode is enabled, the URL of the Blog-User is returned.
	 *
	 * @return string The User-URL.
	 */
	protected function get_attributed_to() {
		// If the comment was received via ActivityPub, return the author URL.
		if ( was_comment_received( $this->item ) ) {
			return $this->item->comment_author_url;
		}

		return $this->get_actor_object()->get_id();
	}

	/**
	 * Returns the content for the ActivityPub Item.
	 *
	 * The content will be generated based on the user settings.
	 *
	 * @return string The content.
	 */
	protected function get_content() {
		$comment  = $this->item;
		$content  = $comment->comment_content;
		$mentions = '';

		foreach ( $this->extract_reply_context() as $acct => $url ) {
			$mentions .= sprintf(
				'<a rel="mention" class="u-url mention" href="%1$s" title="%2$s">%3$s</a> ',
				esc_url( $url ),
				esc_attr( $acct ),
				esc_html( '@' . strtok( $acct, '@' ) )
			);
		}
		$content = $mentions . $content;

		/**
		 * Filter the content of the comment.
		 *
		 * @param string      $content The content of the comment.
		 * @param \WP_Comment $comment The comment object.
		 * @param array       $args    The arguments.
		 *
		 * @return string The filtered content of the comment.
		 */
		$content = \apply_filters( 'comment_text', $content, $comment, array() );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );

		/**
		 * Filter the content of the comment.
		 *
		 * @param string      $content The content of the comment.
		 * @param \WP_Comment $comment The comment object.
		 *
		 * @return string The filtered content of the comment.
		 */
		return \apply_filters( 'activitypub_the_content', $content, $comment );
	}

	/**
	 * Returns the in-reply-to for the ActivityPub Item.
	 *
	 * @return false|string|null The URL of the in-reply-to.
	 */
	protected function get_in_reply_to() {
		$comment        = $this->item;
		$parent_comment = null;

		if ( $comment->comment_parent ) {
			$parent_comment = \get_comment( $comment->comment_parent );
		}

		if ( $parent_comment ) {
			$in_reply_to = Comment_Utils::get_source_id( $parent_comment->comment_ID );
			if ( ! $in_reply_to && ! empty( $parent_comment->user_id ) ) {
				$in_reply_to = Comment_Utils::generate_id( $parent_comment );
			}
		} else {
			$in_reply_to = \get_permalink( $comment->comment_post_ID );
		}

		return $in_reply_to;
	}

	/**
	 * Returns the ID of the ActivityPub Object.
	 *
	 * @see https://www.w3.org/TR/activitypub/#obj-id
	 * @see https://github.com/tootsuite/mastodon/issues/13879
	 *
	 * @return string ActivityPub URI for comment
	 */
	protected function get_id() {
		$comment = $this->item;
		return Comment_Utils::generate_id( $comment );
	}

	/**
	 * Returns the User-Object of the Author of the Post.
	 *
	 * If `single_user` mode is enabled, the Blog-User is returned.
	 *
	 * @return \Activitypub\Activity\Actor The User-Object.
	 */
	protected function get_actor_object() {
		if ( $this->actor_object ) {
			return $this->actor_object;
		}

		$blog_user          = new Blog();
		$this->actor_object = $blog_user;

		if ( is_single_user() ) {
			return $blog_user;
		}

		$user = Actors::get_by_id( $this->item->user_id );

		if ( $user && ! is_wp_error( $user ) ) {
			$this->actor_object = $user;
			return $user;
		}

		return $blog_user;
	}

	/**
	 * Helper function to get the @-Mentions from the comment content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentions() {
		\add_filter( 'activitypub_extract_mentions', array( $this, 'extract_reply_context' ) );

		/**
		 * Filter the mentions in the comment.
		 *
		 * @param array       $mentions The list of mentions.
		 * @param string      $content  The content of the comment.
		 * @param \WP_Comment $comment  The comment object.
		 *
		 * @return array The filtered list of mentions.
		 */
		return apply_filters( 'activitypub_extract_mentions', array(), $this->item->comment_content, $this->item );
	}

	/**
	 * Gets the ancestors of the comment, but only the ones that are ActivityPub comments.
	 *
	 * @return array The list of ancestors.
	 */
	protected function get_comment_ancestors() {
		$ancestors = get_comment_ancestors( $this->item );

		// Now that we have the full tree of ancestors, only return the ones received from the fediverse.
		return array_filter(
			$ancestors,
			function ( $comment_id ) {
				return \get_comment_meta( $comment_id, 'protocol', true ) === 'activitypub';
			}
		);
	}

	/**
	 * Collect all other Users that participated in this comment-thread
	 * to send them a notification about the new reply.
	 *
	 * @param array $mentions Optional. The already mentioned ActivityPub users. Default empty array.
	 *
	 * @return array The list of all Repliers.
	 */
	public function extract_reply_context( $mentions = array() ) {
		// Check if `$this->item` is a WP_Comment.
		if ( 'WP_Comment' !== get_class( $this->item ) ) {
			return $mentions;
		}

		$ancestors = $this->get_comment_ancestors();
		if ( ! $ancestors ) {
			return $mentions;
		}

		foreach ( $ancestors as $comment_id ) {
			$comment = \get_comment( $comment_id );
			if ( $comment && ! empty( $comment->comment_author_url ) ) {
				$acct = Webfinger::uri_to_acct( $comment->comment_author_url );
				if ( $acct && ! is_wp_error( $acct ) ) {
					$acct              = str_replace( 'acct:', '@', $acct );
					$mentions[ $acct ] = $comment->comment_author_url;
				}
			}
		}

		return $mentions;
	}

	/**
	 * Returns the updated date of the comment.
	 *
	 * @return string|null The updated date of the comment.
	 */
	public function get_updated() {
		$updated   = \get_comment_meta( $this->item->comment_ID, 'activitypub_comment_modified', true );
		$published = \get_comment_meta( $this->item->comment_ID, 'activitypub_comment_published', true );

		if ( $updated > $published ) {
			return \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, $updated );
		}

		return null;
	}

	/**
	 * Returns the published date of the comment.
	 *
	 * @return string The published date of the comment.
	 */
	public function get_published() {
		return \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, \strtotime( $this->item->comment_date_gmt ) );
	}

	/**
	 * Returns the URL of the comment.
	 *
	 * @return string The URL of the comment.
	 */
	public function get_url() {
		return $this->get_id();
	}

	/**
	 * Returns the type of the comment.
	 *
	 * @return string The type of the comment.
	 */
	public function get_type() {
		return 'Note';
	}

	/**
	 * Get the context of the post.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-context
	 *
	 * @return string The context of the post.
	 */
	protected function get_context() {
		if ( $this->item->comment_post_ID ) {
			return get_rest_url_by_path( sprintf( 'posts/%d/context', $this->item->comment_post_ID ) );
		}

		return null;
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
	 * Get the attachment for the comment.
	 *
	 * Extracts images from comment content and returns them as ActivityPub attachments.
	 *
	 * @return array The attachments array for ActivityPub.
	 */
	protected function get_attachment() {
		$max_media = \get_option( 'activitypub_max_image_attachments', \ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS );

		/**
		 * Filters the maximum number of media attachments allowed in a comment.
		 *
		 * @param int         $max_media Maximum number of media attachments.
		 * @param \WP_Comment $item      The comment object.
		 */
		$max_media = (int) \apply_filters( 'activitypub_max_image_attachments', $max_media, $this->item );

		if ( 0 === $max_media ) {
			return array();
		}

		$media = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		// Get comment content and parse for image embeds.
		$media = $this->parse_html_images( $media, $max_media, $this->item->comment_content );
		$media = $this->filter_unique_attachments( $media['image'] );
		$media = \array_slice( $media, 0, $max_media );

		/**
		 * Filter the attachment IDs for a comment.
		 *
		 * @param array       $media The media array.
		 * @param \WP_Comment $item  The comment object.
		 *
		 * @return array The filtered attachment IDs.
		 */
		$media = \apply_filters( 'activitypub_comment_attachment_ids', $media, $this->item );

		// Transform to ActivityStreams format using Base class method.
		$attachments = \array_filter( \array_map( array( $this, 'transform_attachment' ), $media ) );

		/**
		 * Filter the attachments for a comment.
		 *
		 * @param array       $attachments The attachments.
		 * @param \WP_Comment $item        The comment object.
		 *
		 * @return array The filtered attachments.
		 */
		return \apply_filters( 'activitypub_comment_attachments', $attachments, $this->item );
	}
}
