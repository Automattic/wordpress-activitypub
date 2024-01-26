<?php

namespace Activitypub;

use WP_Comment_Query;

use function Activitypub\is_user_disabled;

/**
 * ActivityPub Comment Class
 *
 * This class is a helper/utils class that provides a collection of static
 * methods that are used to handle comments.
 */
class Comment {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'comment_reply_link', array( self::class, 'comment_reply_link' ), 10, 3 );
		\add_filter( 'comment_class', array( self::class, 'comment_class' ), 10, 3 );
		\add_filter( 'get_comment_link', array( self::class, 'remote_comment_link' ), 11, 3 );
	}

	/**
	 * Filter the comment reply link.
	 *
	 * We don't want to show the comment reply link for federated comments
	 * if the user is disabled for federation.
	 *
	 * @param string     $link    The HTML markup for the comment reply link.
	 * @param array      $args    An array of arguments overriding the defaults.
	 * @param WP_Comment $comment The object of the comment being replied.
	 *
	 * @return string The filtered HTML markup for the comment reply link.
	 */
	public static function comment_reply_link( $link, $args, $comment ) {
		if ( ! self::is_federated( $comment ) ) {
			return $link;
		}

		$current_user      = get_current_user_id();
		$custom_reply_link = apply_filters( 'activitypub_comment_reply_link', '' );

		if ( ! $current_user ) {
			return $custom_reply_link;
		}

		$is_user_disabled = is_user_disabled( $current_user );

		if ( $is_user_disabled ) {
			return $custom_reply_link;
		}

		return $link;
	}

	/**
	 * Check if a comment is federatable.
	 *
	 * We consider a comment federatable if it is authored by a user that is not disabled for federation
	 * or if it was received via ActivityPub.
	 *
	 * Use this function to check if it is possible to federate a comment or parent comment or if it is
	 * already federated.
	 *
	 * Please consider that this function does not check the parent comment, so if you want to check if
	 * a comment is a reply to a federated comment, you should use should_be_federated().
	 *
	 * @param mixed $comment Comment object or ID.
	 *
	 * @return boolean True if the comment is federatable, false otherwise.
	 */
	public static function is_federatable( $comment ) {
		$comment = \get_comment( $comment );

		if ( ! $comment ) {
			return false;
		}

		if ( self::is_federated( $comment ) ) {
			return true;
		}

		$user_id = $comment->user_id;

		if ( ! $user_id ) {
			return false;
		}

		$is_user_disabled = is_user_disabled( $user_id );

		return ! $is_user_disabled;
	}

	/**
	 * Check if a comment is federated.
	 *
	 * We consider a comment federated if comment was received via ActivityPub.
	 *
	 * Use this function to check if it is comment that was received via ActivityPub.
	 *
	 * @param mixed $comment Comment object or ID.
	 *
	 * @return boolean True if the comment is federated, false otherwise.
	 */
	public static function is_federated( $comment ) {
		$comment = \get_comment( $comment );

		if ( ! $comment ) {
			return false;
		}

		$protocol = \get_comment_meta( $comment->comment_ID, 'protocol', true );

		if ( 'activitypub' === $protocol ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a comment should be federated.
	 *
	 * We consider a comment should be federated if it is authored by a user that is
	 * not disabled for federation and if it is a reply directly to the post or to a
	 * federated comment.
	 *
	 * Use this function to check if a comment should be federated.
	 *
	 * @param mixed $comment Comment object or ID.
	 *
	 * @return boolean True if the comment should be federated, false otherwise.
	 */
	public static function should_be_federated( $comment ) {
		// we should not federate federated comments
		if ( self::is_federated( $comment ) ) {
			return false;
		}

		$comment = \get_comment( $comment );
		$user_id = $comment->user_id;

		// comments without user can't be federated
		if ( ! $user_id ) {
			return false;
		}

		$is_user_disabled = is_user_disabled( $user_id );

		// user is disabled for federation
		if ( $is_user_disabled ) {
			return false;
		}

		// it is a comment to the post and can be federated
		if ( empty( $comment->comment_parent ) ) {
			return true;
		}

		// check if parent comment is federated
		$parent_comment = \get_comment( $comment->comment_parent );

		return self::is_federatable( $parent_comment );
	}

	/**
	 * Examine a comment ID and look up an existing comment it represents.
	 *
	 * @param string $id ActivityPub object ID (usually a URL) to check.
	 *
	 * @return int|boolean Comment ID, or false on failure.
	 */
	public static function object_id_to_comment( $id ) {
		$comment_query = new WP_Comment_Query(
			array(
				'meta_key'   => 'source_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $id,         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( ! $comment_query->comments ) {
			return false;
		}

		if ( count( $comment_query->comments ) > 1 ) {
			return false;
		}

		return $comment_query->comments[0];
	}

	/**
	 * Verify if URL is a local comment, or if it is a previously received
	 * remote comment (For threading comments locally)
	 *
	 * @param string $url The URL to check.
	 *
	 * @return int comment_ID or null if not found
	 */
	public static function url_to_commentid( $url ) {
		if ( ! $url || ! filter_var( $url, \FILTER_VALIDATE_URL ) ) {
			return null;
		}

		// check for local comment
		if ( \wp_parse_url( \site_url(), \PHP_URL_HOST ) === \wp_parse_url( $url, \PHP_URL_HOST ) ) {
			$query = \wp_parse_url( $url, \PHP_URL_QUERY );

			if ( $query ) {
				parse_str( $query, $params );

				if ( ! empty( $params['c'] ) ) {
					$comment = \get_comment( $params['c'] );

					if ( $comment ) {
						return $comment->comment_ID;
					}
				}
			}
		}

		$args = array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
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
		);

		$query    = new WP_Comment_Query();
		$comments = $query->query( $args );

		if ( $comments && is_array( $comments ) ) {
			return $comments[0]->comment_ID;
		}

		return null;
	}

	/**
	 * Filters the CSS classes to add an ActivityPub class.
	 *
	 * @param string[] $classes    An array of comment classes.
	 * @param string[] $css_class  An array of additional classes added to the list.
	 * @param string   $comment_id The comment ID as a numeric string.
	 *
	 * @return string[] An array of classes.
	 */
	public static function comment_class( $classes, $css_class, $comment_id ) {
		// check if ActivityPub comment
		if ( 'activitypub' === get_comment_meta( $comment_id, 'protocol', true ) ) {
			$classes[] = 'activitypub-comment';
		}

		return $classes;
	}

	/**
	 * Link remote comments to source url.
	 *
	 * @param string $comment_link
	 * @param object|WP_Comment $comment
	 *
	 * @return string $url
	 */
	public static function remote_comment_link( $comment_link, $comment ) {
		if ( ! $comment || is_admin() ) {
			return $comment_link;
		}

		$comment_meta = \get_comment_meta( $comment->comment_ID );

		if ( ! empty( $comment_meta['source_url'][0] ) ) {
			return $comment_meta['source_url'][0];
		} elseif ( ! empty( $comment_meta['source_id'][0] ) ) {
			return $comment_meta['source_id'][0];
		}

		return $comment_link;
	}
}