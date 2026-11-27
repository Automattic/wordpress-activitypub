<?php
/**
 * Base_Object schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

use Activitypub\Rest\Language_Map;

/**
 * The schema of an ActivityStreams object received from a remote server.
 *
 * A schema object only builds a JSON Schema; core's `rest_validate_value_from_schema()` does the
 * checking. Subclasses add their properties to their parent's, following the vocabulary, not the
 * model classes in `Activitypub\Activity`. Only keywords core supports may be used: core ignores
 * any other one without an error.
 *
 * Loose on purpose: properties that are not listed are allowed, servers send keys of their own.
 *
 * A schema object also normalizes what passed it (`sanitize()`), so every way in hands on the
 * same form: the inbox through the route args (`arg_options`), the Proxy before it caches.
 *
 * @since unreleased
 */
class Base_Object {
	use Language_Map;

	/**
	 * The schema, built once.
	 *
	 * @var array
	 */
	protected $schema;

	/**
	 * Get the JSON Schema, as `WP_REST_Controller::get_item_schema()` does: built once, cached on the instance.
	 *
	 * The required properties are listed on the schema, not flagged on each property. A route's
	 * args are fixed, the type is only known from the body: args built from this schema check the
	 * shape of each property that is sent, and the schema of the type decides which ones it needs.
	 *
	 * @return array The schema.
	 */
	public function get_schema() {
		if ( ! $this->schema ) {
			$properties = $this->get_properties();
			$required   = array();

			foreach ( $properties as $key => $property ) {
				if ( ! empty( $property['required'] ) ) {
					$required[] = $key;
				}

				unset( $properties[ $key ]['required'] );
			}

			$this->schema = array(
				'type'       => 'object',
				'properties' => $properties,
				'required'   => $required,
			);
		}

		return $this->schema;
	}

	/**
	 * Validate remote data: the schema first, then the rules between fields.
	 *
	 * @param mixed $data The remote data.
	 *
	 * @return true|\WP_Error True if valid, an error naming the property if not.
	 */
	public function validate( $data ) {
		$valid = \rest_validate_value_from_schema( $data, $this->get_schema(), 'data' );

		if ( true === $valid ) {
			$valid = $this->check( $data );
		}

		// Core's errors carry no status, which a REST response would turn into a 500.
		if ( \is_wp_error( $valid ) ) {
			$valid->add_data( array( 'status' => 400 ) );
		}

		return $valid;
	}

	/**
	 * Normalize remote data that passed validation: language maps resolve to the site's language,
	 * and each property gets the `sanitize_callback` of its `arg_options`, the one the inbox's route
	 * args use, so both ways in hand on the same form.
	 *
	 * @param array $data The remote data, valid against the schema.
	 *
	 * @return array The normalized data.
	 */
	public function sanitize( $data ) {
		$data = $this->localize_language_maps( $data );

		foreach ( $this->get_schema()['properties'] as $key => $property ) {
			if ( isset( $data[ $key ], $property['arg_options']['sanitize_callback'] ) ) {
				$data[ $key ] = \call_user_func( $property['arg_options']['sanitize_callback'], $data[ $key ] );
			}
		}

		return $data;
	}

	/**
	 * Normalize an audience to a list: handlers read `to`, `cc` and `bcc` as arrays.
	 *
	 * @param string|array $audience One URI, or a list of URIs.
	 *
	 * @return array The list of URIs.
	 */
	public function audience_to_list( $audience ) {
		return \is_string( $audience ) ? array( $audience ) : $audience;
	}

	/**
	 * The properties of the type. A subclass merges its own into its parent's.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		return array(
			'id'           => $this->uri(),
			'type'         => $this->required(
				array(
					'type'      => 'string',
					'minLength' => 1,
				)
			),
			'attributedTo' => $this->references(),
			'to'           => $this->audience(),
			'cc'           => $this->audience(),
		);
	}

	/**
	 * Rules between properties of the document that a schema can not express.
	 *
	 * Needs neither the signer nor stored data, so it runs wherever the document arrives, as part of
	 * `validate()`: in the inbox route's `validate_callback` and in the Proxy. Runs after the schema passed.
	 *
	 * @param array $data The remote data.
	 *
	 * @return true|\WP_Error True if valid, an error with status 400 if not.
	 */
	public function check( $data ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Subclasses use it.
		return true;
	}

	/**
	 * A URI. Core does not check `format: uri`, so it is a pattern: a scheme and a colon.
	 *
	 * @return array The schema.
	 */
	protected function uri() {
		return array(
			'type'    => 'string',
			'pattern' => '^[a-z][a-z0-9+.-]*:',
		);
	}

	/**
	 * A reference to an object or an actor, in the forms `object_to_id()` reads: its id, an object
	 * with its id, or a `Link` or `Mention` with the `href` it points to.
	 *
	 * A `Link` with an `id` but no `href` passes as an object; `object_to_id()` finds nothing in it.
	 * Core can not make `href` depend on the type, so a reference the plugin replaces by its id is
	 * checked in `check()` too.
	 *
	 * @return array The schema.
	 */
	protected function reference() {
		return array(
			'anyOf' => array(
				$this->uri(),
				array(
					'type'       => 'object',
					'properties' => array(
						'id'   => $this->required( $this->uri() ),
						'href' => $this->uri(),
					),
				),
				array(
					'type'       => 'object',
					'properties' => array(
						'type' => $this->required(
							array(
								'type' => 'string',
								'enum' => array( 'Link', 'Mention' ),
							)
						),
						'href' => $this->required( $this->uri() ),
					),
				),
			),
		);
	}

	/**
	 * One reference, or a list of them (PeerTube names the account and its channel in `attributedTo`).
	 *
	 * @return array The schema.
	 */
	protected function references() {
		$reference = $this->reference();

		$reference['anyOf'][] = array(
			'type'     => 'array',
			'minItems' => 1,
			'items'    => $this->reference(),
		);

		return $reference;
	}

	/**
	 * An audience (`to`, `cc`, `bcc`): one URI, or a list of URIs.
	 *
	 * @return array The schema.
	 */
	protected function audience() {
		return array(
			'anyOf'       => array(
				$this->uri(),
				array(
					'type'  => 'array',
					'items' => $this->uri(),
				),
			),
			'arg_options' => array( 'sanitize_callback' => array( $this, 'audience_to_list' ) ),
		);
	}

	/**
	 * Mark a property as required.
	 *
	 * @param array $schema The schema of the property.
	 *
	 * @return array The schema, required.
	 */
	protected function required( $schema ) {
		$schema['required'] = true;

		return $schema;
	}
}
