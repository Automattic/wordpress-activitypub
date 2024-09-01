<?php
namespace Activitypub\Collection;

use function Activitypub\is_local_comment;
use function Activitypub\get_rest_url_by_path;

use WP_Post;
use WP_Comment;
use WP_Error;


class Replies {
	/**
	 * Build base arguments for fetching the comments of either a WordPress post or comment.
	 *
	 * @param WP_Post|WP_Comment $wp_object
	 */
	private static function build_args( $wp_object ) {
		$args = array(
			'status' => 'approve',
			'orderby' => 'comment_date_gmt',
			'order'  => 'ASC',
		);

		if ( $wp_object instanceof WP_Post ) {
			$args['parent'] = 0; // TODO: maybe this is unnecessary.
			$args['post_id'] = $wp_object->ID;
		} elseif ( $wp_object instanceof WP_Comment ) {
			$args['parent'] = $wp_object->comment_ID;
		} else {
			return new WP_Error();
		}

		return $args;
	}

	/**
	 * Adds pagination args comments query.
	 *
	 * @param array $args              Query args built by self::build_args.
	 * @param int   $page              The current pagination page.
	 * @param int	$comments_per_page The number of comments per page.
	 */
	private static function add_pagination_args( $args, $page, $comments_per_page ) {
		$args['number'] = $comments_per_page;
		$args['offset'] = intval( $page ) * $comments_per_page;;

		return $args;
	}


	/**
	 * Get the replies collections ID.
     *
     * @param WP_Post|WP_Comment $wp_object
	 *
	 * @return string The rest URL of the replies collection.
	 */
	private static function get_replies_id( $wp_object ) {
		if ( $wp_object instanceof WP_Post ) {
			return get_rest_url_by_path( sprintf( 'posts/%d/replies', $wp_object->ID ) );
		} elseif ( $wp_object instanceof WP_Comment ) {
			return get_rest_url_by_path( sprintf( 'comments/%d/replies', $wp_object->comment_ID ) );
		} else {
			return new WP_Error();
		}
	}

    /**
	 * Get the replies collection.
     *
     * @param WP_Post|WP_Comment $wp_object
	 * @param int $page
	 *
	 * @return array An associative array containing the replies collection without JSON-LD context.
	 */
	public static function get_collection( $wp_object ) {
		$id = self::get_replies_id( $wp_object );

		if ( ! $id ) {
			return null;
		}

		$replies = array(
			'id'    => $id ,
			'type'  => 'Collection',
		);

		$replies['first'] = self::get_collection_page( $wp_object, 0, $replies['id'] );

		return $replies;
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
	private static function get_activitypub_comment_ids( $comments ) {
		$comment_ids = array();
		// Only add external comments from the fediverse.
		// Maybe use the Comment class more and the function is_local_comment etc.
		foreach ( $comments as $comment ) {
			if ( is_local_comment( $comment ) ) {
				continue;
			}
			$comment_meta = \get_comment_meta( $comment->comment_ID );
			if ( ! empty( $comment_meta['source_url'][0] ) ) {
				$comment_ids[] = $comment_meta['source_url'][0];
			} elseif ( ! empty( $comment_meta['source_id'][0] ) ) {
				$comment_ids[] = $comment_meta['source_id'][0];
			}
		}
		return $comment_ids;
	}

	/**
	 * Returns a replies collection page as an associative array.
	 *
	 * @link https://www.w3.org/TR/activitystreams-vocabulary/#dfn-collectionpage
	 *
	 * @param WP_Post|WP_Comment $wp_object The post of comment the replies are for.
	 * @param int                $page      The current pagination page.
	 * @param string             $part_of   The collection id/url the returned CollectionPage belongs to.
	 *
	 * @return array A CollectionPage as an associative array.
	 */
	public static function get_collection_page( $wp_object, $page, $part_of = null ) {
		// Build initial arguments for fetching approved comments.
		$args = self::build_args($wp_object);

		// Retrieve the partOf if not already given.
		$part_of = $part_of ?? self::get_replies_id( $wp_object );

		// If the collection page does not exist.
		if ( is_wp_error($args) || is_wp_error( $part_of ) ) {
			return null;
		}

		// Get to total replies count.
		$total_replies = get_comments( array_merge( $args, array( 'count' => true ) ) );

		// Modify query args to retrieve paginated results.
		$comments_per_page = get_option( 'comments_per_page');

		// Fetch internal and external comments for current page.
		$comments = get_comments( self::add_pagination_args( $args, $page, $comments_per_page ) );

		// Get the ActivityPub ID's of the comments, without out local-only comments.
		$comment_ids = self::get_activitypub_comment_ids( $comments );

		// Build the associative CollectionPage array.
		$collection_page = array(
			'id'     => \add_query_arg( 'page', $page, $part_of ),
			'type'   => 'CollectionPage',
			'partOf' => $part_of,
			'items'  => $comment_ids,
		);

		if ( $total_replies / $comments_per_page > $page + 1 ) {
			$collection_page['next'] = \add_query_arg( 'page', $page + 1 , $part_of );
		}

		return $collection_page;
	}
}