<?php
/**
 * Actor schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of an actor received from a remote server.
 *
 * @since unreleased
 */
class Actor extends Base_Object {
	/**
	 * The properties of an actor.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		return \array_merge(
			parent::get_properties(),
			array(
				'id'        => $this->required( $this->uri() ),
				'inbox'     => $this->required( $this->uri() ),
				'outbox'    => $this->uri(),
				'followers' => $this->uri(),
				'following' => $this->uri(),
				'endpoints' => array(
					'type'       => 'object',
					'properties' => array(
						'sharedInbox' => $this->uri(),
					),
				),
				'publicKey' => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => $this->required( $this->uri() ),
						'owner'        => $this->uri(),
						'publicKeyPem' => $this->required(
							array(
								'type'      => 'string',
								'minLength' => 1,
							)
						),
					),
				),
			)
		);
	}
}
