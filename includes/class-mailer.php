<?php
/**
 * Mailer Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Comment;

/**
 * Mailer Class.
 */
class Mailer {
	/**
	 * Meta key on the remote actor for the last new-follower email, %d is the local user ID.
	 *
	 * @var string
	 */
	const FOLLOWER_EMAIL_META_KEY = '_activitypub_last_follower_email_sent_%d';

	/**
	 * Initialize the Mailer.
	 *
	 * @return void
	 */
	public static function init() {
		\add_filter( 'comment_notification_subject', array( self::class, 'comment_notification_subject' ), 10, 2 );
		\add_filter( 'comment_notification_text', array( self::class, 'comment_notification_text' ), 10, 2 );

		\add_action( 'activitypub_handled_follow', array( self::class, 'new_follower' ), 10, 4 );

		\add_action( 'activitypub_handled_inbox_create', array( self::class, 'direct_message' ), 10, 2 );
		// Priority 20 keeps this after @see \Activitypub\Handler\Create::handle_create(), whose comment the reply check reads.
		\add_action( 'activitypub_handled_inbox_create', array( self::class, 'mention' ), 20, 2 );

		\add_filter( 'notify_post_author', array( self::class, 'maybe_prevent_comment_notification' ), 10, 2 );
		\add_filter( 'notify_post_author', array( self::class, 'maybe_prevent_reaction_notification' ), 10, 2 );
		\add_filter( 'notify_moderator', array( self::class, 'maybe_prevent_comment_notification' ), 10, 2 );
		// Late, so the addresses other code adds are counted too.
		\add_filter( 'comment_notification_recipients', array( self::class, 'throttle_comment_recipients' ), PHP_INT_MAX, 2 );
		\add_filter( 'comment_moderation_recipients', array( self::class, 'throttle_comment_recipients' ), PHP_INT_MAX, 2 );
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
		return \sprintf( \esc_html__( '[%1$s] %2$s: %3$s', 'activitypub' ), \esc_html( \get_option( 'blogname' ) ), \esc_html( $singular ), \esc_html( $post->post_title ) );
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
		$comment_author_domain = '';

		// Only attempt to resolve hostname if we have a valid IP address.
		if ( \filter_var( $comment->comment_author_IP, FILTER_VALIDATE_IP ) ) {
			$comment_author_domain = \gethostbyaddr( $comment->comment_author_IP );
		}

		// Check if this is a reaction to a post or a comment.
		if ( 0 === (int) $comment->comment_parent ) {
			$notify_message = \sprintf(
				/* translators: 1: Comment type, 2: Post title */
				\html_entity_decode( \esc_html__( 'New %1$s on your post &#8220;%2$s&#8221;.', 'activitypub' ) ),
				\esc_html( $comment_type['singular'] ),
				\esc_html( $post->post_title )
			) . PHP_EOL . PHP_EOL;

		} else {
			$parent_comment = \get_comment( $comment->comment_parent );
			$notify_message = \sprintf(
				/* translators: 1: Comment type, 2: Post title, 3: Parent comment author */
				\html_entity_decode( \esc_html__( 'New %1$s on your post &#8220;%2$s&#8221; in reply to %3$s&#8217;s comment.', 'activitypub' ) ),
				\esc_html( $comment_type['singular'] ),
				\esc_html( $post->post_title ),
				\esc_html( $parent_comment->comment_author )
			) . PHP_EOL . PHP_EOL;
		}

		/* translators: 1: Website name, 2: Website IP address, 3: Website hostname. */
		$notify_message .= \sprintf( \esc_html__( 'From: %1$s (IP address: %2$s, %3$s)', 'activitypub' ), \esc_html( $comment->comment_author ), \esc_html( $comment->comment_author_IP ), \esc_html( $comment_author_domain ) ) . "\r\n";
		/* translators: Reaction author URL. */
		$notify_message .= \sprintf( \esc_html__( 'URL: %s', 'activitypub' ), \esc_url( $comment->comment_author_url ) ) . "\r\n";

		// For quotes, link to the quoting post itself so the author can review and respond.
		if ( 'quote' === $comment->comment_type ) {
			$quote_url = Comment::get_source_url( (int) $comment->comment_ID );

			if ( $quote_url ) {
				/* translators: Quoting post URL. */
				$notify_message .= \sprintf( \esc_html__( 'Quoting post: %s', 'activitypub' ), \esc_url( $quote_url ) ) . "\r\n";
			}
		}

		$notify_message .= "\r\n";
		/* translators: Comment type label */
		$notify_message .= \sprintf( \esc_html__( 'You can see all %s on this post here:', 'activitypub' ), \esc_html( $comment_type['label'] ) ) . "\r\n";
		$notify_message .= \get_permalink( (int) $comment->comment_post_ID ) . '#' . \esc_attr( $comment_type['type'] ) . "\r\n\r\n";

		return $notify_message;
	}

