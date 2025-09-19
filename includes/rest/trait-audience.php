<?php
/**
 * Audience Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\extract_recipients_from_activity_property;

/**
 * Audience Trait.
 *
 * Provides methods for handling ActivityPub audience and recipient extraction.
 */
trait Audience {
	/**
	 * Determine the visibility of the activity based on its recipients.
	 *
	 * @param array $activity The activity data.
	 *
	 * @return string The visibility level: 'public', 'private', or 'direct'.
	 */
	public function determine_visibility( $activity ) {
		// Set default visibility for specific activity types.
		if ( in_array( $activity['type'], array( 'Accept', 'Delete', 'Follow', 'Reject', 'Undo' ), true ) ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
		}

		// Check 'to' field for public visibility.
		$to = extract_recipients_from_activity_property( 'to', $activity );
		if ( ! empty( array_intersect( $to, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS ) ) ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
		}

		// Check 'cc' field for quiet public visibility.
		$cc = extract_recipients_from_activity_property( 'cc', $activity );
		if ( ! empty( array_intersect( $cc, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS ) ) ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC;
		}

		return ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
	}
}
