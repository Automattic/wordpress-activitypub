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
 * Example usage:
 *
 * $schema = ( new Registry() )->get( $document );                   // Any kind, as the Proxy fetches.
 * $schema = Registry::limit( Activity::class )->get( $activity );   // Only activities, as an inbox receives.
 *
 * @since unreleased
 */
class Registry {
	/**
	 * The class a schema has to be.
	 *
	 * @var string
	 */
	protected $kind = Base_Object::class;

	/**
	 * A registry that only finds the schemas of one kind.
	 *
	 * @param string $kind The class a schema has to be, such as `Activity::class`.
	 *
	 * @return Registry The registry.
	 */
	public static function limit( $kind ) {
		$registry       = new self();
		$registry->kind = $kind;

		return $registry;
	}

	/**
	 * Get the schema object for remote data.
	 *
	 * @param mixed $data The remote data.
	 *
	 * @return Base_Object|\WP_Error The schema object, an error with status 400 for a type of no known
	 *                               kind or of another kind than the registry is limited to.
	 */
	public function get( $data ) {
		$schema = $this->find( $data );

		if ( \is_wp_error( $schema ) || $schema instanceof $this->kind ) {
			return $schema;
		}

		return new \WP_Error( 'activitypub_unexpected_type', \__( 'The type of the document is not expected here.', 'activitypub' ), array( 'status' => 400 ) );
	}

	/**
	 * Find the schema object for remote data, of any kind.
	 *
	 * @param mixed $data The remote data.
	 *
	 * @return Base_Object|\WP_Error The schema object, an error with status 400 for a type of no known kind.
	 */
	protected function find( $data ) {
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
