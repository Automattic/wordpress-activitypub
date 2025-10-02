<?php
/**
 * Notification file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Notification class.
 *
 * @deprecated 7.5.0 Use action hooks like 'activitypub_handled_{type}' instead.
 */
class Notification {
	/**
	 * The type of the notification.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The actor URL.
	 *
	 * @var string
	 */
	public $actor;

	/**
	 * The Activity object.
	 *
	 * @var array
	 */
	public $object;

	/**
	 * The WordPress User-Id.
	 *
	 * @var int
	 */
	public $target;

	/**
	 * Notification constructor.
	 *
	 * @param string $type     The type of the notification.
	 * @param string $actor    The actor URL.
	 * @param array  $activity The Activity object.
	 * @param int    $target   The WordPress User-Id.
	 */
	public function __construct( $type, $actor, $activity, $target ) {
		\_deprecated_class( __CLASS__, '7.5.0', 'Use action hooks like "activitypub_handled_{type}" instead.' );

		$this->type   = $type;
		$this->actor  = $actor;
		$this->object = $activity;
		$this->target = $target;
	}

	/**
	 * Send the notification.
	 */
	public function send() {
		$type = \strtolower( $this->type );

		/**
		 * Action to send ActivityPub notifications.
		 *
		 * @deprecated 7.5.0 Use "activitypub_handled_{$type}" instead.
		 *
		 * @param Notification $instance The notification object.
		 */
		\do_action_deprecated( 'activitypub_notification', array( $this ), '7.5.0', "activitypub_handled_{$type}" );

		/**
		 * Type-specific action to send ActivityPub notifications.
		 *
		 * @deprecated 7.5.0 Use "activitypub_handled_{$type}" instead.
		 *
		 * @param Notification $instance The notification object.
		 */
		\do_action_deprecated( "activitypub_notification_{$type}", array( $this ), '7.5.0', "activitypub_handled_{$type}" );
	}
}
