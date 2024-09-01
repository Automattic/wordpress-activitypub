<?php
namespace Activitypub\Collection;

use function Activitypub\is_local_comment;
use function Activitypub\get_rest_url_by_path;

use WP_Post;
use WP_Comment;
use WP_Error;


class Replies {
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
			return null;
		}
	}

    /**
	 * Get the replies Collection.
     *
     * @param WP_Post|WP_Comment $wp_object
	 * @param int $page
	 */
	public static function get_replies( $wp_object ) {
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

	public static function get_collection_page( $wp_object, $page, $part_of = null ) {
		$per_page = 10;
		$offset = intval( $page ) * $per_page;
		$args = array(
			'status' => 'approve',
			'orderby' => 'comment_date_gmt',
			'order'  => 'ASC',
		);

		if ( $wp_object instanceof WP_Post ) { // && $wp_object->comment_count  ) {
			$args['parent'] = 0;
			$args['post_id'] = $wp_object->ID;
		} elseif ( $wp_object instanceof WP_Comment ) {
			$args = array(
				'parent' => $wp_object->comment_ID,
			);
		} else {
			return new WP_Error();
		}

		$number_of_replies = get_comments(array_merge($args, array('count' => true ) ) );

		$args['number'] = $per_page;
		$args['offset'] = $offset;

		$comments = get_comments( $args );

		if ( ! isset( $part_of ) ) {
			$part_of = self::get_replies_id( $wp_object );
		}

		$id = add_query_arg( 'page', $page, $part_of );

		$comment_ids = array();
		// Only add external comments from the fedi	verse.
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

		$collection_page = array(
			'id'     => $id,
			'type'   => 'CollectionPage',
			'partOf' => $part_of,
			'items'  => $comment_ids,
		);

		if ( $number_of_replies / $per_page  > $page + 1 ) {
			$collection_page['next'] = add_query_arg( 'page', $page + 1 , $part_of );
		}

		return $collection_page;
	}
}