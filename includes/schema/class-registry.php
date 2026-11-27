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
 * Finds the schema object for remote data: the one of its type, else the one of its kind.
 * A type of no kind the plugin knows is not parsed.
 *
 * @since unreleased
 */
class Registry {
	/**
	 * Get the schema object for remote data.
	 *
	 * @param mixed $data The remote data.
	 *
	 * @return Base_Object|\WP_Error The schema object, an error with status 400 for a type of no known kind.
	 */
	public static function get( $data ) {
		$type = \is_array( $data ) && isset( $data['type'] ) && \is_string( $data['type'] ) ? $data['type'] : '';

		/**
		 * Filters the schema object for remote data.
		 *
		 * Return a schema object for a type of your own, or to change the rules of a type. A type
		 * of your own also has to be in the list of its kind, `activitypub_activity_types` for an
		 * activity, so the inbox builds its route args from your schema too.
		 *
		 * Example usage:
		 *
		 * add_filter( 'activitypub_schema', function( $schema, $data, $type ) {
		 *     return 'Offer' === $type ? new My_Offer_Schema() : $schema;
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

		// The schema of the type: `QuoteRequest` is `Quote_Request`. Letters only, so a type can not name another class.
		if ( \preg_match( '/^[A-Za-z]+$/', $type ) ) {
			$class = __NAMESPACE__ . '\\' . \ucwords( camel_to_snake_case( $type ), '_' );

			if ( \class_exists( $class ) && \is_a( $class, Base_Object::class, true ) ) {
				return new $class();
			}
		}

		// No schema of its own: the one of its kind.
		if ( is_activity( $data ) ) {
			return new Activity();
		}

		if ( is_actor( $data ) ) {
			return new Actor();
		}

		if ( is_collection( $data ) ) {
			return new Collection();
		}

		if ( is_activity_object( $data ) ) {
			return new Activity_Object();
		}

		return new \WP_Error( 'activitypub_unknown_type', \__( 'The type of the document is not known.', 'activitypub' ), array( 'status' => 400 ) );
	}
}
