<?php
namespace Activitypub\Handler;

/**
 * Handle Like requests
 */
class Like {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_like',
			array( self::class, 'handle_like' ),
			10,
			3
		);
	}

	/**
	 * Handles "Like" requests
	 *
	 * @param array                 $array    The Activity array.
	 * @param int                   $user_id  The ID of the local blog user.
	 * @param \Activitypub\Activity $activity The Activity object.
	 *
	 * @return void
	 */
	public static function handle_like( $array, $user_id, $activity = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
			return;
		}

		if ( ! empty( $array['object'] ) && filter_var( $array['object'], FILTER_VALIDATE_URL ) ) {
			$url = $array['object'];
		} elseif ( ! empty( $array['object']['id'] ) && filter_var( $array['object']['id'], FILTER_VALIDATE_URL ) ) {
			$url = $array['object']['id'];
		}

		if ( empty( $url ) ) {
			return;
		}

		$exists = \Activitypub\Comment::object_id_to_comment( esc_url_raw( $url ) );
		if ( $exists ) {
			return;
		}

		$state    = static::add_like( $url, $array );
		$reaction = null;

		if ( $state && ! is_wp_error( $state ) ) {
			$reaction = get_comment( $state );
		}

		do_action( 'activitypub_handled_like', $array, $user_id, $state, $reaction );
	}


	/**
	 * Adds an incoming like.
	 *
	 * @param string $url      Object URL.
	 * @param array  $activity Activity array.
	 *
	 * @return array|false      Comment data or `false` on failure.
	 */
	protected static function add_like( $url, $activity ) {
		$comment_post_id   = url_to_postid( $url );
		$parent_comment_id = \Activitypub\url_to_commentid( $url );

		if ( ! $comment_post_id && $parent_comment_id ) {
			$parent_comment  = get_comment( $parent_comment_id );
			$comment_post_id = $parent_comment->comment_post_ID;
		}

		if ( ! $comment_post_id ) {
			// Not a reply to a post or comment.
			return false;
		}

		$actor = \Activitypub\object_to_uri( $activity['actor'] );
		$meta  = \Activitypub\get_remote_metadata_by_actor( $actor );

		if ( ! $meta || is_wp_error( $meta ) ) {
			return false;
		}

		$commentdata = array(
			'comment_post_ID'      => $comment_post_id,
			'comment_author'       => isset( $meta['name'] ) ? \esc_attr( $meta['name'] ) : \esc_attr( $meta['preferredUsername'] ),
			'comment_author_url'   => esc_url_raw( \Activitypub\object_to_uri( $meta['url'] ) ),
			'comment_content'      => __( '&hellip; liked this!', 'activitypub' ), // Default content.
			'comment_type'         => 'like',
			'comment_author_email' => '',
			'comment_parent'       => $parent_comment_id ? $parent_comment_id : 0,
			'comment_meta'         => array(
				'source_id' => esc_url_raw( $activity['id'] ), // To be able to detect existing comments.
				'protocol'  => 'activitypub',
			),
		);

		if ( isset( $meta['icon']['url'] ) ) {
			$commentdata['comment_meta']['avatar_url'] = esc_url_raw( $meta['icon']['url'] );
		}

		if ( isset( $activity['object']['url'] ) ) {
			$commentdata['comment_meta']['source_url'] = esc_url_raw( \Activitypub\object_to_uri( $activity['object']['url'] ) );
		}

		// Disable flood control.
		remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// No nonce possible for this submission route.
		add_filter(
			'akismet_comment_nonce',
			function () {
				return 'inactive';
			}
		);

		$comment = wp_new_comment( $commentdata, true );

		add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		return $comment;
	}
}
