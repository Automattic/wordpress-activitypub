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
	 * An actor needs somewhere to deliver to: its inbox or a shared inbox. A schema can only require
	 * each of them on its own.
	 *
	 * @return true|\WP_Error True if valid, an error with status 400 if not.
	 */
	protected function check() {
		if ( isset( $this->data['inbox'] ) || isset( $this->data['endpoints']['sharedInbox'] ) ) {
			return true;
		}

		return new \WP_Error( 'activitypub_actor_without_inbox', \__( 'The actor has no inbox.', 'activitypub' ), array( 'status' => 400 ) );
	}

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
				'inbox'     => $this->uri(),
				'outbox'    => $this->uri(),
				'followers' => $this->uri(),
				'following' => $this->uri(),
				'endpoints' => array(
					'type'       => 'object',
					'properties' => array(
						'sharedInbox' => $this->uri(),
					),
				),
				// The key embedded, or its URI, which `Remote_Actors::get_public_key()` fetches.
				'publicKey' => array(
					'anyOf' => array(
						$this->uri(),
						( new Key() )->get_schema(),
					),
				),
			)
		);
	}
}
