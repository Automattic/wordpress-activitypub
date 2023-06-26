<?php
/**
 * Inspired by the PHP ActivityPub Library by @Landrok
 *
 * @link https://github.com/landrok/activitypub
 */

namespace Activitypub\Activity;

/**
 * Represents an individual person.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-person
 */
class Person extends Base_Object {
	/**
	 * @var string
	 */
	protected $type = 'Person';
}