	/**
	 * Send a notification email for every new follower.
	 *
	 * @param array                   $activity     The activity object.
	 * @param int|int[]               $user_ids     The id(s) of the local blog-user(s).
	 * @param bool                    $success      True on success, false otherwise.
	 * @param \WP_Post|\WP_Error|null $remote_actor Optional. The remote actor post object.
	 *
	 * @return void
	 */
	public static function new_follower( $activity, $user_ids, $success, $remote_actor = null ) {
		// Only send notification if the follow was successful.
		if ( ! $success ) {
			return;
		}

		// Extract the user ID (follows are always for a single user).
		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;

		if ( $user_id > Actors::BLOG_USER_ID ) {
			if ( ! \get_user_option( 'activitypub_mailer_new_follower', $user_id ) ) {
				return;
			}

			$admin_url = '/users.php?page=activitypub-followers-list';
		} else {
			if ( '1' !== \get_option( 'activitypub_blog_user_mailer_new_follower', '1' ) ) {
				return;
			}

			$admin_url = '/options-general.php?page=activitypub&tab=followers';
		}

		$email = self::get_email_address( $user_id );
		if ( ! $email ) {
			return;
		}

		$meta_key  = null;
		$last_sent = 0;

		// Prevent repeated notification spam if an actor cycles Follow and Undo.
		if ( $remote_actor instanceof \WP_Post ) {
			$meta_key  = \sprintf( self::FOLLOWER_EMAIL_META_KEY, $user_id );
			$last_sent = (int) \get_post_meta( $remote_actor->ID, $meta_key, true );

			if ( $last_sent && ( \time() - $last_sent ) < DAY_IN_SECONDS ) {
				return;
			}
		}

		$actor = get_remote_metadata_by_actor( $activity['actor'] );
		if ( ! $actor || \is_wp_error( $actor ) ) {
			return;
		}

		if ( $meta_key ) {
			$claimed = $last_sent
				? \update_post_meta( $remote_actor->ID, $meta_key, \time(), $last_sent )
				: \add_post_meta( $remote_actor->ID, $meta_key, \time(), true );

			if ( ! $claimed ) {
				return;
			}
		}

		$actor = self::normalize_actor( $actor );

		// Replace emoji in actor name and summary.
		if ( ! empty( $actor['name'] ) ) {
			$actor['name'] = Emoji::replace_for_actor( $actor['name'], $actor['url'] );
		}
		if ( ! empty( $actor['summary'] ) ) {
			$actor['summary'] = Emoji::replace_for_actor( $actor['summary'], $actor['url'] );
		}

		$template_args = \array_merge(
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

			$result = Http::get( $actor[ $field ], array( 'limit_response_size' => Http::SMALL_RESPONSE_SIZE ), true );
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

		$alt_function = static function ( $mailer ) use ( $actor, $admin_url ) {
			/* translators: 1: Follower name */
			$message = \sprintf( \__( 'New Follower: %1$s.', 'activitypub' ), $actor['name'] ) . "\r\n\r\n";
			/* translators: Follower URL */
			$message            .= \sprintf( \__( 'URL: %s', 'activitypub' ), \esc_url( $actor['url'] ) ) . "\r\n\r\n";
			$message            .= \__( 'You can see all followers here:', 'activitypub' ) . "\r\n";
			$message            .= \esc_url( \admin_url( $admin_url ) ) . "\r\n\r\n";
			$mailer->{'AltBody'} = $message;
		};

		$sent = self::maybe_send_mail( $email, $subject, $html_message, $alt_function );

		if ( ! $sent && $meta_key ) {
			if ( $last_sent ) {
				\update_post_meta( $remote_actor->ID, $meta_key, $last_sent );
			} else {
				\delete_post_meta( $remote_actor->ID, $meta_key );
			}
		}
	}

	/**
	 * Send a direct message.
	 *
	 * @param array     $activity The activity object.
	 * @param int|int[] $user_ids The id(s) of the local blog-user(s).
	 *
	 * @return void
	 */
	public static function direct_message( $activity, $user_ids ) {
		// Early return if activity is public or has no recipients.
		if ( is_activity_public( $activity ) || empty( $activity['to'] ) ) {
			return;
		}

		// Normalize to array.
		$user_ids = (array) $user_ids;

		// Build a map of user_id => actor_id and filter to only users in the "to" field.
		$recipients = array();
		foreach ( $user_ids as $user_id ) {
			$actor = Actors::get_by_id( $user_id );
			if ( \is_wp_error( $actor ) ) {
				continue;
			}

			$actor_id = $actor->get_id();
			if ( \in_array( $actor_id, (array) $activity['to'], true ) ) {
				$recipients[ $user_id ] = $actor_id;
			}
		}

		// No matching recipients.
		if ( empty( $recipients ) ) {
			return;
		}

		// Get actor metadata once (shared for all emails).
		$actor = get_remote_metadata_by_actor( $activity['actor'] );
		if ( ! $actor || \is_wp_error( $actor ) || empty( $activity['object']['content'] ) ) {
			return;
		}

		$actor = self::normalize_actor( $actor );

		// Send email to each recipient.
		foreach ( $recipients as $user_id => $actor_id ) {
			// Check user preferences.
			if ( $user_id > Actors::BLOG_USER_ID ) {
				if ( ! \get_user_option( 'activitypub_mailer_new_dm', $user_id ) ) {
					continue;
				}
			} elseif ( '1' !== \get_option( 'activitypub_blog_user_mailer_new_dm', '1' ) ) {
				continue;
			}

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

			$alt_function = static function ( $mailer ) use ( $actor, $activity ) {
				$content = \html_entity_decode(
					\wp_strip_all_tags(
						\str_replace( '</p>', PHP_EOL . PHP_EOL, $activity['object']['content'] )
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

			self::maybe_send_mail( self::get_email_address( $user_id ), $subject, $html_message, $alt_function );
		}
	}

	/**
	 * Send a mention notification.
	 *
	 * @param array     $activity The activity object.
	 * @param int|int[] $user_ids The id(s) of the local blog-user(s).
	 *
	 * @return void
	 */
	public static function mention( $activity, $user_ids ) {
		// Early return if activity has no mentions.
		if ( empty( $activity['object']['tag'] ) ) {
			return;
		}

		// Do not send a mention notification if the activity is a reply to a local post or comment.
		if ( is_activity_reply( $activity ) && object_id_to_comment( \esc_url_raw( object_to_id( $activity['object'] ) ) ) ) {
			return;
		}

		$recipients = array();
		$mentions   = \wp_list_filter( (array) $activity['object']['tag'], array( 'type' => 'Mention' ) );
		$mentions   = \array_map( '\Activitypub\object_to_uri', $mentions );
		foreach ( (array) $user_ids as $user_id ) {
			$actor = Actors::get_by_id( $user_id );
			if ( \is_wp_error( $actor ) ) {
				continue;
			}

			$actor_id = $actor->get_id();
			if ( \in_array( $actor_id, $mentions, true ) ) {
				$recipients[ $user_id ] = $actor_id;
			}
		}

		// No matching recipients.
		if ( empty( $recipients ) ) {
			return;
		}

		// Get actor metadata once (shared for all emails).
		$actor = get_remote_metadata_by_actor( $activity['actor'] );
		if ( \is_wp_error( $actor ) ) {
			return;
		}

		$actor = self::normalize_actor( $actor );

		// Send email to each recipient.
		foreach ( $recipients as $user_id => $actor_id ) {
			// Check user preferences.
			if ( $user_id > Actors::BLOG_USER_ID ) {
				if ( ! \get_user_option( 'activitypub_mailer_new_mention', $user_id ) ) {
					continue;
				}
			} elseif ( '1' !== \get_option( 'activitypub_blog_user_mailer_new_mention', '1' ) ) {
				continue;
			}

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

			$alt_function = static function ( $mailer ) use ( $actor, $activity ) {
				$content = \html_entity_decode(
					\wp_strip_all_tags(
						\str_replace( '</p>', PHP_EOL . PHP_EOL, $activity['object']['content'] )
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

			self::maybe_send_mail( self::get_email_address( $user_id ), $subject, $html_message, $alt_function );
		}
	}

	/**
	 * Send a templated email to a user.
	 *
	 * @param int    $user_id  The user ID (or BLOG_USER_ID for blog actor).
	 * @param string $subject  The email subject.
	 * @param string $template The template name (without path/extension).
	 * @param array  $args     Template arguments.
	 * @param string $alt_body Optional plain text alternative. Auto-generated from HTML if empty.
	 *
	 * @return bool True if email was sent, false otherwise.
	 */
	public static function send( $user_id, $subject, $template, $args = array(), $alt_body = '' ) {
		$email = self::get_email_address( $user_id );
		if ( ! $email ) {
			return false;
		}

		// Load the HTML template.
		$template_file = ACTIVITYPUB_PLUGIN_DIR . 'templates/emails/' . \sanitize_file_name( $template ) . '.php';

		/**
		 * Filter the email template file path.
		 *
		 * @param string $template_file The template file path.
		 * @param string $template      The template name.
		 * @param int    $user_id       The user ID.
		 * @param array  $args          Template arguments.
		 */
		$template_file = \apply_filters( 'activitypub_email_template', $template_file, $template, $user_id, $args );

		if ( ! \file_exists( $template_file ) ) {
			return false;
		}

		\ob_start();
		\load_template( $template_file, false, $args );
		$html_message = \ob_get_clean();

		// Build plain text alternative from HTML if not provided.
		if ( empty( $alt_body ) ) {
			$alt_body = \wp_strip_all_tags( $html_message );
		}
		$alt_function = static function ( $mailer ) use ( $alt_body ) {
			$mailer->{'AltBody'} = $alt_body;
		};
		\add_action( 'phpmailer_init', $alt_function );

		$result = \wp_mail( $email, $subject, $html_message, array( 'Content-type: text/html' ) );

		\remove_action( 'phpmailer_init', $alt_function );

		return $result;
	}

	/**
	 * Get the address a recipient's mails go to.
	 *
	 * @since unreleased
	 *
	 * @param int $user_id The recipient (or BLOG_USER_ID for the blog actor).
	 *
	 * @return string The address, or an empty string when the user has none.
	 */
	private static function get_email_address( $user_id ) {
		if ( $user_id <= Actors::BLOG_USER_ID ) {
			return (string) \get_option( 'admin_email' );
		}

		$user = \get_userdata( $user_id );

		return $user ? $user->user_email : '';
	}

	/**
	 * Tell a recipient that further notification mails will not be sent this hour.
	 *
	 * Sent once per window, for the first mail that is not sent, see maybe_throttle_mail().
	 *
	 * @since unreleased
	 *
	 * @param string $email The recipient's address.
	 * @param int    $limit How many notification mails an address can get in this window.
	 * @param int    $reset When notification mails are sent again, as a Unix timestamp.
	 *
	 * @return bool True if the mail was sent, false otherwise.
	 */
	public static function send_limit_notice( $email, $limit, $reset ) {
		return \wp_mail(
			$email,
			/* translators: %s: Blog name */
			\sprintf( \__( '[%s] Notification emails are paused', 'activitypub' ), \get_option( 'blogname' ) ),
			\sprintf(
				/* translators: 1: Number of emails, 2: Time of day, 3: Dashboard URL */
				\_n(
					'You reached the limit of %1$d notification email about activity from the Fediverse. Further ones will not be sent until %2$s. Check your dashboard for updates: %3$s',
					'You reached the limit of %1$d notification emails about activity from the Fediverse. Further ones will not be sent until %2$s. Check your dashboard for updates: %3$s',
					$limit,
					'activitypub'
				),
				$limit,
				\wp_date( \get_option( 'time_format' ), $reset ),
				\admin_url()
			)
		);
	}

	/**
	 * Send a notification mail, unless its recipient had enough of them this hour.
	 *
	 * New follower, direct message and mention notifications go through here and share one
	 * allowance per address, whoever sends them.
	 *
	 * @since unreleased
	 *
	 * @param string   $email        The recipient's address.
	 * @param string   $subject      The subject.
	 * @param string   $message      The HTML message.
	 * @param callable $alt_function The `phpmailer_init` callback that sets the plain text message.
	 *
	 * @return bool True when the mail was sent, false when the allowance was used up or sending failed.
	 */
	private static function maybe_send_mail( $email, $subject, $message, $alt_function ) {
		if ( self::maybe_throttle_mail( 'mail', $email ) ) {
			return false;
		}

		\add_action( 'phpmailer_init', $alt_function );

		$sent = \wp_mail( $email, $subject, $message, array( 'Content-type: text/html' ) );

		\remove_action( 'phpmailer_init', $alt_function );

		return $sent;
	}

	/**
	 * Count a notification mail against its recipient's allowance, and tell whether it may be sent.
	 *
	 * Counted per address and site, so an inbox that several users or the site share has one
	 * allowance. A mail counts even when sending it fails. The first mail that is not sent in a window sends the recipient a
	 * notice instead, see send_limit_notice().
	 *
	 * @since unreleased
	 *
	 * @param string $bucket `mail` for new followers, direct messages and mentions, `comment_mail` for
	 *                       replies and reactions.
	 * @param string $email  The recipient's address.
	 *
	 * @return bool True when the mail must not be sent, false when it may be sent.
	 */
	private static function maybe_throttle_mail( $bucket, $email ) {
		$allowance = Throttle::count( $bucket, \strtolower( $email ), 30, HOUR_IN_SECONDS );

		if ( $allowance['allowed'] ) {
			return false;
		}

		// Not when the mails are switched off with a limit of 0.
		if ( $allowance['exceeded'] && $allowance['limit'] > 0 ) {
			self::send_limit_notice( $email, $allowance['limit'], $allowance['reset'] );
		}

		return true;
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

	/**
	 * Maybe prevent email notifications for comments.
	 *
	 * This filter can prevent both post author and moderator notifications
	 * for comments on specific post types, such as ActivityPub custom post types.
	 *
	 * @param bool $maybe_notify Whether to send the notification.
	 * @param int  $comment_id   The comment ID.
	 *
	 * @return bool False to prevent notification, original value otherwise.
	 */
	public static function maybe_prevent_comment_notification( $maybe_notify, $comment_id ) {
		// If already disabled, respect that.
		if ( ! $maybe_notify ) {
			return $maybe_notify;
		}

		$comment = \get_comment( $comment_id );
		if ( ! $comment ) {
			return $maybe_notify;
		}

		$post = \get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return $maybe_notify;
		}

		// Prevent notifications for comments on ap_post.
		if ( is_ap_post( $post ) ) {
			return false;
		}

		return $maybe_notify;
	}

	/**
	 * Let the post author mute email about reactions to their post.
	 *
	 * Likes, reposts, and quotes are stored as comments, so WordPress emails the post author about
	 * them like any other comment. This is hooked on `notify_post_author` only, so it never affects
	 * the moderator notification, and it targets the plugin's own reaction comment types so pingbacks,
	 * trackbacks, and plain replies keep notifying as usual. The preference defaults to on.
	 *
	 * @since 9.3.0
	 *
	 * @param bool $maybe_notify Whether to send the notification.
	 * @param int  $comment_id   The comment ID.
	 *
	 * @return bool Whether to send the notification.
	 */
	public static function maybe_prevent_reaction_notification( $maybe_notify, $comment_id ) {
		// If already disabled, respect that.
		if ( ! $maybe_notify ) {
			return $maybe_notify;
		}

		$comment = \get_comment( $comment_id );
		if ( ! $comment || ! \in_array( \get_comment_type( $comment ), Comment::get_comment_type_slugs(), true ) ) {
			return $maybe_notify;
		}

		$post = \get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return $maybe_notify;
		}

		return (bool) \get_user_option( 'activitypub_mailer_new_reaction', (int) $post->post_author );
	}

	/**
	 * Leave out the addresses that had enough mails about replies and reactions from the Fediverse.
	 *
	 * WordPress mails the post author and the moderators about every comment, and replies, likes,
	 * reposts and quotes from the Fediverse are comments. Each address in a mail is counted once; a
	 * mail that is switched off does not reach this filter. The current user's own address is not
	 * counted: WordPress drops it afterwards, for example when a post author approves a reply.
	 *
	 * @since unreleased
	 *
	 * @param string[] $emails     The addresses the mail goes to.
	 * @param int      $comment_id The comment ID.
	 *
	 * @return string[] The addresses that may still get the mail.
	 */
	public static function throttle_comment_recipients( $emails, $comment_id ) {
		if ( ! Comment::was_received( $comment_id ) ) {
			return $emails;
		}

		$own = \wp_get_current_user()->user_email;

		return \array_filter(
			$emails,
			static function ( $email ) use ( $own ) {
				return $email === $own || ! self::maybe_throttle_mail( 'comment_mail', $email );
			}
		);
	}
}
