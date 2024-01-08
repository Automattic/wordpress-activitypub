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

		$in_reply_to        = \esc_url_raw( $activity['object']['inReplyTo'] );
		$comment_post_id    = \url_to_postid( $in_reply_to );
		$parent_comment_id  = url_to_commentid( $in_reply_to );

		// save only replys and reactions
		if ( ! $comment_post_id && $parent_comment_id ) {
			$parent_comment  = get_comment( $parent_comment_id );
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
			'comment_author' => isset( $meta['name'] ) ? \esc_attr( $meta['name'] ) : \esc_attr( $meta['preferredUsername'] ),
			'comment_author_url' => \esc_url_raw( $meta['url'] ),
			'comment_content' => \addslashes( $activity['object']['content'] ),
			'comment_type' => 'comment',
			'comment_author_email' => '',
			'comment_parent' => $parent_comment_id ? $parent_comment_id : 0,
			'comment_meta' => array(
				'source_id'  => \esc_url_raw( $activity['object']['id'] ),
				'protocol'   => 'activitypub',
			),
		);

		if ( isset( $meta['icon']['url'] ) ) {
			$commentdata['comment_meta']['avatar_url'] = \esc_url_raw( $meta['icon']['url'] );
		}

		if ( isset( $activity['object']['url'] ) ) {
			$commentdata['comment_meta']['source_url'] = \esc_url_raw( $activity['object']['url'] );
		}

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );
		// do not require email for AP entries
		\add_filter( 'pre_option_require_name_email', '__return_false' );
		// No nonce possible for this submission route
		\add_filter(
			'akismet_comment_nonce',
			function () {
				return 'inactive';
			}
		);
		\add_filter( 'wp_kses_allowed_html', array( self::class, 'allowed_comment_html' ), 10, 2 );

		$comment = \wp_new_comment( $commentdata, true );

		\remove_filter( 'wp_kses_allowed_html', array( self::class, 'allowed_comment_html' ), 10 );
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
	 * @return array|string|int|\WP_Error|false The commentdata or false on failure
	 */
	public static function update_comment( $activity ) {
		$meta = get_remote_metadata_by_actor( $activity['actor'] );

		//Determine comment_ID
		$comment     = object_id_to_comment( \esc_url_raw( $activity['object']['id'] ) );
		$commentdata = \get_comment( $comment, ARRAY_A );

		if ( ! $commentdata ) {
			return false;
		}

		//found a local comment id
		$commentdata['comment_author'] = \esc_attr( $meta['name'] ? $meta['name'] : $meta['preferredUsername'] );
		$commentdata['comment_content'] = \addslashes( $activity['object']['content'] );
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
			function () {
				return 'inactive';
			}
		);
		\add_filter( 'wp_kses_allowed_html', array( self::class, 'allowed_comment_html' ), 10, 2 );

		$state = \wp_update_comment( $commentdata, true );

		\remove_filter( 'wp_kses_allowed_html', array( self::class, 'allowed_comment_html' ), 10 );
		\remove_filter( 'pre_option_require_name_email', '__return_false' );
		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		if ( 1 === $state ) {
			return $commentdata;
		} else {
			return $state; // Either `false` or a `WP_Error` instance or `0` or `1`!
		}
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

		// get URL, because $actor seems to be the ID
		if ( $meta && ! is_wp_error( $meta ) && isset( $meta['url'] ) ) {
			$actor = $meta['url'];
		}

		$args = array(
			'nopaging'   => true,
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

	/**
	 * Adds line breaks to the list of allowed comment tags.
	 *
	 * @param  array  $allowed_tags Allowed HTML tags.
	 * @param  string $context      Context.
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
}
