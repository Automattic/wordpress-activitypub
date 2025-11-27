<?php
/**
 * Tag Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

/**
 * Tag Transformer Class.
 */
class Tag extends Base {
	/**
	 * Transforms the WP_Term object to an Actor.
	 *
	 * @see \Activitypub\Activity\Actor
	 *
	 * @return \Activitypub\Activity\Base_Object|\WP_Error The Actor or WP_Error on failure.
	 */
	public function to_object() {
		$base_object               = new \Activitypub\Activity\Base_Object();
		$base_object->{'@context'} = 'https://www.w3.org/ns/activitystreams';
		$base_object->set_type( 'OrderedCollection' );
		$base_object->set_id( \get_term_link( $this->item ) );

		return $base_object;
	}

	/**
	 * Get the OrderedCollection ID (term link).
	 *
	 * @return string The OrderedCollection ID (term link).
	 */
	public function to_id() {
		return \get_term_link( $this->item );
	}
}
