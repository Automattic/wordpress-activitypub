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
	 * The remote data the schema checks and normalizes.
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * Constructor.
	 *
	 * @param mixed $data Optional. The remote data. Default null, for the schema alone (route args).
	 */
	public function __construct( $data = null ) {
		$this->data = $data;
	}

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
	 * Validate the remote data: the schema first, then the rules between fields.
	 *
	 * @return true|\WP_Error True if valid, an error naming the property if not.
	 */
	public function validate() {
		$valid = \rest_validate_value_from_schema( $this->data, $this->get_schema(), 'data' );

		if ( true === $valid ) {
			$valid = $this->check();
		}

		// Core's errors carry no status, which a REST response would turn into a 500.
		if ( \is_wp_error( $valid ) ) {
			$valid->add_data( array( 'status' => 400 ) );
		}

		return $valid;
	}

	/**
	 * Normalize the remote data, once it passed `validate()`: language maps resolve to the site's
	 * language, and each property gets the `sanitize_callback` of its `arg_options`.
	 *
	 * @return mixed The normalized data.
	 */
	public function sanitize() {
		$data = $this->localize_language_maps( $this->data );

		if ( ! \is_array( $data ) ) {
			return $data;
		}

		foreach ( \array_keys( $this->get_schema()['properties'] ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = $this->sanitize_property( $key, $data[ $key ] );
			}
		}

		return $data;
	}

	/**
	 * Normalize one property with the `sanitize_callback` of its `arg_options`.
	 *
	 * @param string $key   The property.
	 * @param mixed  $value The value.
	 *
	 * @return mixed The normalized value, or the value if the property has no callback.
	 */
	public function sanitize_property( $key, $value ) {
		$callback = $this->get_schema()['properties'][ $key ]['arg_options']['sanitize_callback'] ?? null;

		return $callback ? \call_user_func( $callback, $value ) : $value;
	}

	/**
	 * The `sanitize_callback` of a route arg: the property is normalized by the schema of the
	 * request's type, so a type normalizes only its own properties.
	 *
	 * Core runs it after the args and the route's `validate_callback` checked the request. A request
	 * without a type of this kind did not get that far; should it still, the arg's own schema normalizes.
	 *
	 * The schema is selected from the raw body: core normalizes the params one by one, in place, so a
	 * selection from them would depend on the order of the properties.
	 *
	 * @param mixed            $value   The value.
	 * @param \WP_REST_Request $request The request.
	 * @param string           $key     The property.
	 *
	 * @return mixed The normalized value.
	 */
	public function sanitize_arg( $value, $request, $key ) {
		$schema = Registry::get( \json_decode( $request->get_body(), true ) )->limit( static::class );

		return ( $schema instanceof Unexpected ? $this : $schema )->sanitize_property( $key, $value );
	}

	/**
	 * Limit the schema to a kind: the schema itself if it is one, else one that refuses the data.
	 *
	 * @param string $kind The class the schema has to be, such as `Activity::class`.
	 *
	 * @return Base_Object The schema.
	 */
	public function limit( $kind ) {
		return $this instanceof $kind ? $this : new Unexpected( $this->data );
	}

	/**
	 * Normalize a value to a list: an audience (`to`, `cc`, `bcc`) or the items of a collection.
	 *
	 * Core takes a single value for an array (`rest_is_array()`), the code that reads it expects a list.
	 *
	 * @param mixed $value One value, or a list of them.
	 *
	 * @return array The list.
	 */
	public function to_list( $value ) {
		return \is_scalar( $value ) ? array( $value ) : $value;
	}

	/**
	 * The properties of the type. A subclass merges its own into its parent's.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		$properties = array(
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

		// The texts `sanitize()` localizes: a string, or null (Mastodon sends `"summary": null`), and a map of strings.
		foreach ( array( 'name', 'summary', 'content' ) as $key ) {
			$properties[ $key ]         = array( 'type' => array( 'string', 'null' ) );
			$properties[ $key . 'Map' ] = array(
				'type'                 => array( 'object', 'null' ),
				'additionalProperties' => array( 'type' => 'string' ),
			);
		}

		return $properties;
	}

	/**
	 * Rules between properties of the document that a schema can not express.
	 *
	 * Needs neither the signer nor stored data, so it runs wherever the document arrives, as part of
	 * `validate()`: in the inbox route's `validate_callback` and in the Proxy. Runs after the schema passed.
	 *
	 * @return true|\WP_Error True if valid, an error with status 400 if not.
	 */
	protected function check() {
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
			'arg_options' => array( 'sanitize_callback' => array( $this, 'to_list' ) ),
		);
	}

	/**
	 * Allow null as well: servers send `"inReplyTo": null` for a post that does not reply.
	 *
	 * Adds to the alternatives of the schema, as core needs a type on each of them.
	 *
	 * @param array $schema The schema of the property, with `anyOf`.
	 *
	 * @return array The schema, or null.
	 */
	protected function nullable( $schema ) {
		$schema['anyOf'][] = array( 'type' => 'null' );

		return $schema;
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
