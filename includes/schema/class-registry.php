<?php
/**
 * Schema registry class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

use function Activitypub\is_activity;
use function Activitypub\is_actor;

/**
 * Finds the schema object for remote data: the one of its type, else the one of its kind.
 *
 * @since unreleased
 */
class Registry {
	/**
	 * Get the schema object for remote data.
	 *
	 * @param mixed $data The remote data.
	 *
	 * @return Base_Object The schema object.
	 */
	public static function get( $data ) {
		$type    = \is_array( $data ) && isset( $data['type'] ) && \is_string( $data['type'] ) ? $data['type'] : '';
		$schemas = self::get_schemas();

		if ( isset( $schemas[ $type ] ) && $schemas[ $type ] instanceof Base_Object ) {
			return $schemas[ $type ];
		}

		// No schema of its own: the one of its kind.
		if ( is_actor( $data ) ) {
			return new Actor();
		}

		if ( is_activity( $data ) ) {
			return new Activity();
		}

		return new Base_Object();
	}

	/**
	 * Get the schema object for an activity: the one of its type, else the one of an activity.
	 *
	 * A document delivered to an inbox is an activity whatever its type says, so a type the
	 * plugin does not know still needs what every activity needs.
	 *
	 * @param mixed $data The activity.
	 *
	 * @return Activity The schema object.
	 */
	public static function get_activity( $data ) {
		$schema = self::get( $data );

		return $schema instanceof Activity ? $schema : new Activity();
	}

	/**
	 * Get the schema objects of the types that have rules of their own.
	 *
	 * @return Base_Object[] The schema objects, keyed by type.
	 */
	public static function get_schemas() {
		$request      = new Request();
		$intransitive = new Intransitive_Activity();

		$schemas = array(
			'Arrive'         => $intransitive,
			'FeatureRequest' => $request,
			'QuoteRequest'   => $request,
			'Travel'         => $intransitive,
		);

		/**
		 * Filters the schema objects of the types that have rules of their own.
		 *
		 * A type without an entry gets the schema of its kind: `Actor`, `Activity` or `Base_Object`.
		 *
		 * @param Base_Object[] $schemas The schema objects, keyed by type.
		 */
		return \apply_filters( 'activitypub_schemas', $schemas );
	}
}
