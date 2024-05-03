<?php
namespace Activitypub\Transformer;

use WP_Comment;
use WP_Comment_Query;

use Activitypub\Webfinger;
use Activitypub\Comment as Comment_Utils;
use Activitypub\Model\Blog;
use Activitypub\Collection\Users;
use Activitypub\Transformer\Base;

use function Activitypub\is_single_user;
use function Activitypub\get_rest_url_by_path;

/**
 * WordPress Comment Transformer
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
	 * @return int The User-ID of the WordPress Comment
	 */
	public function change_wp_user_id( $user_id ) {
		$this->wp_object->user_id = $user_id;
	}

	/**
	 * Transforms the WP_Comment object to an ActivityPub Object
	 *
	 * @see \Activitypub\Activity\Base_Object
	 *
	 * @return \Activitypub\Activity\Base_Object The ActivityPub Object
	 */
	public function to_object() {
		$comment = $this->wp_object;
		$object  = parent::to_object();

		$object->set_url( $this->get_id() );
		$object->set_type( 'Note' );

		$published = \strtotime( $comment->comment_date_gmt );
		$object->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', $published ) );

		$updated = \get_comment_meta( $comment->comment_ID, 'activitypub_comment_modified', true );
		if ( $updated > $published ) {
			$object->set_updated( \gmdate( 'Y-m-d\TH:i:s\Z', $updated ) );
		}

		$object->set_content_map(
			array(
				$this->get_locale() => $this->get_content(),
			)
		);
		$path = sprintf( 'actors/%d/followers', intval( $comment->comment_author ) );

		$object->set_to(
			array(
				'https://www.w3.org/ns/activitystreams#Public',
				get_rest_url_by_path( $path ),
			)
		);

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
			$user = new Blog();
			return $user->get_url();
		}

		return Users::get_by_id( $this->wp_object->user_id )->get_url();
	}

	/**
	 * Returns the content for the ActivityPub Item.
	 *
	 * The content will be generated based on the user settings.
	 *
	 * @return string The content.
	 */
	protected function get_content() {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$comment = $this->wp_object;
		$content = $comment->comment_content;

		$content = \apply_filters( 'comment_text', $content, $comment, array() );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );
		$content = \apply_filters( 'activitypub_the_content', $content, $comment );

		return $content;
	}

	/**
	 * Returns the in-reply-to for the ActivityPub Item.
	 *
	 * @return int The URL of the in-reply-to.
	 */
	protected function get_in_reply_to() {
		$comment = $this->wp_object;

		$parent_comment = null;
		$in_reply_to    = null;

		if ( $comment->comment_parent ) {
			$parent_comment = \get_comment( $comment->comment_parent );
		}

		if ( $parent_comment ) {
			$comment_meta = \get_comment_meta( $parent_comment->comment_ID );

			if ( ! empty( $comment_meta['source_id'][0] ) ) {
				$in_reply_to = $comment_meta['source_id'][0];
			} elseif ( ! empty( $comment_meta['source_url'][0] ) ) {
				$in_reply_to = $comment_meta['source_url'][0];
			} elseif ( ! empty( $parent_comment->user_id ) ) {
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
				$tag = array(
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

		return apply_filters( 'activitypub_extract_mentions', array(), $this->wp_object->comment_content, $this->wp_object );
	}

	/**
	 * Collect all other Users that participated in this comment-thread
	 * to send them a notification about the new reply.
	 *
	 * @param array $mentions The already mentioned ActivityPub users
	 *
	 * @return array The list of all Repliers.
	 */
	public function extract_reply_context( $mentions ) {
		// Check if `$this->wp_object` is a WP_Comment
		if ( 'WP_Comment' !== get_class( $this->wp_object ) ) {
			return $mentions;
		}

		$comment_query = new WP_Comment_Query(
			array(
				'post_id'    => $this->wp_object->comment_post_ID,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					array(
						'key'   => 'protocol',
						'value' => 'activitypub',
					),
				),
			)
		);

		if ( $comment_query->comments ) {
			foreach ( $comment_query->comments as $comment ) {
				if ( ! empty( $comment->comment_author_url ) ) {
					$acct = Webfinger::uri_to_acct( $comment->comment_author_url );
					if ( $acct && ! is_wp_error( $acct ) ) {
						$acct = str_replace( 'acct:', '@', $acct );
						$mentions[ $acct ] = $comment->comment_author_url;
					}
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
		 * @param string  $lang    The locale of the comment.
		 * @param int     $comment_id The comment ID.
		 * @param WP_Post $post    The comment object.
		 *
		 * @return string The filtered locale of the comment.
		 */
		return apply_filters( 'activitypub_comment_locale', $lang, $comment_id, $this->wp_object );
	}
}
