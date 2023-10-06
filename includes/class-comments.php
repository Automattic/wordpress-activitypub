<?php
namespace Activitypub;

/**
 * Comments Class
 *
 * @author Django Doucet
 */
class Comments {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {

		\add_filter( 'preprocess_comment', array( self::class, 'preprocess_comment' ) );
		\add_filter( 'comment_excerpt', array( self::class, 'comment_excerpt' ), 10, 3 );
		\add_filter( 'comment_text', array( self::class, 'comment_content_filter' ), 10, 3 );
		\add_filter( 'comment_post', array( self::class, 'postprocess_comment' ), 10, 3 );
		\add_filter( 'comment_reply_link', array( self::class, 'comment_reply_link' ), 10, 4 );
		\add_action( 'edit_comment', array( self::class, 'edit_comment' ), 20, 2 ); //schedule_admin_comment_activity
	}

	/**
	 * preprocess local comments for federated replies
	 */
	public static function preprocess_comment( $commentdata ) {
		// only process replies from authorized local actors, for ap enabled post types
		if ( 0 === $commentdata['user_id'] ) {
			return $commentdata;
		}
		$user = \get_userdata( $commentdata['user_id'] );
		$comment_parent_post = \get_post_type( $commentdata['comment_post_ID'] );
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) );

		if ( $user->has_cap( 'publish_post', $commentdata['comment_post_ID'] ) && \in_array( $comment_parent_post, $post_types, true ) ) {
			$commentdata['comment_meta']['protocol'] = 'activitypub';
		}
		return $commentdata;
	}

	/**
	 * Filters the comment text to display webfinger in the Recently Published Dashboard Widget.
	 * comment_excerpt( $comment_excerpt, $comment_id )
	 *
	 * doesn't work on received webfinger links as get_comment_excerpt strips tags
	 * https://developer.wordpress.org/reference/functions/get_comment_excerpt/
	 * @param string $comment_text
	 * @param int $comment_id
	 * @param array $args
	 * @return void
	 */
	public static function comment_excerpt( $comment_excerpt, $comment_id ) {
		$comment = get_comment( $comment_id );
		$comment_excerpt = \apply_filters( 'the_content', $comment_excerpt, $comment );
		return $comment_excerpt;
	}

	/**
	 * comment_text( $comment )
	 * Filters the comment text for display.
	 *
	 * @param string $comment_text
	 * @param WP_Comment $comment
	 * @param array $args
	 * @return void
	 */
	public static function comment_content_filter( $comment_text, $comment, $args ) {
		$comment_text = \apply_filters( 'the_content', $comment_text, $comment );
		$protocol = \get_comment_meta( $comment->comment_ID, 'protocol', true );
		// TODO Test if this is returned by Model/Comment
		if ( 'activitypub' === $protocol ) {
			$updated = \get_comment_meta( $comment->comment_ID, 'activitypub_last_modified', true );
			if ( $updated ) {
				$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				$formatted_datetime = \date_i18n( $format, \strtotime( $updated ) );
				$iso_date = \wp_date( 'c', \strtotime( $updated ) );
				$i18n_text = sprintf(
					/* translators: %s: Displays comment last modified date and time */
					__( '(Last edited on %s)', 'activitypub' ),
					"<time class='modified' datetime='{$iso_date}'>$formatted_datetime</time>"
				);
				$comment_text .= "<small>$i18n_text<small>";
			}
		}
		return $comment_text;
	}

	/**
	 * comment_post()
	 * postprocess_comment for federating replies and inbox-forwarding
	 */
	public static function postprocess_comment( $comment_id, $comment_approved, $commentdata ) {
		//Administrator role users comments bypass transition_comment_status (auto approved)
		$user = \get_userdata( $commentdata['user_id'] );
		if (
			( 1 === $comment_approved ) &&
			\in_array( 'administrator', $user->roles )
		) {
			// Only for Admins
			$wp_comment = \get_comment( $comment_id );
			\wp_schedule_single_event( \time(), 'activitypub_send_comment_activity', array( $wp_comment, 'Create' ) );
		}
	}

	/**
	 * Add reply recipients to comment_reply_link
	 *
	 * https://developer.wordpress.org/reference/hooks/comment_reply_link/
	 * @param string $comment_reply_link
	 * @param array $args
	 * @param WP_Comment $comment
	 * @param WP_Post $post
	 * @return $comment_reply_link
	 */
	public static function comment_reply_link( $comment_reply_link, $args, $comment, $post ) {
		$recipients = \Activitypub\reply_recipients( $comment->comment_ID );
		$comment_reply_link = str_replace( "class='comment-reply-link'", "class='comment-reply-link' data-recipients='${recipients}'", $comment_reply_link );
		return $comment_reply_link;
	}

	/**
	 * edit_comment()
	 *
	 * Fires immediately after a comment is updated in the database.
	 * Fires immediately before comment status transition hooks are fired. (useful only for admin)
	 */
	public static function edit_comment( $comment_id, $commentdata ) {
		update_comment_meta( $comment_id, 'activitypub_last_modified', \wp_date( 'Y-m-d H:i:s' ) );
		$user = \get_userdata( $commentdata['user_id'] );
		if ( \in_array( 'administrator', $user->roles ) ) {
			$wp_comment = \get_comment( $comment_id );
			\wp_schedule_single_event( \time(), 'activitypub_send_comment_activity', array( $wp_comment, 'Update' ) );
		}
	}
}
