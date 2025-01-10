<?php
/**
 * Interactions collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use WP_Comment_Query;
use Activitypub\Comment;

use function Activitypub\object_to_uri;
use function Activitypub\url_to_commentid;
use function Activitypub\object_id_to_comment;
use function Activitypub\get_remote_metadata_by_actor;

/**
 * ActivityPub Interactions Collection.
 */
class Interactions {
	const INSERT = 'insert';
	const UPDATE = 'update';

	/**
	 * Add a comment to a post.
	 *
	 * @param array $activity The activity-object.
	 *
	 * @return array|false The comment data or false on failure.
	 */
	public static function add_comment( $activity ) {
		$commentdata = self::activity_to_comment( $activity );

		if ( ! $commentdata || ! isset( $activity['object']['inReplyTo'] ) ) {
			return false;
		}

		$in_reply_to       = object_to_uri( $activity['object']['inReplyTo'] );
		$in_reply_to       = \esc_url_raw( $in_reply_to );
		$comment_post_id   = \url_to_postid( $in_reply_to );
		$parent_comment_id = url_to_commentid( $in_reply_to );

		// Save only replies and reactions.
		if ( ! $comment_post_id && $parent_comment_id ) {
			$parent_comment  = get_comment( $parent_comment_id );
			$comment_post_id = $parent_comment->comment_post_ID;
		}

		// Not a reply to a post or comment.
		if ( ! $comment_post_id ) {
			return false;
		}

		$commentdata['comment_post_ID'] = $comment_post_id;
		$commentdata['comment_parent']  = $parent_comment_id ? $parent_comment_id : 0;

		return self::persist( $commentdata, self::INSERT );
	}

	/**
	 * Update a comment.
	 *
	 * @param array $activity The activity object.
	 *
	 * @return array|string|int|\WP_Error|false The comment data or false on failure.
	 */
	public static function update_comment( $activity ) {
		$meta = get_remote_metadata_by_actor( $activity['actor'] );

		// Determine comment_ID.
		$comment     = object_id_to_comment( \esc_url_raw( $activity['object']['id'] ) );
		$commentdata = \get_comment( $comment, ARRAY_A );

		if ( ! $commentdata ) {
			return false;
		}

		// Found a local comment id.
		$commentdata['comment_author']  = \esc_attr( $meta['name'] ? $meta['name'] : $meta['preferredUsername'] );
		$commentdata['comment_content'] = \addslashes( $activity['object']['content'] );

		return self::persist( $commentdata, self::UPDATE );
	}

	/**
	 * Adds an incoming Like, Announce, ... as a comment to a post.
	 *
	 * @param array $activity Activity array.
	 *
	 * @return array|false      Comment data or `false` on failure.
	 */
	public static function add_reaction( $activity ) {
		$commentdata = self::activity_to_comment( $activity );

		if ( ! $commentdata ) {
			return false;
		}

		$url               = object_to_uri( $activity['object'] );
		$comment_post_id   = \url_to_postid( $url );
		$parent_comment_id = url_to_commentid( $url );

		if ( ! $comment_post_id && $parent_comment_id ) {
			$parent_comment  = \get_comment( $parent_comment_id );
			$comment_post_id = $parent_comment->comment_post_ID;
		}

		if ( ! $comment_post_id ) {
			// Not a reply to a post or comment.
			return false;
		}

		$comment_type = Comment::get_comment_type_by_activity_type( $activity['type'] );

		if ( ! $comment_type ) {
			// Not a valid comment type.
			return false;
		}

		$comment_content = $comment_type['excerpt'];

		$commentdata['comment_post_ID']           = $comment_post_id;
		$commentdata['comment_content']           = \esc_html( $comment_content );
		$commentdata['comment_type']              = \esc_attr( $comment_type['type'] );
		$commentdata['comment_meta']['source_id'] = \esc_url_raw( $activity['id'] );

		return self::persist( $commentdata, self::INSERT );
	}

