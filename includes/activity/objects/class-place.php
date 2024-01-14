<?php
/**
 * Event is an implementation of one of the
 * Activity Streams Event object type
 *
 * @package activity-event-transformers
 * @license AGPL-3.0-or-later
 */

namespace Activitypub\Activity\Objects;

use Activitypub\Activity\Base_Object;

/**
 * Event is an implementation of one of the
 * Activity Streams Event object type
 *
 * The Object is the primary base type for the Activity Streams
 * vocabulary.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-event
 */
class Place extends Base_Object {
	/**
	 * Place is an implementation of one of the
	 * Activity Streams
	 *
	 * @var string
	 */
	protected $type = 'Place';

	/**
	 * Indicates the accuracy of position coordinates on a Place objects.
	 * Expressed in properties of percentage. e.g. "94.0" means "94.0% accurate".
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-accuracy
	 * @var float xsd:float [>= 0.0f, <= 100.0f]
	 */
	protected $accuracy;

	/**
	 * Indicates the altitude of a place. The measurement units is indicated using the units property.
	 * If units is not specified, the default is assumed to be "m" indicating meters.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-altitude
	 * @var float xsd:float
	 */
	protected $altitude;

	/**
	 * The latitude of a place.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-latitude
	 * @var float xsd:float
	 */
	protected $latitude;

	/**
	 * The longitude of a place.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-longitude
	 * @var float xsd:float
	 */
	protected $longitude;

	/**
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-radius
	 * @var float
	 */
	protected $radius;

	/**
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-units
	 * @var string
	 */
	protected $units;

	/**
	 * @var Postal_Address|string
	 */
	protected $address;

	public function set_address( $address ) {
		if ( is_string( $address ) || is_array( $address ) ) {
			$this->address = $address;
		} else {
			_doing_it_wrong(
				__METHOD__,
				'The address must be either a string or an array like schema.org/PostalAddress.',
				'<version_placeholder>'
			);
		}
	}
}
