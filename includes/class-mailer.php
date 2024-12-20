<?php
/**
 * Mailer Class.
 *
 * @package ActivityPub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Model\User;

/**
 * Mailer Class.
 */
class Mailer {
	/**
	 * Initialize the Mailer.
	 */
	public static function init() {
		\add_filter( 'comment_notification_subject', array( self::class, 'comment_notification_subject' ), 10, 2 );
		\add_filter( 'comment_notification_text', array( self::class, 'comment_notification_text' ), 10, 2 );

		// New follower notification.
		if ( '1' === \get_option( 'activitypub_mailer_new_follower', '0' ) ) {
			\add_action( 'activitypub_notification_follow', array( self::class, 'new_follower' ) );
		}

		// Direct message notification.
		if ( '1' === \get_option( 'activitypub_mailer_new_dm', '0' ) ) {
			\add_action( 'activitypub_inbox_create', array( self::class, 'direct_message' ), 10, 2 );
		}
	}

	/**
	 * Filter the subject line for Like and Announce notifications.
	 *
	 * @param string     $subject    The default subject line.
	 * @param int|string $comment_id The comment ID.
	 *
	 * @return string The filtered subject line.
	 */
	public static function comment_notification_subject( $subject, $comment_id ) {
		$comment = \get_comment( $comment_id );

		if ( ! $comment ) {
			return $subject;
		}

		$type = \get_comment_meta( $comment->comment_ID, 'protocol', true );

		if ( 'activitypub' !== $type ) {
			return $subject;
		}

		$singular = Comment::get_comment_type_attr( $comment->comment_type, 'singular' );

		if ( ! $singular ) {
			return $subject;
		}

		$post = \get_post( $comment->comment_post_ID );

		/* translators: 1: Blog name, 2: Like or Repost, 3: Post title */
		return \sprintf( \esc_html__( '[%1$s] %2$s: %3$s', 'activitypub' ), \esc_html( get_option( 'blogname' ) ), \esc_html( $singular ), \esc_html( $post->post_title ) );
	}

	/**
	 * Filter the notification text for Like and Announce notifications.
	 *
	 * @param string     $message    The default notification text.
	 * @param int|string $comment_id The comment ID.
	 *
	 * @return string The filtered notification text.
	 */
	public static function comment_notification_text( $message, $comment_id ) {
		$comment = \get_comment( $comment_id );

		if ( ! $comment ) {
			return $message;
		}

		$type = \get_comment_meta( $comment->comment_ID, 'protocol', true );

		if ( 'activitypub' !== $type ) {
			return $message;
		}

		$comment_type = Comment::get_comment_type( $comment->comment_type );

		if ( ! $comment_type ) {
			return $message;
		}

		$post                  = \get_post( $comment->comment_post_ID );
		$comment_author_domain = \gethostbyaddr( $comment->comment_author_IP );

		/* translators: 1: Comment type, 2: Post title */
		$notify_message = \sprintf( html_entity_decode( esc_html__( 'New %1$s on your post &#8220;%2$s&#8221;.', 'activitypub' ) ), \esc_html( $comment_type['singular'] ), \esc_html( $post->post_title ) ) . "\r\n\r\n";
		/* translators: 1: Website name, 2: Website IP address, 3: Website hostname. */
		$notify_message .= \sprintf( \esc_html__( 'From: %1$s (IP address: %2$s, %3$s)', 'activitypub' ), \esc_html( $comment->comment_author ), \esc_html( $comment->comment_author_IP ), \esc_html( $comment_author_domain ) ) . "\r\n";
		/* translators: Reaction author URL. */
		$notify_message .= \sprintf( \esc_html__( 'URL: %s', 'activitypub' ), \esc_url( $comment->comment_author_url ) ) . "\r\n\r\n";
		/* translators: Comment type label */
		$notify_message .= \sprintf( \esc_html__( 'You can see all %s on this post here:', 'activitypub' ), \esc_html( $comment_type['label'] ) ) . "\r\n";
		$notify_message .= \get_permalink( $comment->comment_post_ID ) . '#' . \esc_attr( $comment_type['type'] ) . "\r\n\r\n";

		return $notify_message;
	}

