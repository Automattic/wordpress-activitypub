<?php
/**
 * String Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Activity\Base_Object;

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

		if ( is_array( $item ) ) {
			$object = Base_Object::init_from_array( $item );
		} elseif ( is_string( $item ) ) {
			$object = Base_Object::init_from_json( $item );
		}

		parent::__construct( $object );
	}

	/**
	 * Returns the public secondary audience of this object
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-cc
	 *
	 * @return array The secondary audience of this object.
	 */
	protected function get_cc() {
		return $this->item->get( 'cc' );
	}
}
