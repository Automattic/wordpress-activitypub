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
 * Place is an implementation of the Activity Streams Place object type.
 *
 * The Place object represents a logical or physical location.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-place
 *
 * @method float|null        get_accuracy()  Gets the accuracy of position coordinates.
 * @method array|string|null get_address()   Gets the address of the place.
 * @method float|null        get_altitude()  Gets the altitude of the place.
 * @method float|null        get_latitude()  Gets the latitude of the place.
 * @method float|null        get_longitude() Gets the longitude of the place.
 * @method float|null        get_radius()    Gets the radius from the given latitude and longitude.
 * @method string|null       get_units()     Gets the measurement units for radius and altitude.
 *
 * @method Place set_accuracy( float $accuracy )      Sets the accuracy of position coordinates.
 * @method Place set_address( array|string $address ) Sets the address of the place.
 * @method Place set_altitude( float $altitude )      Sets the altitude of the place.
 * @method Place set_latitude( float $latitude )      Sets the latitude of the place.
 * @method Place set_longitude( float $longitude )    Sets the longitude of the place.
 * @method Place set_radius( float $radius )          Sets the radius from the given latitude and longitude.
 * @method Place set_units( string $units )           Sets the measurement units for radius and altitude.
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
