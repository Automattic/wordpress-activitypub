<?php
/**
 * Cached Actor model file.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

use Activitypub\Activity\Actor;

/**
 * Cached Actor model.
 *
 * This class wraps an Actor object and returns the cached data when requested.
 * It's used to serve old domain actor data during domain migration.
 */
class Cached_Actor {
	/**
	 * The wrapped actor object.
	 *
	 * @var Actor
	 */
	private $actor;

	/**
	 * Constructor.
	 *
	 * @param Actor $actor The actor to wrap.
	 */
	public function __construct( Actor $actor ) {
		$this->actor = $actor;
	}

	/**
	 * Magic method to pass all method calls to the wrapped actor.
	 *
	 * @param string $method The method name.
	 * @param array  $args   The method arguments.
	 *
	 * @return mixed The result of the method call.
	 */
	public function __call( $method, $args ) {
		if ( \method_exists( $this->actor, $method ) ) {
			return \call_user_func_array( array( $this->actor, $method ), $args );
		}

		return null;
	}

	/**
	 * Get the actor ID.
	 *
	 * @return string The actor ID.
	 */
	public function get_id() {
		return $this->actor->get_id();
	}

	/**
	 * Get the actor's internal WordPress ID.
	 *
	 * @return int The actor's internal WordPress ID.
	 */
	public function get__id() {
		return $this->actor->get__id();
	}

	/**
	 * Get the actor type.
	 *
	 * @return string The actor type.
	 */
	public function get_type() {
		return $this->actor->get_type();
	}

	/**
	 * Convert the actor to an array.
	 *
	 * @param bool $include_json_ld_context Whether to include the JSON-LD context.
	 *
	 * @return array The actor as an array.
	 */
	public function to_array( $include_json_ld_context = true ) {
		return $this->actor->to_array( $include_json_ld_context );
	}

	/**
	 * Convert the actor to JSON.
	 *
	 * @param bool $include_json_ld_context Whether to include the JSON-LD context.
	 *
	 * @return string The actor as JSON.
	 */
	public function to_json( $include_json_ld_context = true ) {
		return $this->actor->to_json( $include_json_ld_context );
	}
}
