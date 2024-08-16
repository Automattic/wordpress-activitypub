<?php
namespace Activitypub\Integration;

/**
 * Stream Connector for ActivityPub
 *
 * This class is a Stream Connector for the Stream plugin.
 *
 * @see https://wordpress.org/plugins/stream/
 */
class Stream_Connector extends \WP_Stream\Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'activitypub';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'activitypub_notification_follow',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'ActivityPub', 'activitypub' );
	}

	/**
	 * Return translated context labels
	 *
	 * @return array
	 */
	public function get_context_labels() {
		return array();
	}

	/**
	 * Return translated action labels
	 *
	 * @return array
	 */
	public function get_action_labels() {
		return array();
	}

	/**
	 * Callback for activitypub_notification_follow
	 *
	 * @param \Activitypub\Notification $notification The notification object
	 *
	 * @return void
	 */
	public function callback_activitypub_notification_follow( $notification ) {
		$this->log(
			sprintf(
				// translators: %s is a URL
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
}
