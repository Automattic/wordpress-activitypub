<?php
/**
 * Collection schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of a collection or a page of one, ordered or not.
 *
 * @since unreleased
 */
class Collection extends Base_Object {
	/**
	 * The properties of a collection: its size, its items and the links to its pages.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		return \array_merge(
			parent::get_properties(),
			array(
				'totalItems'   => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'items'        => array( 'type' => 'array' ),
				'orderedItems' => array( 'type' => 'array' ),
				'first'        => $this->reference(),
				'last'         => $this->reference(),
				'current'      => $this->reference(),
				'next'         => $this->reference(),
				'prev'         => $this->reference(),
				'partOf'       => $this->reference(),
			)
		);
	}
}
