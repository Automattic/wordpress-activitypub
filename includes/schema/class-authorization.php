<?php
/**
 * Authorization schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of an authorization, the stamp that allows an interaction (FEP-044f, FEP-7aa9).
 *
 * @since unreleased
 */
abstract class Authorization extends Base_Object {
	/**
	 * The properties of an authorization: who issued it, and which object it allows on which target.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		return \array_merge(
			parent::get_properties(),
			array(
				'id'                => $this->required( $this->uri() ),
				'attributedTo'      => $this->required( $this->references() ),
				'interactingObject' => $this->required( $this->reference() ),
				'interactionTarget' => $this->required( $this->reference() ),
			)
		);
	}
}