	/**
	 * Get interaction(s) for a given URL/ID.
	 *
	 * @param string $url The URL/ID to get interactions for.
	 *
	 * @return array The interactions as WP_Comment objects.
	 */
	public static function get_interaction_by_id( $url ) {
		$args = array(
			'nopaging'   => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
				),
				array(
					'relation' => 'OR',
					array(
						'key'   => 'source_url',
						'value' => $url,
					),
					array(
						'key'   => 'source_id',
						'value' => $url,
					),
				),
			),
		);

		$query = new WP_Comment_Query( $args );
		return $query->comments;
	}

	/**
	 * Get interaction(s) for a given actor.
	 *
	 * @param string $actor The Actor-URL.
	 *
	 * @return array The interactions as WP_Comment objects.
	 */
	public static function get_interactions_by_actor( $actor ) {
		$meta = get_remote_metadata_by_actor( $actor );

		// Get URL, because $actor seems to be the ID.
		if ( $meta && ! is_wp_error( $meta ) && isset( $meta['url'] ) ) {
			$actor = object_to_uri( $meta['url'] );
		}

		$args          = array(
			'nopaging'   => true,
			'author_url' => $actor,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				array(
					'key'     => 'protocol',
					'value'   => 'activitypub',
					'compare' => '=',
				),
			),
		);
		$comment_query = new WP_Comment_Query( $args );
		return $comment_query->comments;
	}

	/**
	 * Adds line breaks to the list of allowed comment tags.
	 *
	 * @param  array  $allowed_tags Allowed HTML tags.
	 * @param  string $context      Optional. Context. Default empty.
	 *
	 * @return array Filtered tag list.
	 */
	public static function allowed_comment_html( $allowed_tags, $context = '' ) {
		if ( 'pre_comment_content' !== $context ) {
			// Do nothing.
			return $allowed_tags;
		}

		// Add `p` and `br` to the list of allowed tags.
		if ( ! array_key_exists( 'br', $allowed_tags ) ) {
			$allowed_tags['br'] = array();
		}

		if ( ! array_key_exists( 'p', $allowed_tags ) ) {
			$allowed_tags['p'] = array();
		}

		return $allowed_tags;
	}

	/**
	 * Convert an Activity to a WP_Comment
	 *
	 * @param array $activity The Activity array.
	 *
	 * @return array|false The comment data or false on failure.
	 */
	public static function activity_to_comment( $activity ) {
		$comment_content = null;
		$actor           = object_to_uri( $activity['actor'] );
		$actor           = get_remote_metadata_by_actor( $actor );

		// Check Actor-Meta.
		if ( ! $actor || is_wp_error( $actor ) ) {
			return false;
		}

		// Check Actor-Name.
		if ( isset( $actor['name'] ) ) {
			$comment_author = $actor['name'];
		} elseif ( isset( $actor['preferredUsername'] ) ) {
			$comment_author = $actor['preferredUsername'];
		} else {
			return false;
		}

		$url = object_to_uri( $actor['url'] ?? $actor['id'] );

		if ( ! $url ) {
			$url = object_to_uri( $actor['id'] );
		}

		if ( isset( $activity['object']['content'] ) ) {
			$comment_content = \addslashes( $activity['object']['content'] );
		}

		$commentdata = array(
			'comment_author'       => \esc_attr( $comment_author ),
			'comment_author_url'   => \esc_url_raw( $url ),
			'comment_content'      => $comment_content,
			'comment_type'         => 'comment',
			'comment_author_email' => '',
			'comment_meta'         => array(
				'source_id' => \esc_url_raw( object_to_uri( $activity['object'] ) ),
				'protocol'  => 'activitypub',
			),
		);

		if ( isset( $actor['icon']['url'] ) ) {
			$commentdata['comment_meta']['avatar_url'] = \esc_url_raw( $actor['icon']['url'] );
		}

		if ( isset( $activity['object']['url'] ) ) {
			$commentdata['comment_meta']['source_url'] = \esc_url_raw( object_to_uri( $activity['object']['url'] ) );
		}

		return $commentdata;
	}

	/**
	 * Persist a comment.
	 *
	 * @param array  $commentdata The commentdata array.
	 * @param string $action      Optional. Either 'insert' or 'update'. Default 'insert'.
	 *
	 * @return array|string|int|\WP_Error|false The comment data or false on failure
	 */
	public static function persist( $commentdata, $action = self::INSERT ) {
		// Disable flood control.
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );
		// Do not require email for AP entries.
		\add_filter( 'pre_option_require_name_email', '__return_false' );
		// No nonce possible for this submission route.
		\add_filter(
			'akismet_comment_nonce',
			function () {
				return 'inactive';
			}
		);
		\add_filter( 'wp_kses_allowed_html', array( self::class, 'allowed_comment_html' ), 10, 2 );

		if ( self::INSERT === $action ) {
			$state = \wp_new_comment( $commentdata, true );
		} else {
			$state = \wp_update_comment( $commentdata, true );
		}

		\remove_filter( 'wp_kses_allowed_html', array( self::class, 'allowed_comment_html' ), 10 );
		\remove_filter( 'pre_option_require_name_email', '__return_false' );
		// Restore flood control.
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		if ( 1 === $state ) {
			return $commentdata;
		} else {
			return $state; // Either WP_Comment, false, a WP_Error, 0, or 1!
		}
	}
}
