<?php
/**
 * Intransitive_Activity schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of an activity without an object, such as Arrive or Travel.
 *
 * @since unreleased
 */
class Intransitive_Activity extends Activity {
	/**
	 * The properties of an intransitive activity: those of an activity, without `object`.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		$properties = parent::get_properties();

		unset( $properties['object'] );

		return $properties;
	}
}
