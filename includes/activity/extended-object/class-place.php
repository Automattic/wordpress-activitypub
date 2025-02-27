<?php
/**
 * Event is an implementation of one of the
 * Activity Streams Event object type
 *
 * @package activity-event-transformers
 */

namespace Activitypub\Activity\Extended_Object;

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
	 * Indicates the altitude of a place. The measurement unit is indicated using the unit's property.
	 * If unit is not specified, the default is assumed to be "m" indicating meters.
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
	 * The radius from the given latitude and longitude for a Place.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-radius
	 * @var float
	 */
	protected $radius;

	/**
	 * Specifies the measurement units for the `radius` and `altitude` properties.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-units
	 * @var string
	 */
	protected $units;

	/**
	 * The address of the place.
	 *
	 * @see https://schema.org/PostalAddress
	 * @var array|string
	 */
	protected $address;

	/**
	 * Set the address of the place.
	 *
	 * @param array|string $address The address of the place.
	 */
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
