<?php
namespace Activitypub\Transformer;

use WP_Comment;
use Activitypub\Collection\Users;
use Activitypub\Model\Blog_User;
use Activitypub\Activity\Base_Object;
use Activitypub\Hashtag;

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
class Comment {

	/**
	 * The WP_Comment object.
	 *
	 * @var WP_Comment
	 */
	protected $wp_comment;

	/**
	 * Static function to Transform a WP_Comment Object.
	 *
	 * This helps to chain the output of the Transformer.
	 *
	 * @param WP_Comment $wp_comment The WP_Comment object
	 *
	 * @return void
	 */
	public static function transform( WP_Comment $wp_comment ) {
		return new static( $wp_comment );
	}

	/**
	 *
	 *
	 * @param WP_Comment $wp_comment
	 */
	public function __construct( WP_Comment $wp_comment ) {
		$this->wp_comment = $wp_comment;
	}

	/**
	 * Transforms the WP_Comment object to an ActivityPub Object
	 *
	 * @see \Activitypub\Activity\Base_Object
	 *
	 * @return \Activitypub\Activity\Base_Object The ActivityPub Object
	 */
	public function to_object() {
		$comment = $this->wp_comment;
		$object = new Base_Object();

		$object->set_id( $this->get_id( $comment ) );
		$object->set_url( \get_comment_link( $comment->ID ) );
		$object->set_context( $this->get_context() );
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
				\strstr( \get_locale(), '_', true ) => $this->get_content(),
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

		return Users::get_by_id( $this->wp_comment->user_id )->get_url();
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
		$comment = $this->wp_comment;
		$content = $comment->comment_content;

		$content = \wpautop( $content );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );
		$content = \apply_filters( 'the_content', $content, $comment );

		return $content;
	}

	/**
	 * get_parent_uri (in reply to)
	 * takes a comment and returns
	 * @param WP_Comment activitypub object id URI
	 * @return int comment_id
	 */
	protected function get_in_reply_to() {
		$comment = $this->wp_comment;

		$parent_comment = \get_comment( $comment->comment_parent );
		if ( $parent_comment ) {
			//is parent remote?
			$in_reply_to = $this->get_source_id( $parent_comment );
			if ( ! $in_reply_to ) {
				//local
				$in_reply_to = $this->get_id( $parent_comment );
			}
		} else {
			$pretty_permalink = \get_post_meta( $comment->comment_post_ID, 'activitypub_canonical_url', true );
			if ( $pretty_permalink ) {
				$in_reply_to = $pretty_permalink;
			} else {
				$in_reply_to = \get_permalink( $comment->comment_post_ID );
			}
		}
		return $in_reply_to;
	}

	/**
	 * @param $comment or $comment_id
	 * @return ActivityPub URI of comment
	 *
	 * AP Object ID must be unique
	 *
	 * https://www.w3.org/TR/activitypub/#obj-id
	 * https://github.com/tootsuite/mastodon/issues/13879
	 */
	protected function get_id( $comment ) {

		$comment = \get_comment( $comment );
		$ap_comment_id = \add_query_arg(
			array(
				'p' => $comment->comment_post_ID,
				'replytocom' => $comment->comment_ID,
			),
			\trailingslashit( site_url() )
		);
		return $ap_comment_id;
	}

	/**
	 * Checks a comment ID for a source_id, or source_url
	 */
	protected function get_source_id( $comment ) {
		if (  $comment->user_id ) {
			return null;
		}

		$source_id = \get_comment_meta( $comment->comment_ID, 'source_id', true );
		if ( ! $source_id ) {
			$source_url = \get_comment_meta( $comment->comment_ID, 'source_url', true );
			if ( ! $source_url ) {
				return null;
			}
			$response = \safe_remote_get( $source_url );
			$body = \wp_remote_retrieve_body( $response );
			$remote_status = \json_decode( $body, true );
			if ( \is_wp_error( $remote_status )
				|| ! isset( $remote_status['@context'] )
				|| ! isset( $remote_status['object']['id'] ) ) {
					// the original post may have been deleted, before we started processing deletes.
				return null;
			}
			$source_id = $remote_status['object']['id'];
		}
		return $source_id;
	}

	protected function get_context() {
		$comment = $this->wp_comment;
		$pretty_permalink = \get_post_meta( $comment->comment_post_ID, 'activitypub_canonical_url', true );
		if ( $pretty_permalink ) {
			$context = $pretty_permalink;
		} else {
			$context = \get_permalink( $comment->comment_post_ID );
		}
		return $context;
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
		return apply_filters( 'activitypub_extract_mentions', array(), $this->wp_comment->comment_content, $this->wp_comment );
	}

}
