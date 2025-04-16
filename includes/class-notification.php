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
 * @deprecated unreleased.
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
	 * @deprecated unreleased.
	 *
	 * @param string $type     The type of the notification.
	 * @param string $actor    The actor URL.
	 * @param array  $activity The Activity object.
	 * @param int    $target   The WordPress User-Id.
	 */
	public function __construct( $type, $actor, $activity, $target ) {
		_deprecated_class( __CLASS__, 'unreleased' );

		$this->type   = $type;
		$this->actor  = $actor;
		$this->object = $activity;
		$this->target = $target;
	}

	/**
	 * Send the notification.
	 *
	 * @deprecated unreleased.
	 */
	public function send() {
		_deprecated_function( __METHOD__, 'unreleased' );

		$type = \strtolower( $this->type );

		/**
		 * Action to send ActivityPub notifications.
		 *
		 * @param Notification $instance The notification object.
		 */
		do_action_deprecated( 'activitypub_notification', array( $this ), 'unreleased', 'activitypub_inbox' );

		/**
		 * Type-specific action to send ActivityPub notifications.
		 *
		 * @param Notification $instance The notification object.
		 */
		do_action_deprecated( "activitypub_notification_{$type}", array( $this ), 'unreleased', 'activitypub_inbox_' . $type );
	}
}
