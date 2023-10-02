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
use function Activitypub\set_ap_comment_id;
use function Activitypub\get_in_reply_to;

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
		$wp_comment = $this->wp_comment;
		$object = new Base_Object();

		$object->set_id( set_ap_comment_id( $wp_comment ) );
		$object->set_url( \get_comment_link( $wp_comment->ID ) );
		$object->set_context( \get_permalink( $wp_comment->comment_post_ID ) );
		$object->set_type( 'Note' );

		$published = \strtotime( $wp_comment->comment_date_gmt );
		$object->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', $published ) );

		$updated = \get_comment_meta( $wp_comment->comment_ID, 'ap_last_modified', true );
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
		$path = sprintf( 'users/%d/followers', intval( $wp_comment->comment_author ) );

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
		// TODO Delete Or Modify
		$tags = array();

		$comment_tags = self::get_hashtags();
		if ( $comment_tags ) {
			foreach ( $comment_tags as $comment_tag ) {
				$tag_link = \get_tag_link( $comment_tag );
				if ( ! $tag_link ) {
					continue;
				}
				$tag = array(
					'type' => 'Hashtag',
					'href' => \esc_url( $tag_link ),
					'name' => esc_hashtag( $comment_tag ),
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
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_comment = $this->wp_comment;
		$content    = $wp_comment->comment_content;

		$content    = \wpautop( $content );
		$content    = \preg_replace( '/[\n\r\t]/', '', $content );
		$content    = \trim( $content );

		$content    = \apply_filters( 'the_content', $content, $wp_comment );
		$content    = \html_entity_decode( $content, \ENT_QUOTES, 'UTF-8' );
		return $content;
	}

	/**
	 * Helper function to get the @-Mentions from the comment content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentions() {
		return apply_filters( 'activitypub_extract_mentions', array(), $this->wp_comment->comment_content, $this->wp_comment );
	}

	/**
	 * Helper function to get the #HashTags from the comment content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_hashtags() {
		$wp_comment = $this->wp_comment;
		$content    = $this->get_content();

		$tags = [];
		//TODO fix hashtag
		if ( \preg_match_all( '/' . ACTIVITYPUB_HASHTAGS_REGEXP . '/i', $content, $match ) ) {
			$tags = \implode( ', ', $match[1] );
		}
		\error_log( "get_hashtags: tags: " . \print_r( $tags, true ) );
		$hashtags = [];
		preg_match_all("/(#\w+)/u", $content, $matches);
		if ($matches) {
			$hashtagsArray = array_count_values($matches[0]);
			$hashtags = array_keys($hashtagsArray);
		}
		\error_log( "get_hashtags: hashtags: " . \print_r( $hashtags, true ) );
		return $hashtags;

	}

	/**
	 * Helper function to get the InReplyTo parent Comment URI.
	 *
	 * @return array The in_reply_to URI.
	 */
	protected function get_in_reply_to() {
		$wp_comment = $this->wp_comment;
		$in_reply_to = get_in_reply_to( $wp_comment );
		error_log( 'get_in_reply_to: ' . print_r( $in_reply_to, true ) );
		return $in_reply_to;
	}
}
