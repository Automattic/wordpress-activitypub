<?php
/**
 * Inspired by the PHP ActivityPub Library by @Landrok
 *
 * @link https://github.com/landrok/activitypub
 */

namespace Activitypub\Activity;

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
class Note extends Base_Object {
	protected $type = 'Event';
}
