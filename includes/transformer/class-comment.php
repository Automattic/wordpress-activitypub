<?php
/**
 * WordPress Comment Transformer file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Webfinger;
use Activitypub\Comment as Comment_Utils;
use Activitypub\Model\Blog;
use Activitypub\Collection\Actors;

use function Activitypub\is_single_user;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\was_comment_received;
use function Activitypub\get_comment_ancestors;

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
	 * Returns the User-ID of the WordPress Comment.
	 *
	 * @return int The User-ID of the WordPress Comment
	 */
	public function get_wp_user_id() {
		return $this->wp_object->user_id;
	}

	/**
	 * Change the User-ID of the WordPress Comment.
	 *
	 * @param int $user_id The new user ID.
	 */
	public function change_wp_user_id( $user_id ) {
		$this->wp_object->user_id = $user_id;
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
		if ( was_comment_received( $this->wp_object ) ) {
			return $this->wp_object->comment_author_url;
		}

		if ( is_single_user() ) {
			$user = new Blog();
			return $user->get_id();
		}

		return Actors::get_by_id( $this->wp_object->user_id )->get_id();
	}

	/**
	 * Returns the content for the ActivityPub Item.
	 *
	 * The content will be generated based on the user settings.
	 *
	 * @return string The content.
	 */
	protected function get_content() {
		$comment  = $this->wp_object;
		$content  = $comment->comment_content;
		$mentions = '';

		foreach ( $this->extract_reply_context() as $acct => $url ) {
			$mentions .= sprintf(
				'<a rel="mention" class="u-url mention" href="%s">%s</a> ',
				esc_url( $url ),
				esc_html( $acct )
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
		$comment        = $this->wp_object;
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
		$comment = $this->wp_object;
		return Comment_Utils::generate_id( $comment );
	}

	/**
	 * Returns a list of Mentions, used in the Comment.
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

		return array_unique( $cc );
	}

	/**
	 * Returns a list of Tags, used in the Comment.
	 *
	 * This includes Hash-Tags and Mentions.
	 *
	 * @return array The list of Tags.
	 */
	protected function get_tag() {
		$tags = array();

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

		return \array_unique( $tags, SORT_REGULAR );
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
		return apply_filters( 'activitypub_extract_mentions', array(), $this->wp_object->comment_content, $this->wp_object );
	}

	/**
	 * Gets the ancestors of the comment, but only the ones that are ActivityPub comments.
	 *
	 * @return array The list of ancestors.
	 */
	protected function get_comment_ancestors() {
		$ancestors = get_comment_ancestors( $this->wp_object );

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
		// Check if `$this->wp_object` is a WP_Comment.
		if ( 'WP_Comment' !== get_class( $this->wp_object ) ) {
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
	 * Returns the locale of the post.
	 *
	 * @return string The locale of the post.
	 */
	public function get_locale() {
		$comment_id = $this->wp_object->ID;
		$lang       = \strtolower( \strtok( \get_locale(), '_-' ) );

		/**
		 * Filter the locale of the comment.
		 *
		 * @param string   $lang    The locale of the comment.
		 * @param int      $comment_id The comment ID.
		 * @param \WP_Post $post    The comment object.
		 *
		 * @return string The filtered locale of the comment.
		 */
		return apply_filters( 'activitypub_comment_locale', $lang, $comment_id, $this->wp_object );
	}

	/**
	 * Returns the updated date of the comment.
	 *
	 * @return string|null The updated date of the comment.
	 */
	public function get_updated() {
		$updated   = \get_comment_meta( $this->wp_object->comment_ID, 'activitypub_comment_modified', true );
		$published = \get_comment_meta( $this->wp_object->comment_ID, 'activitypub_comment_published', true );

		if ( $updated > $published ) {
			return \gmdate( 'Y-m-d\TH:i:s\Z', $updated );
		}

		return null;
	}

	/**
	 * Returns the published date of the comment.
	 *
	 * @return string The published date of the comment.
	 */
	public function get_published() {
		return \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $this->wp_object->comment_date_gmt ) );
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
	 * Returns the to of the comment.
	 *
	 * @return array The to of the comment.
	 */
	public function get_to() {
		$path = sprintf( 'actors/%d/followers', intval( $this->wp_object->comment_author ) );

		return array(
			'https://www.w3.org/ns/activitystreams#Public',
			get_rest_url_by_path( $path ),
		);
	}

	/**
	 * Returns the content map for the comment.
	 *
	 * @return array The content map for the comment.
	 */
	public function get_content_map() {
		return array(
			$this->get_locale() => $this->get_content(),
		);
	}
}
