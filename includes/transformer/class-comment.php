<?php
namespace Activitypub\Transformer;

use WP_Comment;
use WP_Comment_Query;

use Activitypub\Model\Blog_User;
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

		$object->set_url( \get_comment_link( $comment->comment_ID ) );
		$object->set_type( 'Note' );

		$published = \strtotime( $comment->comment_date_gmt );
		$object->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', $published ) );

		$updated = \get_comment_meta( $comment->comment_ID, 'activitypub_last_modified', true );
		if ( $updated > $published ) {
			$object->set_updated( \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $updated ) ) );
		}

		$object->set_content_map(
			array(
				$this->get_locale() => $this->get_content(),
			)
		);
		$path = sprintf( 'users/%d/followers', intval( $comment->comment_author ) );

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
			$user = new Blog_User();
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

		$content = \wpautop( $content );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );
		$content = \apply_filters( 'the_content', $content, $comment );

		return $content;
	}

	/**
	 * Returns the in-reply-to for the ActivityPub Item.
	 *
	 * @return int The URL of the in-reply-to.
	 */
	protected function get_in_reply_to() {
		$comment = $this->wp_object;

		$parent_comment = \get_comment( $comment->comment_parent );
		$in_reply_to    = \get_permalink( $comment->comment_post_ID );

		if ( $parent_comment ) {
			$comment_meta = \get_comment_meta( $parent_comment->comment_ID );

			if ( ! empty( $comment_meta['source_id'][0] ) ) {
				$in_reply_to = $comment_meta['source_id'][0];
			} elseif ( ! empty( $comment_meta['source_url'][0] ) ) {
				$in_reply_to = $comment_meta['source_url'][0];
			} elseif ( ! empty( $parent_comment->user_id ) ) {
				$in_reply_to = $this->generate_id( $parent_comment );
			}
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
		return $this->generate_id( $comment );
	}

	/**
	 * Generates an ActivityPub URI for a comment
	 *
	 * @param WP_Comment|int $comment A comment object or comment ID
	 *
	 * @return string ActivityPub URI for comment
	 */
	protected function generate_id( $comment ) {
		$comment = get_comment( $comment );

		return \add_query_arg(
			array(
				'c' => $comment->comment_ID,
			),
			\trailingslashit( site_url() )
		);
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
			foreach ( $mentions as $mention => $url ) {
				$cc[] = $url;
			}
		}

		$comment_query = new WP_Comment_Query(
			array(
				'post_id'    => $this->wp_object->comment_post_ID,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					array(
						'key'     => 'source_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( $comment_query->comments ) {
			foreach ( $comment_query->comments as $comment ) {
				if ( empty( $comment->comment_author_url ) ) {
					continue;
				}
				$cc[] = \esc_url( $comment->comment_author_url );
			}
		}

		$cc = \array_unique( $cc );

		return $cc;
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
		return apply_filters( 'activitypub_extract_mentions', array(), $this->wp_object->comment_content, $this->wp_object );
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
