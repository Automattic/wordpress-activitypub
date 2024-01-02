<?php
/**
 * PostalAddress is a custom ActivityPub object firstly used by Mobilizon
 * derived from https://schema.org/PostalAddress.
 *
 * @license AGPL-3.0-or-later
 */

namespace Activitypub\Activity;

use Activitypub\Activity\Base_Object;

/**
 * A Postal Address.
 *
 * @context https://schema.org/PostalAddress
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-event
 */
class Postal_Address extends Base_Object {
	/**
	 * Place is an implementation of one of the
	 * Activity Streams
	 *
	 * @var string
	 */
	protected $type = 'PostalAddress';

	/**
	 * The country. For example, USA. You can also provide the two-letter ISO 3166-1 alpha-2 country code.
	 *
	 * @see http://en.wikipedia.org/wiki/ISO_3166-1
	 * @var string
	 */
	protected $address_country;

	/**
	 * The locality in which the street address is, and which is in the region. For example, Mountain View.
	 *
	 * @var string
	 */
	protected $address_locality;

	/**
	 * The region in which the locality is, and which is in the country.
	 * For example, California or another appropriate first-level Administrative division.
	 *
	 * @var string
	 */
	protected $address_region;

	/**
	 * The postal code. For example, 94043.
	 *
	 * @var string
	 */
	protected $postal_code;

	/**
	 * The street address. For example, 1600 Amphitheatre Pkwy.
	 *
	 * @var string
	 */
	protected $street_address;
}
