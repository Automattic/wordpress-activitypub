<?php
namespace Activitypub\Integration;

class Jetpack {

	public static function init() {
		\add_action( 'activitypub_notification', [ self::class, 'send' ] );
	}

	public static function send( $notification ) {
		\Automattic\Jetpack\Connection\Client::wpcom_json_api_request_as_user(
			sprintf( '/sites/%d/activitypub/notify', \Jetpack_Options::get_option( 'id' ) ),
			'2',
			[ 'method' => 'POST' ],
			[
				'actor'  => $notification->actor,
				'object' => $notification->object,
				'target' => $notification->target,
				'type'   => $notification->type,
			],
			'wpcom'
		);
	}
}