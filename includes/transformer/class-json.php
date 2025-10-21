<?php
/**
 * String Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

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
			$class = '\Activitypub\Activity\Activity';
		} elseif ( is_actor( $item ) ) {
			$class = '\Activitypub\Activity\Actor';
		} else {
			$class = '\Activitypub\Activity\Base_Object';
		}

		$object = $class::init_from_array( $item );

		parent::__construct( $object );
	}
}
