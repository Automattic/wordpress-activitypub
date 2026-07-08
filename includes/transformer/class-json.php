<?php
/**
 * String Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Actor;
use Activitypub\Activity\Base_Object;

use function Activitypub\is_activity;
use function Activitypub\is_actor;

/**
 * String Transformer Class file.
 */
class Json extends Activity_Object {

	/**
	 * JSON constructor.
	 *
	 * @param string|array $item The item that should be transformed.
	 */
	public function __construct( $item ) {
		if ( \is_string( $item ) ) {
			$item = \json_decode( $item, true );
		}

		// Check if the item is an Activity or an Object.
		if ( is_activity( $item ) ) {
			$class = Activity::class;
		} elseif ( is_actor( $item ) ) {
			$class = Actor::class;
		} else {
			$class = Base_Object::class;
		}

		$object = $class::init_from_array( $item );

		parent::__construct( $object );
	}
}
