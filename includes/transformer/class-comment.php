<?php
namespace Activitypub\Transformer;

use WP_Comment;
use Activitypub\Hashtag;
use Activitypub\Model\Blog_User;
use Activitypub\Collection\Users;
use Activitypub\Transformer\Base;
use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;

use function Activitypub\esc_hashtag;
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
		return $this->object->user_id;
	}

	/**
	 * Change the User-ID of the WordPress Comment.
	 *
	 * @return int The User-ID of the WordPress Comment
	 */
	public function change_wp_user_id( $user_id ) {
		$this->object->user_id = $user_id;
	}

	/**
	 * Transforms the WP_Comment object to an ActivityPub Object
	 *
	 * @see \Activitypub\Activity\Base_Object
	 *
	 * @return \Activitypub\Activity\Base_Object The ActivityPub Object
	 */
	public function to_object() {
		$comment = $this->object;
		$object = new Base_Object();

		$object->set_id( $this->get_id() );
		$object->set_url( \get_comment_link( $comment->ID ) );
		$object->set_type( 'Note' );

		$published = \strtotime( $comment->comment_date_gmt );
		$object->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', $published ) );

		$updated = \get_comment_meta( $comment->comment_ID, 'activitypub_last_modified', true );
		if ( $updated > $published ) {
			$object->set_updated( \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $updated ) ) );
		}

		$object->set_attributed_to( $this->get_attributed_to() );
		$object->set_in_reply_to( $this->get_in_reply_to() );
		$object->set_content( $this->get_content() );
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
		$object->set_cc( $this->get_cc() );
		$object->set_tag( $this->get_tags() );

		return $object;
	}

	/**
	 * Transforms the ActivityPub Object to an Activity
	 *
	 * @param string $type The Activity-Type.
	 *
	 * @return \Activitypub\Activity\Activity The Activity.
	 */
	public function to_activity( $type ) {
		$object = $this->to_object();

		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_object( $object );

		return $activity;
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

		return Users::get_by_id( $this->object->user_id )->get_url();
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
		$comment = $this->object;
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
		$comment = $this->object;

		$parent_comment = \get_comment( $comment->comment_parent );

		if ( $parent_comment ) {
			$comment_meta = \get_comment_meta( $parent_comment->comment_ID );

			if ( ! empty( $comment_meta['source_id'][0] ) ) {
				$in_reply_to = $comment_meta['source_id'][0];
			} elseif ( ! empty( $comment_meta['source_url'][0] ) ) {
				$in_reply_to = $comment_meta['source_url'][0];
			} else {
				$in_reply_to = $this->generate_id( $parent_comment );
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
		$comment = $this->object;
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

		return $cc;
	}

	/**
	 * Returns a list of Tags, used in the Comment.
	 *
	 * This includes Hash-Tags and Mentions.
	 *
	 * @return array The list of Tags.
	 */
	protected function get_tags() {
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

		return $tags;
	}

	/**
	 * Helper function to get the @-Mentions from the comment content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentions() {
		return apply_filters( 'activitypub_extract_mentions', array(), $this->object->comment_content, $this->object );
	}

	/**
	 * Returns the locale of the post.
	 *
	 * @return string The locale of the post.
	 */
	public function get_locale() {
		$comment_id = $this->object->ID;
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
		return apply_filters( 'activitypub_comment_locale', $lang, $comment_id, $this->object );
	}
}
