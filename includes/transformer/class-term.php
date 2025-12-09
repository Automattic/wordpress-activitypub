<?php
/**
 * Term Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

/**
 * Term Transformer Class.
 */
class Term extends Base {
	/**
	 * Transforms the WP_Term object to an OrderedCollection.
	 *
	 * @see \Activitypub\Activity\Base_Object
	 *
	 * @return \Activitypub\Activity\Base_Object|\WP_Error The OrderedCollection or WP_Error on failure.
	 */
	public function to_object() {
		$base_object               = new \Activitypub\Activity\Base_Object();
		$base_object->{'@context'} = 'https://www.w3.org/ns/activitystreams';
		$base_object->set_type( 'OrderedCollection' );
		$base_object->set_id( $this->get_id() );
		$base_object->set_url( $this->get_url() );

		return $base_object;
	}

	/**
	 * Get the OrderedCollection ID.
	 *
	 * @return string The OrderedCollection ID.
	 */
	public function to_id() {
		return $this->get_id();
	}

	/**
	 * Returns the stable ID of the Term.
	 *
	 * Uses term_id query parameter to ensure the ID remains stable
	 * even if the term slug is changed.
	 *
	 * @return string The Term's stable ID.
	 */
	public function get_id() {
		return \add_query_arg( 'term_id', $this->item->term_id, \home_url( '/' ) );
	}

	/**
	 * Returns the URL of the Term.
	 *
	 * @return string The Term's URL (term link).
	 */
	public function get_url() {
		$term_link = \get_term_link( $this->item );

		if ( \is_wp_error( $term_link ) ) {
			return '';
		}

		return \esc_url( $term_link );
	}
}
