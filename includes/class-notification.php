<?php

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
	 * @param string $type   The type of the notification.
	 * @param string $actor  The actor URL.
	 * @param array  $object The Activity object.
	 * @param int    $target The WordPress User-Id.
	 */
	public function __construct( $type, $actor, $object, $target ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound
		$this->type = $type;
		$this->actor = $actor;
		$this->object = $object;
		$this->target = $target;
	}

	/**
	 * Send the notification.
	 */
	public function send() {
		$type = \strtolower( $this->type );

		do_action( 'activitypub_notification', $this );
		do_action( "activitypub_notification_{$type}", $this );
	}
}
