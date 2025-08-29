<?php
/**
 * Mailer Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;

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

		\add_action( 'activitypub_inbox_follow', array( self::class, 'new_follower' ), 10, 2 );
		\add_action( 'activitypub_inbox_create', array( self::class, 'direct_message' ), 10, 2 );
		\add_action( 'activitypub_inbox_create', array( self::class, 'mention' ), 20, 2 );  /** After @see \Activitypub\Handler\Create::handle_create() */
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

		// Check if this is a reaction to a post or a comment.
		if ( 0 === (int) $comment->comment_parent ) {
			$notify_message = \sprintf(
				/* translators: 1: Comment type, 2: Post title */
				\html_entity_decode( esc_html__( 'New %1$s on your post &#8220;%2$s&#8221;.', 'activitypub' ) ),
				\esc_html( $comment_type['singular'] ),
				\esc_html( $post->post_title )
			) . PHP_EOL . PHP_EOL;

		} else {
			$parent_comment = \get_comment( $comment->comment_parent );
			$notify_message = \sprintf(
				/* translators: 1: Comment type, 2: Post title, 3: Parent comment author */
				\html_entity_decode( esc_html__( 'New %1$s on your post &#8220;%2$s&#8221; in reply to %3$s&#8217;s comment.', 'activitypub' ) ),
				\esc_html( $comment_type['singular'] ),
				\esc_html( $post->post_title ),
				\esc_html( $parent_comment->comment_author )
			) . PHP_EOL . PHP_EOL;
		}

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
	 * @param array $activity The activity object.
	 * @param int   $user_id  The id of the local blog-user.
	 */
	public static function new_follower( $activity, $user_id ) {
		// Do not send notifications to the Application user.
		if ( Actors::APPLICATION_USER_ID === $user_id ) {
			return;
		}

		if ( $user_id > Actors::BLOG_USER_ID ) {
			if ( ! \get_user_option( 'activitypub_mailer_new_follower', $user_id ) ) {
				return;
			}

			$email     = \get_userdata( $user_id )->user_email;
			$admin_url = '/users.php?page=activitypub-followers-list';
		} else {
			if ( '1' !== \get_option( 'activitypub_blog_user_mailer_new_follower', '1' ) ) {
				return;
			}

			$email     = \get_option( 'admin_email' );
			$admin_url = '/options-general.php?page=activitypub&tab=followers';
		}

		$actor = get_remote_metadata_by_actor( $activity['actor'] );
		if ( ! $actor || \is_wp_error( $actor ) ) {
			return;
		}

		$actor = self::normalize_actor( $actor );

		$template_args = array_merge(
			$actor,
			array(
				'admin_url' => $admin_url,
				'user_id'   => $user_id,
				'stats'     => array(
					'outbox'    => null,
					'followers' => null,
					'following' => null,
				),
			)
		);

		foreach ( $template_args['stats'] as $field => $value ) {
			if ( empty( $actor[ $field ] ) ) {
				continue;
			}

			$result = Http::get( $actor[ $field ], true );
			if ( 200 === \wp_remote_retrieve_response_code( $result ) ) {
				$body = \json_decode( \wp_remote_retrieve_body( $result ), true );
				if ( isset( $body['totalItems'] ) ) {
					$template_args['stats'][ $field ] = $body['totalItems'];
				}
			}
		}

		/* translators: 1: Blog name, 2: Follower name */
		$subject = \sprintf( \__( '[%1$s] New Follower: %2$s', 'activitypub' ), \get_option( 'blogname' ), $actor['name'] );

		\ob_start();
		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/emails/new-follower.php', false, $template_args );
		$html_message = \ob_get_clean();

		$alt_function = function ( $mailer ) use ( $actor, $admin_url ) {
			/* translators: 1: Follower name */
			$message = \sprintf( \__( 'New Follower: %1$s.', 'activitypub' ), $actor['name'] ) . "\r\n\r\n";
			/* translators: Follower URL */
			$message            .= \sprintf( \__( 'URL: %s', 'activitypub' ), \esc_url( $actor['url'] ) ) . "\r\n\r\n";
			$message            .= \__( 'You can see all followers here:', 'activitypub' ) . "\r\n";
			$message            .= \esc_url( \admin_url( $admin_url ) ) . "\r\n\r\n";
			$mailer->{'AltBody'} = $message;
		};
		\add_action( 'phpmailer_init', $alt_function );

		\wp_mail( $email, $subject, $html_message, array( 'Content-type: text/html' ) );

		\remove_action( 'phpmailer_init', $alt_function );
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

		if ( $user_id > Actors::BLOG_USER_ID ) {
			if ( ! \get_user_option( 'activitypub_mailer_new_dm', $user_id ) ) {
				return;
			}

			$email = \get_userdata( $user_id )->user_email;
		} else {
			if ( '1' !== \get_option( 'activitypub_blog_user_mailer_new_dm', '1' ) ) {
				return;
			}

			$email = \get_option( 'admin_email' );
		}

		$actor = get_remote_metadata_by_actor( $activity['actor'] );

		if ( ! $actor || \is_wp_error( $actor ) || empty( $activity['object']['content'] ) ) {
			return;
		}

		$actor = self::normalize_actor( $actor );

		$template_args = array(
			'activity' => $activity,
			'actor'    => $actor,
			'user_id'  => $user_id,
		);

		/* translators: 1: Blog name, 2 Actor name */
		$subject = \sprintf( \esc_html__( '[%1$s] Direct Message from: %2$s', 'activitypub' ), \esc_html( \get_option( 'blogname' ) ), \esc_html( $actor['name'] ) );

		\ob_start();
		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/emails/new-dm.php', false, $template_args );
		$html_message = \ob_get_clean();

		$alt_function = function ( $mailer ) use ( $actor, $activity ) {
			$content = \html_entity_decode(
				\wp_strip_all_tags(
					str_replace( '</p>', PHP_EOL . PHP_EOL, $activity['object']['content'] )
				),
				ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
			);

			/* translators: Actor name */
			$message = \sprintf( \esc_html__( 'New Direct Message: %s', 'activitypub' ), $content ) . "\r\n\r\n";
			/* translators: Actor name */
			$message .= \sprintf( \esc_html__( 'From: %s', 'activitypub' ), \esc_html( $actor['name'] ) ) . "\r\n";
			/* translators: Message URL */
			$message .= \sprintf( \esc_html__( 'URL: %s', 'activitypub' ), \esc_url( $activity['object']['id'] ) ) . "\r\n\r\n";

			$mailer->{'AltBody'} = $message;
		};
		\add_action( 'phpmailer_init', $alt_function );

		\wp_mail( $email, $subject, $html_message, array( 'Content-type: text/html' ) );

		\remove_action( 'phpmailer_init', $alt_function );
	}

	/**
	 * Send a mention notification.
	 *
	 * @param array $activity The activity object.
	 * @param int   $user_id  The id of the local blog-user.
	 */
	public static function mention( $activity, $user_id ) {
		if (
			// Only accept messages that have the user in the "cc" field.
			empty( $activity['cc'] ) ||
			! in_array( Actors::get_by_id( $user_id )->get_id(), (array) $activity['cc'], true )
		) {
			return;
		}

		if (
			// Do not send a mention notification if the activity is a reply to a local post or comment.
			is_activity_reply( $activity ) &&
			object_id_to_comment( $activity['object']['id'] )
		) {
			return;
		}

		if ( $user_id > Actors::BLOG_USER_ID ) {
			if ( ! \get_user_option( 'activitypub_mailer_new_mention', $user_id ) ) {
				return;
			}

			$email = \get_userdata( $user_id )->user_email;
		} else {
			if ( '1' !== \get_option( 'activitypub_blog_user_mailer_new_mention', '1' ) ) {
				return;
			}

			$email = \get_option( 'admin_email' );
		}

		$actor = get_remote_metadata_by_actor( $activity['actor'] );
		if ( \is_wp_error( $actor ) ) {
			return;
		}

		$actor = self::normalize_actor( $actor );

		$template_args = array(
			'activity' => $activity,
			'actor'    => $actor,
			'user_id'  => $user_id,
		);

		/* translators: 1: Blog name, 2 Actor name */
		$subject = \sprintf( \esc_html__( '[%1$s] Mention from: %2$s', 'activitypub' ), \esc_html( \get_option( 'blogname' ) ), \esc_html( $actor['name'] ) );

		\ob_start();
		\load_template( ACTIVITYPUB_PLUGIN_DIR . 'templates/emails/new-mention.php', false, $template_args );
		$html_message = \ob_get_clean();

		$alt_function = function ( $mailer ) use ( $actor, $activity ) {
			$content = \html_entity_decode(
				\wp_strip_all_tags(
					str_replace( '</p>', PHP_EOL . PHP_EOL, $activity['object']['content'] )
				),
				ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
			);

			/* translators: Message content */
			$message = \sprintf( \esc_html__( 'New Mention: %s', 'activitypub' ), $content ) . "\r\n\r\n";
			/* translators: Actor name */
			$message .= \sprintf( \esc_html__( 'From: %s', 'activitypub' ), \esc_html( $actor['name'] ) ) . "\r\n";
			/* translators: Message URL */
			$message .= \sprintf( \esc_html__( 'URL: %s', 'activitypub' ), \esc_url( $activity['object']['id'] ) ) . "\r\n\r\n";

			$mailer->{'AltBody'} = $message;
		};
		\add_action( 'phpmailer_init', $alt_function );

		\wp_mail( $email, $subject, $html_message, array( 'Content-type: text/html' ) );

		\remove_action( 'phpmailer_init', $alt_function );
	}

	/**
	 * Apply defaults to the actor object.
	 *
	 * Ensure that the actor object has a name, url, and webfinger.
	 *
	 * @param array $actor The actor object.
	 *
	 * @return array The inflated actor object.
	 */
	private static function normalize_actor( $actor ) {
		if ( empty( $actor['name'] ) ) {
			$actor['name'] = $actor['preferredUsername'];
		}

		if ( empty( $actor['url'] ) ) {
			$actor['url'] = $actor['id'];
		}
		$actor['url'] = object_to_uri( $actor['url'] );

		if ( empty( $actor['webfinger'] ) ) {
			$actor['webfinger'] = '@' . ( $actor['preferredUsername'] ?? $actor['name'] ) . '@' . \wp_parse_url( $actor['url'], PHP_URL_HOST );
		}

		return $actor;
	}
}
