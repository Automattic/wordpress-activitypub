<?php
/**
 * Object Title Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Base_Object;

/**
 * Object Title Trait.
 *
 * Derives a human-readable title from an activity's object, used by the Inbox and
 * Outbox collections when labelling stored activities.
 */
trait With_Object_Title {

	/**
	 * Get the title of an activity recursively.
	 *
	 * @param Activity|Base_Object|array $activity_object The activity object.
	 *
	 * @return string The title.
	 */
	private static function get_object_title( $activity_object ) {
		// Guard against arrays for every caller (Outbox previously relied on only ever passing an object).
		if ( ! $activity_object || \is_array( $activity_object ) ) {
			return '';
		}

		if ( \is_string( $activity_object ) ) {
			$post_id = \url_to_postid( $activity_object );

			return $post_id ? \get_the_title( $post_id ) : '';
		}

		$title = $activity_object->get_name() ?: $activity_object->get_content();

		if ( ! $title && $activity_object->get_object() instanceof Base_Object ) {
			$title = $activity_object->get_object()->get_name() ?: $activity_object->get_object()->get_content();
		}

		return $title;
	}
}
