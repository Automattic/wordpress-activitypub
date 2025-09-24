<?php
/**
 * Interactions collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Comment;
use Activitypub\Webfinger;
use WP_Comment_Query;

use function Activitypub\get_remote_metadata_by_actor;
use function Activitypub\is_post_disabled;
use function Activitypub\object_id_to_comment;
use function Activitypub\object_to_uri;
use function Activitypub\url_to_commentid;

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
	 * @return int|false|\WP_Error The comment ID or false or WP_Error on failure.
	 */
	public static function add_comment( $activity ) {
		$comment_data = self::activity_to_comment( $activity );

		if ( ! $comment_data || ! isset( $activity['object']['inReplyTo'] ) ) {
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

		if ( is_post_disabled( $comment_post_id ) ) {
			return false;
		}

		$comment_data['comment_post_ID'] = $comment_post_id;
		$comment_data['comment_parent']  = $parent_comment_id ? $parent_comment_id : 0;

		return self::persist( $comment_data );
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
		$comment      = object_id_to_comment( \esc_url_raw( $activity['object']['id'] ) );
		$comment_data = \get_comment( $comment, ARRAY_A );

		if ( ! $comment_data ) {
			return false;
		}

		// Found a local comment id.
		$comment_data['comment_author']  = \esc_attr( $meta['name'] ?? $meta['preferredUsername'] );
		$comment_data['comment_content'] = \addslashes( $activity['object']['content'] );

		return self::persist( $comment_data, self::UPDATE );
	}

	/**
	 * Adds an incoming Like, Announce, ... as a comment to a post.
	 *
	 * @param array $activity Activity array.
	 *
	 * @return array|string|int|\WP_Error|false Comment data or `false` on failure.
	 */
	public static function add_reaction( $activity ) {
		$url               = object_to_uri( $activity['object'] );
		$comment_post_id   = \url_to_postid( $url );
		$parent_comment_id = url_to_commentid( $url );

		if ( ! $comment_post_id && $parent_comment_id ) {
			$parent_comment  = \get_comment( $parent_comment_id );
			$comment_post_id = $parent_comment->comment_post_ID;
		}

		if ( ! $comment_post_id || is_post_disabled( $comment_post_id ) ) {
			// Not a reply to a post or comment.
			return false;
		}

		$comment_type = Comment::get_comment_type_by_activity_type( $activity['type'] );
		if ( ! $comment_type ) {
			// Not a valid comment type.
			return false;
		}

		$comment_data = self::activity_to_comment( $activity );
		if ( ! $comment_data ) {
			return false;
		}

		$comment_data['comment_post_ID']           = $comment_post_id;
		$comment_data['comment_parent']            = $parent_comment_id ? $parent_comment_id : 0;
		$comment_data['comment_content']           = \esc_html( $comment_type['excerpt'] );
		$comment_data['comment_type']              = \esc_attr( $comment_type['type'] );
		$comment_data['comment_meta']['source_id'] = \esc_url_raw( $activity['id'] );

		return self::persist( $comment_data );
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

		$args = array(
			'nopaging'   => true,
			'author_url' => $actor,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
				),
			),
		);

		return get_comments( $args );
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
		$actor           = object_to_uri( $activity['actor'] ?? null );
		$actor           = get_remote_metadata_by_actor( $actor );

		// Check Actor-Meta.
		if ( ! $actor || is_wp_error( $actor ) ) {
			return false;
		}

		// Check Actor-Name.
		$comment_author = null;
		if ( ! empty( $actor['name'] ) ) {
			$comment_author = $actor['name'];
		} elseif ( ! empty( $actor['preferredUsername'] ) ) {
			$comment_author = $actor['preferredUsername'];
		}

		if ( empty( $comment_author ) && \get_option( 'require_name_email' ) ) {
			return false;
		}

		$url = object_to_uri( $actor['url'] ?? $actor['id'] );

		if ( isset( $activity['object']['content'] ) ) {
			$comment_content = \addslashes( $activity['object']['content'] );
		}

		$webfinger = Webfinger::uri_to_acct( $url );
		if ( is_wp_error( $webfinger ) ) {
			$webfinger = '';
		} else {
			$webfinger = str_replace( 'acct:', '', $webfinger );
		}

		$date = $activity['object']['published'] ?? 'now';

		$comment_data = array(
			'comment_author'       => $comment_author ?? __( 'Anonymous', 'activitypub' ),
			'comment_author_url'   => \esc_url_raw( $url ),
			'comment_content'      => $comment_content,
			'comment_type'         => 'comment',
			'comment_author_email' => $webfinger,
			'comment_date'         => \get_date_from_gmt( \gmdate( 'Y-m-d H:i:s', \strtotime( $date ) ) ),
			'comment_date_gmt'     => \gmdate( 'Y-m-d H:i:s', \strtotime( $date ) ),
			'comment_meta'         => array(
				'source_id' => \esc_url_raw( object_to_uri( $activity['object'] ) ),
				'protocol'  => 'activitypub',
			),
		);

		if ( isset( $actor['icon']['url'] ) ) {
			$comment_data['comment_meta']['avatar_url'] = \esc_url_raw( $actor['icon']['url'] );
		}

		if ( isset( $activity['object']['url'] ) ) {
			$comment_data['comment_meta']['source_url'] = \esc_url_raw( object_to_uri( $activity['object']['url'] ) );
		}

		return $comment_data;
	}

	/**
	 * Persist a comment.
	 *
	 * @param array  $comment_data The comment data array.
	 * @param string $action       Optional. Either 'insert' or 'update'. Default 'insert'.
	 *
	 * @return array|string|int|\WP_Error|false The comment data or false on failure
	 */
	public static function persist( $comment_data, $action = self::INSERT ) {
		// Disable flood control.
		\remove_action( 'check_comment_flood', 'check_comment_flood_db' );
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
			$state = \wp_new_comment( $comment_data, true );
		} else {
			$state = \wp_update_comment( $comment_data, true );
		}

		\remove_filter( 'wp_kses_allowed_html', array( self::class, 'allowed_comment_html' ) );
		\remove_filter( 'pre_option_require_name_email', '__return_false' );
		// Restore flood control.
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		if ( 1 === $state ) {
			return $comment_data;
		} else {
			return $state; // Either WP_Comment, false, a WP_Error, 0, or 1!
		}
	}

	/**
	 * Get the total number of interactions by type for a given ID.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $type    The type of interaction to count.
	 *
	 * @return int The total number of interactions.
	 */
	public static function count_by_type( $post_id, $type ) {
		return \get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'type'    => $type,
				'count'   => true,
				'paging'  => false,
				'fields'  => 'ids',
			)
		);
	}
}
