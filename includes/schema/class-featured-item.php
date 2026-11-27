<?php
/**
 * Featured_Item schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of an item of a featured collection.
 *
 * @since unreleased
 */
class Featured_Item extends Base_Object {
	/**
	 * The properties of a featured item: the object it features, which `object_to_uri()` reads.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		return \array_merge(
			parent::get_properties(),
			array(
				'featuredObject' => $this->required( $this->reference() ),
			)
		);
	}
}
