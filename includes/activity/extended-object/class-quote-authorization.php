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
 * Class representing a QuoteAuthorization activity.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md#quoteauthorization
 */
class Quote_Authorization extends Base_Object {
	/**
	 * The JSON-LD context for the object.
	 *
	 * @var array
	 */
	const JSON_LD_CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
		array(
			'QuoteAuthorization' => 'https://w3id.org/fep/044f#QuoteAuthorization',
			'gts'                => 'https://gotosocial.org/ns#',
			'interactingObject'  => array(
				'@id'   => 'gts:interactingObject',
				'@type' => '@id',
			),
			'interactionTarget'  => array(
				'@id'   => 'gts:interactionTarget',
				'@type' => '@id',
			),
		),
	);

	/**
	 * The type of the object.
	 *
	 * @var string
	 */
	protected $type = 'QuoteAuthorization';

	/**
	 * The object that is being interacted with.
	 *
	 * @var mixed
	 */
	protected $interacting_object;

	/**
	 * The target of the interaction.
	 *
	 * @var mixed
	 */
	protected $interaction_target;
}
