<?php
/**
 * Inspired by the PHP ActivityPub Library by @Landrok
 *
 * @link https://github.com/landrok/activitypub
 */

namespace Activitypub\Activity;

use Activitypub\Activity\Base_Object;

/**
 * \Activitypub\Activity\Activity implements the common
 * attributes of an Activity.
 *
 * @see https://www.w3.org/TR/activitystreams-core/#activities
 * @see https://www.w3.org/TR/activitystreams-core/#intransitiveactivities
 */
class Activity extends Base_Object {
	const CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
		'https://w3id.org/security/v1',
		array(
			'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
			'PropertyValue' => 'schema:PropertyValue',
			'schema' => 'http://schema.org#',
			'pt' => 'https://joinpeertube.org/ns#',
			'toot' => 'http://joinmastodon.org/ns#',
			'value' => 'schema:value',
			'Hashtag' => 'as:Hashtag',
			'featured' => array(
				'@id' => 'toot:featured',
				'@type' => '@id',
			),
			'featuredTags' => array(
				'@id' => 'toot:featuredTags',
				'@type' => '@id',
			),
		),
	);

	/**
	 * The object's unique global identifier
	 *
	 * @see https://www.w3.org/TR/activitypub/#obj-id
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $type = 'Activity';

	/**
	 * The context within which the object exists or an activity was
	 * performed.
	 * The notion of "context" used is intentionally vague.
	 * The intended function is to serve as a means of grouping objects
	 * and activities that share a common originating context or
	 * purpose. An example could be all activities relating to a common
	 * project or event.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-context
	 *
	 * @var string
	 *    | ObjectType
	 *    | Link
	 *    | null
	 */
	protected $context = self::CONTEXT;

	/**
	 * Describes the direct object of the activity.
	 * For instance, in the activity "John added a movie to his
	 * wishlist", the object of the activity is the movie added.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-object-term
	 *
	 * @var string
	 *    | Base_Objectr
	 *    | Link
	 *    | null
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
	 * @var string
	 *    | \ActivityPhp\Type\Extended\AbstractActor
	 *    | array<Actor>
	 *    | array<Link>
	 *    | Link
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
	 * @var string
	 *    | ObjectType
	 *    | array<ObjectType>
	 *    | Link
	 *    | array<Link>
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
	 * @var string
	 *    | ObjectType
	 *    | Link
	 *    | null
	 */
	protected $result;

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
	 * @var string
	 *    | ObjectType
	 *    | Link
	 *    | null
	 */
	protected $origin;

	/**
	 * One or more objects used (or to be used) in the completion of an
	 * Activity.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-instrument
	 *
	 * @var string
	 *    | ObjectType
	 *    | Link
	 *    | null
	 */
	protected $instrument;

	/**
	 * Set the object and copy Object properties to the Activity.
	 *
	 * Any to, bto, cc, bcc, and audience properties specified on the object
	 * MUST be copied over to the new Create activity by the server.
	 *
	 * @see https://www.w3.org/TR/activitypub/#object-without-create
	 *
	 * @param string|Base_Objectr|Link|null $object
	 *
	 * @return void
	 */
	public function set_object( $object ) {
		$this->set( 'object', $object );

		if ( ! is_object( $object ) ) {
			return;
		}

		foreach ( array( 'to', 'bto', 'cc', 'bcc', 'audience' ) as $i ) {
			$this->set( $i, $object->get( $i ) );
		}

		if ( $object->get_published() && ! $this->get_published() ) {
			$this->set( 'published', $object->get_published() );
		}

		if ( $object->get_updated() && ! $this->get_updated() ) {
			$this->set( 'updated', $object->get_updated() );
		}

		if ( $object->attributed_to() && ! $this->get_actor() ) {
			$this->set( 'actor', $object->attributed_to() );
		}

		if ( $object->get_id() && ! $this->get_id() ) {
			$this->set( 'id', $object->get_id() . '#activity' );
		}
	}
}
