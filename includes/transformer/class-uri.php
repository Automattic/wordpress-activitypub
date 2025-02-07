<?php
/**
 * URI Transformer Class.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

/**
 * URI Transformer Class.
 *
 * @package Activitypub
 */
class Uri extends Base {
	/**
	 * Transform the item into an ActivityPub Object.
	 *
	 * @return string The URI.
	 */
	public function to_object() {
		return $this->item;
	}

	/**
	 * Get the ID of the item.
	 *
	 * @return string The ID of the item.
	 */
	public function to_id() {
		return $this->item;
	}
}
