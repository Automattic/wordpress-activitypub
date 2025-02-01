<?php
/**
 * Stream Connector integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Actors;
use function Activitypub\url_to_authorid;
use function Activitypub\url_to_commentid;

/**
 * Stream Connector for ActivityPub.
 *
 * This class is a Stream Connector for the Stream plugin.
 *
 * @see https://wordpress.org/plugins/stream/
 */
class Stream_Connector extends \WP_Stream\Connector {
	/**
	 * Connector slug.
	 *
	 * @var string
	 */
	public $name = 'activitypub';

	/**
	 * Actions registered for this connector.
	 *
	 * @var array
	 */
	public $actions = array(
		'activitypub_notification_follow',
		'activitypub_sent_to_followers',
	);

	/**
	 * Return translated connector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'ActivityPub', 'activitypub' );
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array
	 */
	public function get_context_labels() {
		return array();
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array
	 */
	public function get_action_labels() {
		return array(
			'processed' => __( 'Processed', 'activitypub' ),
		);
	}

	/**
	 * Callback for activitypub_notification_follow.
	 *
	 * @param \Activitypub\Notification $notification The notification object.
	 */
	public function callback_activitypub_notification_follow( $notification ) {
		$this->log(
			sprintf(
				// translators: %s is a URL.
				__( 'New Follower: %s', 'activitypub' ),
				$notification->actor
			),
			array(
				'notification' => \wp_json_encode( $notification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			),
			null,
			'notification',
			$notification->type,
			$notification->target
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links   Previous links registered.
	 * @param Record $record  Stream record.
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		if ( 'processed' === $record->action ) {
			$errors = json_decode( $record->get_meta( 'errors', true ), true );

			if ( $errors ) {
				$errors = array_map(
					function ( $inbox, $result ) {
						return sprintf( '%1$s: %2$s', $inbox, $result );
					},
					array_keys( $errors ),
					$errors
				);

				$message = sprintf(
					'<details><summary>%1$s</summary><pre>%2$s</pre></details>',
					__( 'Inbox Errors', 'activitypub' ),
					implode( "\n", $errors )
				);

				$links[ $message ] = '';
			}
		}

		return $links;
	}

	/**
	 * Callback for activitypub_send_to_inboxes.
	 *
	 * @param array                          $results     The results of the remote posts.
	 * @param \ActivityPub\Activity\Activity $activity    The ActivityPub Activity.
	 * @param \WP_Post                       $outbox_item The WordPress object.
	 */
	public function callback_activitypub_sent_to_followers( $results, $activity, $outbox_item ) {
		$object_id    = $outbox_item->ID;
		$object_type  = $outbox_item->post_type;
		$object_title = $outbox_item->post_title;

		$post_id = url_to_postid( $outbox_item->post_title );
		if ( $post_id ) {
			$post = get_post( $post_id );

			$object_id    = $post_id;
			$object_type  = $post->post_type;
			$object_title = $post->post_title;
		} else {
			$comment_id = url_to_commentid( $outbox_item->post_title );
			if ( $comment_id ) {
				$comment = get_comment( $comment_id );

				$object_id    = $comment_id;
				$object_type  = 'comments';
				$object_title = $comment->comment_content;
			} else {
				$author_id = url_to_authorid( $outbox_item->post_title );
				if ( null !== $author_id ) {
					$object_id   = $author_id;
					$object_type = 'profiles';

					if ( $author_id ) {
						$object_title = get_userdata( $author_id )->display_name;
					} elseif ( Actors::BLOG_USER_ID === $author_id ) {
						$object_title = __( 'Blog User', 'activitypub' );
					} elseif ( Actors::APPLICATION_USER_ID === $author_id ) {
						$object_title = __( 'Application User', 'activitypub' );
					}
				}
			}
		}

		$errors = array();
		foreach ( $results as $inbox => $result ) {
			if ( is_wp_error( $result ) ) {
				$errors[ $inbox ] = $result->get_error_message();
				continue;
			}
			if ( wp_remote_retrieve_response_code( $result ) >= 300 ) {
				$errors[ $inbox ] = wp_remote_retrieve_response_message( $result );
			}
		}

		$this->log(
			// translators: 1: post title.
			sprintf( __( 'Outbox processed for "%1$s"', 'activitypub' ), $object_title ),
			array(
				'errors' => wp_json_encode( $errors ),
			),
			$object_id,
			$object_type,
			'processed'
		);
	}
}
