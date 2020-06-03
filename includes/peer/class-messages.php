<?php
namespace Activitypub\Peer;

/**
 * ActivityPub Messages DB-Class
 *
 * @author Django Doucet
 */
class Messages {

	public static function get_messages() {
		$args = array(
			'type'  => array('activitypub', 'activitypub_dm', 'activitypub_fo', 'activitypub_ul'),
		);
		$messages = get_comments( $args );

		if ( ! $messages ) {
			return array();
		}

		$current_user_id = get_current_user_id();
		$personal_messages = array();

		foreach ( $messages as $message ) :
		    $target_user = get_comment_meta( $message->comment_ID, 'target_user', true );
				if ( $current_user_id == $target_user ) {
					$personal_messages[] = $message;
				}
		endforeach;
		return $personal_messages;
	}

	public static function count_messages( ) {
		$messages = self::get_messages( );
		return \count( $messages );
	}

}
