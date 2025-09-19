<?php
/**
 * Audience Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;

use function Activitypub\extract_recipients_from_activity;
use function Activitypub\extract_recipients_from_activity_property;
use function Activitypub\is_same_domain;
use function Activitypub\user_can_activitypub;

/**
 * Audience Trait.
 *
 * Provides methods for handling ActivityPub audience and recipient extraction.
 */
trait Audience {
	/**
	 * Extract recipients from the given Activity.
	 *
	 * @param array $activity The activity data.
	 *
	 * @return array An array of user IDs who are the recipients of the activity.
	 */
	public function determine_recipients( $activity ) {
		$recipients = extract_recipients_from_activity( $activity );
		$user_ids   = array();

		foreach ( $recipients as $recipient ) {

			if ( ! is_same_domain( $recipient ) ) {
				continue;
			}

			$user_id = Actors::get_id_by_resource( $recipient );

			if ( \is_wp_error( $user_id ) ) {
				continue;
			}

			if ( ! user_can_activitypub( $user_id ) ) {
				continue;
			}

			$user_ids[] = $user_id;
		}

		return $user_ids;
	}

	public function determine_visibility( $activity ) {
		$recipients = extract_recipients_from_activity_property( 'to', $activity );
		$visibility = 'private';

		foreach ( $recipients as $recipient ) {
			if ( is_same_domain( $recipient ) ) {
				$visibility = 'direct';
				break;
			}

			if ( \in_array( $recipient, array( 'https://www.w3.org/ns/activitystreams#Public', 'as:Public' ), true ) ) {
				$visibility = 'public';
				break;
			}
		}

		return $visibility;
	}
}
