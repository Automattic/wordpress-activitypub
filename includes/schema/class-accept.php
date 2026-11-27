<?php
/**
 * Accept schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of an Accept, of a Follow or a request.
 *
 * @since unreleased
 */
class Accept extends Activity {
	/**
	 * The properties of an Accept: the activity it answers is embedded, so the handler can match it.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		$properties = parent::get_properties();

		$properties['object'] = $this->activity( $properties['object'] );

		return $properties;
	}
}
