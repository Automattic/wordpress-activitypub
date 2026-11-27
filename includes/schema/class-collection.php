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
	 * The items are one URI or a list: core reads any other scalar, an empty string included, as an
	 * empty list (`rest_is_array()`), which `to_list()` would turn into a list of one bogus item.
	 *
	 * @return true|\WP_Error True if valid, an error with status 400 if not.
	 */
	protected function check() {
		foreach ( array( 'items', 'orderedItems' ) as $key ) {
			if ( ! isset( $this->data[ $key ] ) || \is_array( $this->data[ $key ] ) ) {
				continue;
			}

			if ( ! \is_string( $this->data[ $key ] ) || true !== \rest_validate_value_from_schema( $this->data[ $key ], $this->uri(), $key ) ) {
				return new \WP_Error( 'activitypub_invalid_items', \__( 'The items of the collection are not a list.', 'activitypub' ), array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * The items of a collection: a list of URIs or objects, a single URI becomes one.
	 *
	 * Objects need no id: Mastodon lists its featured hashtags as objects without one.
	 *
	 * @return array The schema.
	 */
	protected function items() {
		$item = array(
			'anyOf' => array(
				$this->uri(),
				array( 'type' => 'object' ),
			),
		);

		return array(
			'anyOf'       => array(
				$this->uri(),
				array(
					'type'  => 'array',
					'items' => $item,
				),
			),
			'arg_options' => array( 'sanitize_callback' => array( $this, 'to_list' ) ),
		);
	}

	/**
	 * A page of a collection: a reference, or the page embedded without an id of its own.
	 *
	 * @return array The schema.
	 */
	protected function page() {
		$page = $this->reference();

		$page['anyOf'][] = array(
			'type'       => 'object',
			'properties' => array(
				'type' => $this->required( array( 'type' => 'string' ) ),
			),
		);

		return $page;
	}

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
				'items'        => $this->items(),
				'orderedItems' => $this->items(),
				'first'        => $this->page(),
				'last'         => $this->page(),
				'current'      => $this->page(),
				'next'         => $this->page(),
				'prev'         => $this->page(),
				'partOf'       => $this->reference(),
			)
		);
	}
}
