<?php
/**
 * Stream Connector integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

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
		'activitypub_send_to_inboxes',
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
			$inboxes = $record->get_meta( 'inboxes', true );
			if ( empty( $inboxes ) ) {
				$inboxes = __( 'No inboxes to notify about this activity.', 'activitypub' );
			} else {
				$inboxes = implode( "\n", $inboxes );
			}

			$message = sprintf(
				'<details><summary>%1$s</summary><pre>%2$s</pre></details>',
				__( 'Notified Inboxes', 'activitypub' ),
				$inboxes
			);

			$links[ $message ] = '';
		}

		return $links;
	}

	/**
	 * Callback for activitypub_send_to_inboxes.
	 *
	 * @param array                          $inboxes     The list of inboxes to send to.
	 * @param int                            $actor_id    The actor ID.
	 * @param \Activitypub\Activity\Activity $activity    The ActivityPub Activity.
	 * @param \WP_Post                       $outbox_item The WordPress object.
	 */
	public function callback_activitypub_send_to_inboxes( $inboxes, $actor_id, $activity, $outbox_item ) {
		static $initial_run = true;

		// Jump back in priority to catch modified inboxes.
		if ( $initial_run ) {
			add_action( 'activitypub_send_to_inboxes', array( $this, __FUNCTION__ ), 99, 4 );
			$initial_run = false;
			return $inboxes;
		}

		$object_id    = $outbox_item->ID;
		$object_type  = $outbox_item->post_type;
		$object_title = $outbox_item->post_title;

		$post_id = url_to_postid( $outbox_item->post_title );
		if ( $post_id ) {
			$post = get_post( $post_id );

			$object_id    = $post_id;
			$object_type  = $post->post_type;
			$object_title = $post->post_title;
		}

		$comment_id = url_to_commentid( $outbox_item->post_title );
		if ( $comment_id ) {
			$comment = get_comment( $comment_id );

			$object_id    = $comment_id;
			$object_type  = $comment->comment_type;
			$object_title = $comment->comment_content;
		}

		$this->log(
			// translators: 1: post title.
			sprintf( __( 'Outbox processed for "%1$s"', 'activitypub' ), $object_title ),
			array(
				'inboxes' => $inboxes,
			),
			$object_id,
			$object_type,
			'processed'
		);

		// We're in a filter, so we need to return the inboxes.
		return $inboxes;
	}
}
