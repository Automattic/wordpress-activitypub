<?php
/**
 * String Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Activity\Base_Object;

use function Activitypub\is_activity;

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
		$object = new Base_Object();

		// Check if the item is an Activity or an Object.
		if ( is_activity( $item ) ) {
			$class = '\Activitypub\Activity\Activity';
		} else {
			$class = '\Activitypub\Activity\Base_Object';
		}

		if ( is_array( $item ) ) {
			$object = $class::init_from_array( $item );
		} elseif ( is_string( $item ) ) {
			$object = $class::init_from_json( $item );
		}

		parent::__construct( $object );
	}
}
