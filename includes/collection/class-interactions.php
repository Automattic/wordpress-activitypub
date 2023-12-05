<?php
namespace Activitypub\Collection;

use WP_Error;
use WP_Comment_Query;

use function Activitypub\url_to_commentid;
use function Activitypub\object_id_to_comment;
use function Activitypub\get_remote_metadata_by_actor;

/**
 * ActivityPub Interactions Collection
 */
class Interactions {
	/**
	 * Add a comment to a post
	 *
	 * @param array $activity The activity-object
	 *
	 * @return array|false The commentdata or false on failure
	 */
	public static function add_comment( $activity ) {
		if (
			! isset( $activity['object'] ) ||
			! isset( $activity['object']['id'] )
		) {
			return false;
		}

		if ( ! isset( $activity['object']['inReplyTo'] ) ) {
			return false;
		}

		$in_reply_to     = \esc_url_raw( $activity['object']['inReplyTo'] );
		$comment_post_id = \url_to_postid( $in_reply_to );
		$parent_comment  = object_id_to_comment( $in_reply_to );

		// save only replys and reactions
		if ( ! $comment_post_id && $parent_comment ) {
			$comment_post_id = $parent_comment->comment_post_ID;
		}

		// not a reply to a post or comment
		if ( ! $comment_post_id ) {
			return false;
		}

		$meta = get_remote_metadata_by_actor( $activity['actor'] );

		if ( ! $meta || \is_wp_error( $meta ) ) {
			return false;
		}

		$commentdata = array(
			'comment_post_ID' => $comment_post_id,
			'comment_author' => \esc_attr( $meta['name'] ),
			'comment_author_url' => \esc_url_raw( $meta['url'] ),
			'comment_content' => \addslashes( \wp_kses( $activity['object']['content'], 'pre_comment_content' ) ),
			'comment_type' => 'comment',
			'comment_author_email' => '',
			'comment_parent' => $parent_comment ? $parent_comment->comment_ID : 0,
			'comment_meta' => array(
				'source_id'  => \esc_url_raw( $activity['object']['id'] ),
				'source_url' => \esc_url_raw( $activity['object']['url'] ),
				'protocol'   => 'activitypub',
			),
		);

		if ( isset( $meta['icon']['url'] ) ) {
			$commentdata['comment_meta']['avatar_url'] = \esc_url_raw( $meta['icon']['url'] );
		}

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// do not require email for AP entries
		\add_filter( 'pre_option_require_name_email', '__return_false' );
		// No nonce possible for this submission route
		\add_filter(
			'akismet_comment_nonce',
			function() {
				return 'inactive';
			}
		);

		$comment = \wp_new_comment( $commentdata, true );

		\remove_filter( 'pre_option_require_name_email', '__return_false' );

		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		return $comment;
	}

	/**
	 * Update a comment
	 *
	 * @param array $activity The activity-object
	 *
	 * @return array|false The commentdata or false on failure
	 */
	public static function update_comment( $activity ) {
		$meta = get_remote_metadata_by_actor( $activity['actor'] );

		//Determine comment_ID
		$object_comment_id = url_to_commentid( \esc_url_raw( $activity['object']['id'] ) );

		if ( ! $object_comment_id ) {
			return false;
		}

		//found a local comment id
		$commentdata = \get_comment( $object_comment_id, ARRAY_A );
		$commentdata['comment_author'] = \esc_attr( $meta['name'] ? $meta['name'] : $meta['preferredUsername'] );
		$commentdata['comment_content'] = \addslashes( \wp_kses( $activity['object']['content'], 'pre_comment_content' ) );
		if ( isset( $meta['icon']['url'] ) ) {
			$commentdata['comment_meta']['avatar_url'] = \esc_url_raw( $meta['icon']['url'] );
		}

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// do not require email for AP entries
		\add_filter( 'pre_option_require_name_email', '__return_false' );
		// No nonce possible for this submission route
		\add_filter(
			'akismet_comment_nonce',
			function() {
				return 'inactive';
			}
		);

		$comment = \wp_update_comment( $commentdata, true );

		\remove_filter( 'pre_option_require_name_email', '__return_false' );

		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		return $comment;
	}

	/**
	 * Get interaction(s) for a given URL/ID.
	 *
	 * @param strin $url The URL/ID to get interactions for.
	 *
	 * @return array The interactions as WP_Comment objects.
	 */
	public static function get_interaction_by_id( $url ) {
		$args = array(
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

		// get URL, because $actor seems to be the ID
		if ( $meta && ! is_wp_error( $meta ) && isset( $meta['url'] ) ) {
			$actor = $meta['url'];
		}

		$args = array(
			'author_url' => $actor,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
					'compare' => '=',
				),
			),
		);
		$comment_query = new WP_Comment_Query( $args );
		return $comment_query->comments;
	}
}
