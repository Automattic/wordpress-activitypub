<?php
/**
 * Inspired by the PHP ActivityPub Library by @Landrok
 *
 * @link https://github.com/landrok/activitypub
 */

namespace Activitypub\Activity;

use Activitypub\Activity\Base_Object;

/**
 * Note is an implementation of one of the
 * Activity Streams Note object type
 *
 * The Object is the primary base type for the Activity Streams
 * vocabulary.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-note
 */
class Note extends Base_Object {
    protected $type = 'Note';
}
