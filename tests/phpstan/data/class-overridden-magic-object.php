<?php
/**
 * An activity object with its own magic dispatcher.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\PHPStan;

use Activitypub\Activity\Generic_Object;

/**
 * An activity object that overrides the generic accessor dispatcher.
 */
class Overridden_Magic_Object extends Generic_Object {
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
