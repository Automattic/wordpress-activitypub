<?php
/**
 * Inspired by the PHP ActivityPub Library by @Landrok
 *
 * @link https://github.com/landrok/activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Activity;

use Activitypub\Link;

use function Activitypub\is_actor;
use function Activitypub\is_activity;

/**
 * \Activitypub\Activity\Activity implements the common
 * attributes of an Activity.
 *
 * @see https://www.w3.org/TR/activitystreams-core/#activities
 * @see https://www.w3.org/TR/activitystreams-core/#intransitiveactivities
 *
 * @method string|array|null       get_actor()          Gets one or more entities that performed or are expected to perform the activity.
 * @method string|null             get_id()             Gets the object's unique global identifier.
 * @method string                  get_type()           Gets the type of the object.
 * @method string|null             get_name()           Gets the natural language name of the object.
 * @method string|null             get_url()            Gets the URL of the object.
 * @method string|null             get_summary()        Gets the natural language summary of the object.
 * @method string|null             get_published()      Gets the date and time the object was published in ISO 8601 format.
 * @method string|null             get_updated()        Gets the date and time the object was updated in ISO 8601 format.
 * @method string|null             get_attributed_to()  Gets the entity attributed as the original author.
 * @method array|string|null       get_cc()             Gets the secondary recipients of the object.
 * @method array|string|null       get_to()             Gets the primary recipients of the object.
 * @method array|null              get_attachment()     Gets the attachment property of the object.
 * @method array|null              get_icon()           Gets the icon property of the object.
 * @method array|null              get_image()          Gets the image property of the object.
 * @method Base_Object|string|null get_object()         Gets the direct object of the activity.
 * @method array|string|null       get_in_reply_to()    Gets the objects this object is in reply to.
 *
 * @method Activity set_actor( string|array $actor )    Sets one or more entities that performed the activity.
 * @method Activity set_id( string $id )                Sets the object's unique global identifier.
 * @method Activity set_type( string $type )            Sets the type of the object.
 * @method Activity set_name( string $name )            Sets the natural language name of the object.
 * @method Activity set_url( string $url )              Sets the URL of the object.
 * @method Activity set_summary( string $summary )      Sets the natural language summary of the object.
 * @method Activity set_published( string $published )  Sets the date and time the object was published in ISO 8601 format.
 * @method Activity set_updated( string $updated )      Sets the date and time the object was updated in ISO 8601 format.
 * @method Activity set_attributed_to( string $attributed_to ) Sets the entity attributed as the original author.
 * @method Activity set_cc( array|string $cc )          Sets the secondary recipients of the object.
 * @method Activity set_to( array|string $to )          Sets the primary recipients of the object.
 * @method Activity set_attachment( array $attachment ) Sets the attachment property of the object.
 * @method Activity set_icon( array $icon )             Sets the icon property of the object.
 * @method Activity set_image( array $image )           Sets the image property of the object.
 */
class Activity extends Base_Object {
	const JSON_LD_CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
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
	 * @var string|Base_Object|null
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
	 * @var string|array
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
	 * @var string|Base_Object
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
	 * @var array
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
	 * @var string|array
	 */
	protected $origin;

	/**
	 * One or more objects used (or to be used) in the completion of an
	 * Activity.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-instrument
	 *
	 * @var string|array
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
	 * @param array|string|Base_Object|Link|null $data Activity object.
	 */
	public function set_object( $data ) {
		// Convert array to object.
		if ( is_array( $data ) ) {
			// Check if the item is an Activity or an Object.
			if ( is_activity( $data ) ) {
				$data = self::init_from_array( $data );
			} elseif ( is_actor( $data ) ) {
				$data = Actor::init_from_array( $data );
			} else {
				$data = Base_Object::init_from_array( $data );
			}
		}

		// Set object.
		$this->set( 'object', $data );

		// Check if `$data` is a URL and use it to generate an ID then.
		if ( is_string( $data ) && filter_var( $data, FILTER_VALIDATE_URL ) && ! $this->get_id() ) {
			$this->set( 'id', $data . '#activity-' . strtolower( $this->get_type() ) . '-' . time() );

			return;
		}

		// Check if `$data` is an object and copy some properties otherwise do nothing.
		if ( ! is_object( $data ) ) {
			return;
		}

		foreach ( array( 'to', 'bto', 'cc', 'bcc', 'audience' ) as $i ) {
			$this->set( $i, $data->get( $i ) );
		}

		if ( $data->get_published() && ! $this->get_published() ) {
			$this->set( 'published', $data->get_published() );
		}

		if ( $data->get_updated() && ! $this->get_updated() ) {
			$this->set( 'updated', $data->get_updated() );
		}

		if ( $data->get_attributed_to() && ! $this->get_actor() ) {
			$this->set( 'actor', $data->get_attributed_to() );
		}

		if ( $data->get_in_reply_to() ) {
			$this->set( 'in_reply_to', $data->get_in_reply_to() );
		}

		if ( $data->get_id() && ! $this->get_id() ) {
			$id = strtok( $data->get_id(), '#' );
			if ( $data->get_updated() ) {
				$updated = $data->get_updated();
			} else {
				$updated = $data->get_published();
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
		if ( $this->object instanceof Base_Object ) {
			$class = get_class( $this->object );
			if ( $class && $class::JSON_LD_CONTEXT ) {
				// Without php 5.6 support this could be just: 'return  $this->object::JSON_LD_CONTEXT;'.
				return $class::JSON_LD_CONTEXT;
			}
		}

		return static::JSON_LD_CONTEXT;
	}
}
