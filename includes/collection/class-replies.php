<?php
/**
 * Replies collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use WP_Post;
use WP_Comment;
use WP_Error;

use Activitypub\Comment;

use function Activitypub\is_local_comment;
use function Activitypub\get_rest_url_by_path;

/**
 * Class containing code for getting replies Collections and CollectionPages of posts and comments.
 */
class Replies {
	/**
	 * Build base arguments for fetching the comments of either a WordPress post or comment.
	 *
	 * @param WP_Post|WP_Comment|WP_Error $wp_object The post or comment to fetch replies for on success.
	 */
	private static function build_args( $wp_object ) {
		$args = array(
			'status'  => 'approve',
			'orderby' => 'comment_date_gmt',
			'order'   => 'ASC',
			'type'    => 'comment',
		);

		if ( $wp_object instanceof WP_Post ) {
			$args['parent']  = 0; // TODO: maybe this is unnecessary.
			$args['post_id'] = $wp_object->ID;
		} elseif ( $wp_object instanceof WP_Comment ) {
			$args['parent'] = $wp_object->comment_ID;
		} else {
			return new WP_Error();
		}

		return $args;
	}

	/**
	 * Get the replies collections ID.
	 *
	 * @param WP_Post|WP_Comment $wp_object The post or comment to fetch replies for.
	 *
	 * @return string|WP_Error The rest URL of the replies collection or WP_Error if the object is not a post or comment.
	 */
	private static function get_id( $wp_object ) {
		if ( $wp_object instanceof WP_Post ) {
			return get_rest_url_by_path( sprintf( 'posts/%d/replies', $wp_object->ID ) );
		} elseif ( $wp_object instanceof WP_Comment ) {
			return get_rest_url_by_path( sprintf( 'comments/%d/replies', $wp_object->comment_ID ) );
		} else {
			return new WP_Error( 'unsupported_object', 'The object is not a post or comment.' );
		}
	}

	/**
	 * Get the Replies collection.
	 *
	 * @param WP_Post|WP_Comment $wp_object The post or comment to fetch replies for.
	 *
	 * @return array|\WP_Error|null An associative array containing the replies collection without JSON-LD context on success.
	 */
	public static function get_collection( $wp_object ) {
		$id = self::get_id( $wp_object );

		if ( is_wp_error( $id ) ) {
			return defined( 'REST_REQUEST' ) && REST_REQUEST ? $id : null;
		}

		$replies = array(
			'id'   => $id,
			'type' => 'Collection',
		);

		$replies['first'] = self::get_collection_page( $wp_object, 1, $replies['id'] );

		return $replies;
	}

	/**
	 * Returns a replies collection page as an associative array.
	 *
	 * @link https://www.w3.org/TR/activitystreams-vocabulary/#dfn-collectionpage
	 *
	 * @param WP_Post|WP_Comment $wp_object The post of comment the replies are for.
	 * @param int                $page      The current pagination page.
	 * @param string             $part_of   Optional. The collection id/url the returned CollectionPage belongs to. Default null.
	 *
	 * @return array|WP_Error|null A CollectionPage as an associative array on success, WP_Error or null on failure.
	 */
	public static function get_collection_page( $wp_object, $page, $part_of = null ) {
		// Build initial arguments for fetching approved comments.
		$args = self::build_args( $wp_object );
		if ( is_wp_error( $args ) ) {
			return defined( 'REST_REQUEST' ) && REST_REQUEST ? $args : null;
		}

		// Retrieve the partOf if not already given.
		$part_of = $part_of ?? self::get_id( $wp_object );

		// If the collection page does not exist.
		if ( is_wp_error( $part_of ) ) {
			return defined( 'REST_REQUEST' ) && REST_REQUEST ? $part_of : null;
		}

		// Get to total replies count.
		$total_replies = \get_comments( array_merge( $args, array( 'count' => true ) ) );

		// If set to zero, we get errors below. You need at least one comment per page, here.
		$args['number'] = max( (int) \get_option( 'comments_per_page' ), 1 );
		$args['offset'] = intval( $page - 1 ) * $args['number'];

		// Get the ActivityPub ID's of the comments, without local-only comments.
		$comment_ids = self::get_reply_ids( \get_comments( $args ) );

		// Build the associative CollectionPage array.
		$collection_page = array(
			'id'     => \add_query_arg( 'page', $page, $part_of ),
			'type'   => 'CollectionPage',
			'partOf' => $part_of,
			'items'  => $comment_ids,
		);

		if ( ( $total_replies / $args['number'] ) > $page ) {
			$collection_page['next'] = \add_query_arg( 'page', $page + 1, $part_of );
		}

		if ( $page > 1 ) {
			$collection_page['prev'] = \add_query_arg( 'page', $page - 1, $part_of );
		}

		return $collection_page;
	}

	/**
	 * Get the ActivityPub ID's from a list of comments.
	 *
	 * It takes only federated/non-local comments into account, others also do not have an
	 * ActivityPub ID available.
	 *
	 * @param WP_Comment[] $comments The comments to retrieve the ActivityPub ids from.
	 *
	 * @return string[] A list of the ActivityPub ID's.
	 */
	private static function get_reply_ids( $comments ) {
		$comment_ids = array();
		// Only add external comments from the fediverse.
		// Maybe use the Comment class more and the function is_local_comment etc.
		foreach ( $comments as $comment ) {
			if ( is_local_comment( $comment ) ) {
				continue;
			}

			$public_comment_id = Comment::get_source_id( $comment->comment_ID );
			if ( $public_comment_id ) {
				$comment_ids[] = $public_comment_id;
			}
		}

		return $comment_ids;
	}
}
