<?php
/**
 * Key schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of a public key, served on its own or embedded in an actor.
 *
 * @since unreleased
 */
class Key extends Base_Object {
	/**
	 * The properties of a key: its id, its owner and the key itself.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		$properties = \array_merge(
			parent::get_properties(),
			array(
				'id'           => $this->required( $this->uri() ),
				'owner'        => $this->uri(),
				'publicKeyPem' => $this->required(
					array(
						'type'      => 'string',
						'minLength' => 1,
					)
				),
			)
		);

		// An embedded key often has no type.
		unset( $properties['type'] );

		return $properties;
	}
}
