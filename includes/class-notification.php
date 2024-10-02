<?php

namespace Activitypub;

use function Activitypub\get_remote_metadata_by_actor;

/**
 * ActivityPub Notification Class
 */
class Notification {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action(
			'activitypub_notification_create',
			array( self::class, 'mail' )
		);
	}

	public static function mail( $notification ) {
		$actor  = get_remote_metadata_by_actor( $notification->actor );
		$object = $notification->object['object'];
		$target = \get_user_by( 'id', $notification->target );

		if ( ! $actor || \is_wp_error( $actor ) || ! $target ) {
			return;
		}

		$subject = \sprintf(
			/* translators: 1: actor name, 2: object name */
			\__( 'A DM from %1$s', 'activitypub' ),
			$actor['name'],
		);

		$message = \sprintf(
			/* translators: 1: actor name, 2: object name, 3: object URL */
			\__( "%1\$s: \n\n %2\$s \n\n %3\$s", 'activitypub' ),
			$actor['name'],
			$object['content'],
			$object['url']
		);

		\wp_mail( $target->user_email, $subject, $message );
	}
}
