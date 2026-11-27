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
	 * The actor is replaced by its id (`arg_options`), so its id has to be a URI: for a `Link` or
	 * `Mention` that is the `href`, which the schema can not require by type.
	 *
	 * @return true|\WP_Error True if valid, an error with status 400 if not.
	 */
	protected function check() {
		$valid = \rest_validate_value_from_schema( object_to_id( $this->data['actor'] ), $this->uri(), 'actor' );

		if ( \is_wp_error( $valid ) ) {
			$valid->add_data( array( 'status' => 400 ) );
		}

		return $valid;
	}

	/**
	 * An embedded activity, as the object of an answer (`Accept`, `Reject`): its id, type, actor and object.
	 *
	 * @param array $property The property it narrows, whose `arg_options` it keeps.
	 *
	 * @return array The schema.
	 */
	protected function activity( $property ) {
		return $this->required(
			array(
				'type'        => 'object',
				'properties'  => array(
					'id'     => $this->required( $this->uri() ),
					'type'   => $this->required( array( 'type' => 'string' ) ),
					'actor'  => $this->required( $this->reference() ),
					'object' => $this->required( $this->references() ),
				),
				'arg_options' => $property['arg_options'],
			)
		);
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
