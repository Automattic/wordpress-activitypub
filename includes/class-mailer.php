<?php
/**
 * Mailer Class.
 *
 * @package ActivityPub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
/**
 * Mailer Class
 */
class Mailer {
	/**
	 * Initialize the Mailer.
	 */
	public static function init() {
		add_filter( 'comment_notification_subject', array( self::class, 'comment_notification_subject' ), 10, 2 );
		add_filter( 'comment_notification_text', array( self::class, 'comment_notification_text' ), 10, 2 );

		// New follower notification.
		add_action( 'activitypub_notification_follow', array( self::class, 'new_follower' ) );
	}

	/**
	 * Filter the mail-subject for Like and Announce notifications.
	 *
	 * @param string     $subject    The default mail-subject.
	 * @param int|string $comment_id The comment ID.
	 *
	 * @return string The filtered mail-subject
	 */
	public static function comment_notification_subject( $subject, $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return $subject;
		}

		$type = get_comment_meta( $comment->comment_ID, 'protocol', true );

		if ( 'activitypub' !== $type ) {
			return $subject;
		}

		$singular = Comment::get_comment_type_attr( $comment->comment_type, 'singular' );

		if ( ! $singular ) {
			return $subject;
		}

		$post = get_post( $comment->comment_post_ID );

		/* translators: %1$s: Blog name, %2$s: Post title */
		return sprintf( __( '[%1$s] %2$s: %3$s', 'activitypub' ), get_option( 'blogname' ), $singular, $post->post_title );
	}

	/**
	 * Filter the mail-content for Like and Announce notifications.
	 *
	 * @param string     $message    The default mail-content.
	 * @param int|string $comment_id The comment ID.
	 *
	 * @return string The filtered mail-content
	 */
	public static function comment_notification_text( $message, $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return $message;
		}

		$type = get_comment_meta( $comment->comment_ID, 'protocol', true );

		if ( 'activitypub' !== $type ) {
			return $message;
		}

		$comment_type = Comment::get_comment_type( $comment->comment_type );

		if ( ! $comment_type ) {
			return $message;
		}

		$post                  = get_post( $comment->comment_post_ID );
		$comment_author_domain = gethostbyaddr( $comment->comment_author_IP );

		/* translators: %1$s: Comment type, %2$s: Post title */
		$notify_message = \sprintf( __( 'New %1$s on your post "%2$s"', 'activitypub' ), $comment_type['singular'], $post->post_title ) . "\r\n";
		/* translators: 1: Trackback/pingback website name, 2: Website IP address, 3: Website hostname. */
		$notify_message .= \sprintf( __( 'Website: %1$s (IP address: %2$s, %3$s)', 'activitypub' ), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		/* translators: %s: Trackback/pingback/comment author URL. */
		$notify_message .= \sprintf( __( 'URL: %s', 'activitypub' ), $comment->comment_author_url ) . "\r\n\r\n";
		/* translators: %s: Comment type label */
		$notify_message .= \sprintf( __( 'You can see all %s on this post here:', 'activitypub' ), $comment_type['label'] ) . "\r\n";
		$notify_message .= \get_permalink( $comment->comment_post_ID ) . '#' . $comment_type['singular'] . "\r\n\r\n";

		return $notify_message;
	}

	/**
	 * Send a Mail for every new follower.
	 *
	 * @param Notification $notification The notification object.
	 */
	public static function new_follower( $notification ) {
		$actor = get_remote_metadata_by_actor( $notification->actor );

		if ( ! $actor || \is_wp_error( $actor ) ) {
			return;
		}

		$email = \get_option( 'admin_email' );

		if ( (int) $notification->target > Actors::BLOG_USER_ID ) {
			$user = \get_user_by( 'id', $notification->target );

			if ( ! $user ) {
				return;
			}

			$email = $user->user_email;
		}

		/* translators: %1$s: Blog name, %2$s: Follower name */
		$subject = \sprintf( \__( '[%1$s] Follower: %2$s', 'activitypub' ), get_option( 'blogname' ), $actor['name'] );
		/* translators: %1$s: Blog name, %2$s: Follower name */
		$message = \sprintf( \__( 'New follower: %2$s', 'activitypub' ), get_option( 'blogname' ), $actor['name'] ) . "\r\n";
		/* translators: %s: Follower URL */
		$message .= \sprintf( \__( 'URL: %s', 'activitypub' ), $actor['url'] ) . "\r\n\r\n";
		$message .= \sprintf( \__( 'You can see all followers here:', 'activitypub' ) ) . "\r\n";
		$message .= \esc_url( \admin_url( '/users.php?page=activitypub-followers-list' ) ) . "\r\n\r\n";

		\wp_mail( $email, $subject, $message );
	}
}
