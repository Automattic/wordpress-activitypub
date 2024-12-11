<?php
/**
 * Notification file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Notification class.
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
		 * @param Notification $this The notification object.
		 */
		do_action( 'activitypub_notification', $this );

		/**
		 * Type-specific action to send ActivityPub notifications.
		 *
		 * @param Notification $this The notification object.
		 */
		do_action( "activitypub_notification_{$type}", $this );
	}
}
