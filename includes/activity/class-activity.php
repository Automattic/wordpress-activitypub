<?php
/**
 * Inspired by the PHP ActivityPub Library by @Landrok
 *
 * @link https://github.com/landrok/activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Activity;

use Activitypub\Activity\Extended_Object\Event;
use Activitypub\Activity\Extended_Object\Place;

/**
 * \Activitypub\Activity\Activity implements the common
 * attributes of an Activity.
 *
 * @see https://www.w3.org/TR/activitystreams-core/#activities
 * @see https://www.w3.org/TR/activitystreams-core/#intransitiveactivities
 */
class Activity extends Base_Object {
	const JSON_LD_CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
		array(
			'toot'         => 'http://joinmastodon.org/ns#',
			'QuoteRequest' => 'toot:QuoteRequest',
		),
	);

	/**
	 * The default types for Activities.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#activity-types
	 *
	 * @var array
	 */
	const TYPES = array(
		'Accept',
		'Add',
		'Announce',
		'Arrive',
		'Block',
		'Create',
		'Delete',
		'Dislike',
		'Follow',
		'Flag',
		'Ignore',
		'Invite',
		'Join',
		'Leave',
		'Like',
		'Listen',
		'Move',
		'Offer',
		'QuoteRequest', // @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md
		'Read',
		'Reject',
		'Remove',
		'TentativeAccept',
		'TentativeReject',
		'Travel',
		'Undo',
		'Update',
		'View',
	);

	/**
	 * The type of the object.
	 *
	 * @var string
	 */
	protected $type = 'Activity';

	/**
	 * Describes the direct object of the activity.
	 * For instance, in the activity "John added a movie to his
	 * wishlist", the object of the activity is the movie added.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-object-term
	 *
	 * @var string|Base_Object|array|null
	 */
	protected $object;

	/**
	 * Describes one or more entities that either performed or are
	 * expected to perform the activity.
	 * Any single activity can have multiple actors.
	 * The actor MAY be specified using an indirect Link.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-actor
	 *
	 * @var string|array
	 */
	protected $actor;

	/**
	 * The indirect object, or target, of the activity.
	 * The precise meaning of the target is largely dependent on the
	 * type of action being described but will often be the object of
	 * the English preposition "to".
	 * For instance, in the activity "John added a movie to his
	 * wishlist", the target of the activity is John's wishlist.
	 * An activity can have more than one target.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-target
	 *
	 * @var string|array|null
	 */
	protected $target;

	/**
	 * Describes the result of the activity.
	 * For instance, if a particular action results in the creation of
	 * a new resource, the result property can be used to describe
	 * that new resource.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-result
	 *
	 * @var string|Base_Object|null
	 */
	protected $result;

	/**
	 * Identifies a Collection containing objects considered to be responses
	 * to this object.
	 * WordPress has a strong core system of approving replies. We only include
	 * approved replies here.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-replies
	 *
	 * @var array|null
	 */
	protected $replies;

	/**
	 * An indirect object of the activity from which the
	 * activity is directed.
	 * The precise meaning of the origin is the object of the English
	 * preposition "from".
	 * For instance, in the activity "John moved an item to List B
	 * from List A", the origin of the activity is "List A".
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-origin
	 *
	 * @var string|array|null
	 */
	protected $origin;

	/**
	 * One or more objects used (or to be used) in the completion of an
	 * Activity.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-instrument
	 *
	 * @var string|array|null
	 */
	protected $instrument;

	/**
	 * Set the object and copy Object properties to the Activity.
	 *
	 * Any to, bto, cc, bcc, and audience properties specified on the object
	 * MUST be copied over to the new "Create" activity by the server.
	 *
	 * @see https://www.w3.org/TR/activitypub/#object-without-create
	 *
	 * @param array|string|Base_Object|Activity|Actor|null $data Activity object.
	 */
	public function set_object( $data ) {
		$object = $data;

		// Convert array to appropriate object type.
		if ( is_array( $data ) ) {
			if ( array_is_list( $data ) ) {
				$object = array_map( array( $this, 'maybe_convert_to_object' ), $data );
			} else {
				$object = $this->maybe_convert_to_object( $data );
			}
		}

		$this->set( 'object', $object );
		$this->pre_fill_activity_from_object();
	}

	/**
	 * Fills the Activity with the specified activity object.
	 */
	public function pre_fill_activity_from_object() {
		$object = $this->get_object();

		// Check if `$data` is a URL and use it to generate an ID then.
		if ( is_string( $object ) && filter_var( $object, FILTER_VALIDATE_URL ) && ! $this->get_id() ) {
			$this->set( 'id', $object . '#activity-' . strtolower( $this->get_type() ) . '-' . time() );

			return;
		}

		// Check if `$data` is an object and copy some properties otherwise do nothing.
		if ( ! is_object( $object ) ) {
			return;
		}

		foreach ( array( 'to', 'bto', 'cc', 'bcc', 'audience' ) as $i ) {
			$value = $object->get( $i );
			if ( $value && ! $this->get( $i ) ) {
				$this->set( $i, $value );
			}
		}

		if ( $object->get_published() && ! $this->get_published() ) {
			$this->set( 'published', $object->get_published() );
		}

		if ( $object->get_updated() && ! $this->get_updated() ) {
			$this->set( 'updated', $object->get_updated() );
		}

		if ( $object->get_attributed_to() && ! $this->get_actor() ) {
			$this->set( 'actor', $object->get_attributed_to() );
		}

		if ( $this->get_type() !== 'Announce' && $object->get_in_reply_to() && ! $this->get_in_reply_to() ) {
			$this->set( 'in_reply_to', $object->get_in_reply_to() );
		}

		if ( $object->get_interaction_policy() && ! $this->get_interaction_policy() ) {
			$this->set( 'interaction_policy', $object->get_interaction_policy() );
		}

		if ( $object->get_id() && ! $this->get_id() ) {
			$id = strtok( $object->get_id(), '#' );
			if ( $object->get_updated() ) {
				$updated = $object->get_updated();
			} elseif ( $object->get_published() ) {
				$updated = $object->get_published();
			} else {
				$updated = time();
			}
			$this->set( 'id', $id . '#activity-' . strtolower( $this->get_type() ) . '-' . $updated );
		}
	}

	/**
	 * The context of an Activity is usually just the context of the object it contains.
	 *
	 * @return array $context A compacted JSON-LD context.
	 */
	public function get_json_ld_context() {
		if ( \is_object( $this->object ) ) {
			$class = get_class( $this->object );
			if ( $class && $class::JSON_LD_CONTEXT ) {
				// Without php 5.6 support this could be just: 'return  $this->object::JSON_LD_CONTEXT;'.
				return $class::JSON_LD_CONTEXT;
			}
		}

		return static::JSON_LD_CONTEXT;
	}

	/**
	 * Convert data to the appropriate object type if it has an ActivityPub type.
	 *
	 * @param array|string|Base_Object|Activity|Actor|null $data The data to convert.
	 *
	 * @return Activity|Actor|Base_Object|Generic_Object|string|\WP_Error|null The converted object or original data.
	 */
	private function maybe_convert_to_object( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$type = $data['type'] ?? null;

		if ( in_array( $type, self::TYPES, true ) ) {
			$object = self::init_from_array( $data );
		} elseif ( in_array( $type, Actor::TYPES, true ) ) {
			$object = Actor::init_from_array( $data );
		} elseif ( in_array( $type, Base_Object::TYPES, true ) ) {
			switch ( $type ) {
				case 'Event':
					$object = Event::init_from_array( $data );
					break;
				case 'Place':
					$object = Place::init_from_array( $data );
					break;
				default:
					$object = Base_Object::init_from_array( $data );
					break;
			}
		} else {
			$object = Generic_Object::init_from_array( $data );
		}

		return $object;
	}
}