	/**
	 * Send a notification email for every new follower.
	 *
	 * @param Notification $notification The notification object.
	 */
	public static function new_follower( $notification ) {
		$actor = get_remote_metadata_by_actor( $notification->actor );

		if ( ! $actor || \is_wp_error( $actor ) ) {
			return;
		}

		$email     = \get_option( 'admin_email' );
		$admin_url = '/options-general.php?page=activitypub&tab=followers';

		if ( $notification->target > Actors::BLOG_USER_ID ) {
			$user = \get_user_by( 'id', $notification->target );

			if ( ! $user ) {
				return;
			}

			$email     = $user->user_email;
			$admin_url = '/users.php?page=activitypub-followers-list';
		}

		/* translators: 1: Blog name, 2: Follower name */
		$subject = \sprintf( \esc_html__( '[%1$s] Follower: %2$s', 'activitypub' ), \esc_html( get_option( 'blogname' ) ), \esc_html( $actor['name'] ) );
		/* translators: 1: Blog name, 2: Follower name */
		$message = \sprintf( \esc_html__( 'New Follower: %2$s.', 'activitypub' ), \esc_html( get_option( 'blogname' ) ), \esc_html( $actor['name'] ) ) . "\r\n\r\n";
		/* translators: Follower URL */
		$message .= \sprintf( \esc_html__( 'URL: %s', 'activitypub' ), \esc_url( $actor['url'] ) ) . "\r\n\r\n";
		$message .= \esc_html__( 'You can see all followers here:', 'activitypub' ) . "\r\n";
		$message .= \esc_url( \admin_url( $admin_url ) ) . "\r\n\r\n";

		\wp_mail( $email, $subject, $message );
	}

	/**
	 * Send a direct message.
	 *
	 * @param array $activity The activity object.
	 * @param int   $user_id  The id of the local blog-user.
	 */
	public static function direct_message( $activity, $user_id ) {
		if (
			is_activity_public( $activity ) ||
			// Only accept messages that have the user in the "to" field.
			empty( $activity['to'] ) ||
			! in_array( Actors::get_by_id( $user_id )->get_id(), (array) $activity['to'], true )
		) {
			return;
		}

		$actor = get_remote_metadata_by_actor( $activity['actor'] );

		if ( ! $actor || \is_wp_error( $actor ) || empty( $activity['object']['content'] ) ) {
			return;
		}

		$email = \get_option( 'admin_email' );

		if ( (int) $user_id > Actors::BLOG_USER_ID ) {
			$user = \get_user_by( 'id', $user_id );

			if ( ! $user ) {
				return;
			}

			$email = $user->user_email;
		}

		$content = \html_entity_decode(
			\wp_strip_all_tags(
				str_replace( '</p>', PHP_EOL . PHP_EOL, $activity['object']['content'] )
			),
			ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
		);

		/* translators: 1: Blog name, 2 Actor name */
		$subject = \sprintf( \esc_html__( '[%1$s] Direct Message from: %2$s', 'activitypub' ), \esc_html( get_option( 'blogname' ) ), \esc_html( $actor['name'] ) );
		/* translators: 1: Blog name, 2: Actor name */
		$message = \sprintf( \esc_html__( 'New Direct Message: %2$s', 'activitypub' ), \esc_html( get_option( 'blogname' ) ), $content ) . "\r\n\r\n";
		/* translators: Actor name */
		$message .= \sprintf( \esc_html__( 'From: %s', 'activitypub' ), \esc_html( $actor['name'] ) ) . "\r\n";
		/* translators: Actor URL */
		$message .= \sprintf( \esc_html__( 'URL: %s', 'activitypub' ), \esc_url( $actor['url'] ) ) . "\r\n\r\n";

		\wp_mail( $email, $subject, $message );
	}
}
