<?php
/**
 * Schema registry class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

use function Activitypub\camel_to_snake_case;
use function Activitypub\is_activity;
use function Activitypub\is_activity_object;
use function Activitypub\is_actor;
use function Activitypub\is_collection;

/**
 * Finds the schema object for remote data: the one of its type, else the one of its kind. A type
 * of no kind the plugin knows gets `Unexpected`, which refuses it.
 *
 * Example usage:
 *
 * $valid = Registry::get( $document )->validate();                           // Any kind, as the Proxy fetches.
 * $valid = Registry::get( $activity )->limit( Activity::class )->validate(); // Only activities, as an inbox receives.
 *
 * @since unreleased
 */
class Registry {
	/**
	 * The classes no type of the vocabulary is called by: the root, the kinds the registry falls
	 * back to and the groups. A document can not name them.
	 *
	 * @var string[]
	 */
	const NOT_TYPES = array(
		Base_Object::class,
		Activity_Object::class,
		Actor::class,
		Authorization::class,
		Request::class,
		Unexpected::class,
	);
	/**
	 * Get the schema object for remote data, holding the data.
	 *
	 * @param mixed $data The remote data.
	 *
	 * @return Base_Object The schema object.
	 */
	public static function get( $data ) {
		$type = \is_array( $data ) && isset( $data['type'] ) && \is_string( $data['type'] ) ? $data['type'] : '';

		/**
		 * Filters the schema object for remote data.
		 *
		 * Return a schema object for a type of your own, or to change the rules of a type, built
		 * with the data. A type of your own also has to be in the list of its kind,
		 * `activitypub_activity_types` for an activity, so the inbox builds its route args from
		 * your schema too.
		 *
		 * Example usage:
		 *
		 * add_filter( 'activitypub_schema', function( $schema, $data, $type ) {
		 *     return 'Offer' === $type ? new My_Offer_Schema( $data ) : $schema;
		 * }, 10, 3 );
		 *
		 * @param Base_Object|null $schema The schema object. Default null, the plugin's own.
		 * @param mixed            $data   The remote data.
		 * @param string           $type   The type of the remote data.
		 */
		$schema = \apply_filters( 'activitypub_schema', null, $data, $type );

		if ( $schema instanceof Base_Object ) {
			return $schema;
		}

		/*
		 * The schema of the type: `QuoteRequest` is `Quote_Request`. Letters only, so a type can not
		 * name another class, and only a class of a type, not one of `NOT_TYPES`.
		 */
		if ( \preg_match( '/^[A-Za-z]+$/', $type ) ) {
			$class = __NAMESPACE__ . '\\' . \ucwords( camel_to_snake_case( $type ), '_' );

			if ( \class_exists( $class ) && \is_subclass_of( $class, Base_Object::class ) && ! \in_array( $class, self::NOT_TYPES, true ) ) {
				return new $class( $data );
			}
		}

		// No schema of its own: the one of its kind.
		if ( is_activity( $data ) ) {
			return new Activity( $data );
		}

		if ( is_actor( $data ) ) {
			return new Actor( $data );
		}

		if ( is_collection( $data ) ) {
			return new Collection( $data );
		}

		if ( is_activity_object( $data ) ) {
			return new Activity_Object( $data );
		}

		// A key served on its own often has no type: the only kind known by its shape, see `Remote_Actors::get_public_key()`.
		if ( '' === $type && isset( $data['publicKeyPem'] ) ) {
			return new Key( $data );
		}

		return new Unexpected( $data );
	}
}
