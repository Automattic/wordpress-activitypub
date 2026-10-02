<?php
/**
 * Magic dispatchers that do not implement Generic_Object's accessors.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\PHPStan;

/**
 * An unrelated class with a magic dispatcher.
 */
class Unrelated_Magic_Object {
	/**
	 * Dispatch a method.
	 *
	 * @param string $method The method.
	 * @param array  $params The parameters.
	 * @return mixed
	 */
	public function __call( $method, $params ) {
		return $params[ $method ] ?? null;
	}
}
