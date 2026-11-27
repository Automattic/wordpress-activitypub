<?php
/**
 * Activity schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

use function Activitypub\object_to_id;

/**
 * The schema of an activity received from a remote server.
 *
 * @since unreleased
 */
class Activity extends Base_Object {
	/**
	 * Normalize an activity: language maps resolved (the embedded object's too), the actor as its id.
	 *
	 * The same the inbox's route args do per property, for the ways in without args (the Proxy).
	 *
	 * @param array $data The activity, valid against the schema.
	 *
	 * @return array The normalized activity.
	 */
	public function sanitize( $data ) {
		$data          = parent::sanitize( $data );
		$data['actor'] = object_to_id( $data['actor'] );

		return $data;
	}

	/**
	 * The properties of an activity.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		return \array_merge(
			parent::get_properties(),
			array(
				'id'         => $this->required( $this->uri() ),
				// Letters only: the type names the dynamic hooks, `activitypub_inbox_{type}`.
				'type'       => $this->required(
					array(
						'type'    => 'string',
						'pattern' => '^[A-Za-z]+$',
					)
				),
				// Handlers read the actor as its id, see `object_to_id()`. Core checks the schema first.
				'actor'      => \array_merge(
					$this->required( $this->reference() ),
					array( 'arg_options' => array( 'sanitize_callback' => '\Activitypub\object_to_id' ) )
				),
				// An id, an object with its id, or a list of them: Follow, Create, Undo, Flag alike.
				'object'     => \array_merge(
					$this->required( $this->references() ),
					array( 'arg_options' => array( 'sanitize_callback' => array( $this, 'localize_language_maps' ) ) )
				),
				'target'     => $this->references(),
				'origin'     => $this->references(),
				'instrument' => $this->references(),
				'bcc'        => $this->audience(),
			)
		);
	}
}
