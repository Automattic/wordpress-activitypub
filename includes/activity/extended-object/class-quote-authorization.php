<?php
/**
 * Quote_Authorization is an implementation of the QuoteAuthorization activity type,
 * as defined in FEP-044f (https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md#quoteauthorization).
 *
 * This class represents a QuoteAuthorization activity for ActivityPub implementations.
 *
 * @package Activitypub
 */

namespace Activitypub\Activity\Extended_Object;

use Activitypub\Activity\Base_Object;

/**
 * Class representing a QuoteAuthorization activity.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md#quoteauthorization
 *
 * @since 7.5.0
 *
 * @method Base_Object|string|array|null get_interacting_object() Gets the interacting object property of the object.
 * @method Base_Object|string|array|null get_interaction_target() Gets the interaction target property of the object.
 *
 * @method Quote_Authorization set_interacting_object( string|array|Base_Object|null $data ) Sets the interacting object property of the object.
 * @method Quote_Authorization set_interaction_target( string|array|Base_Object|null $data ) Sets the interaction target property of the object.
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
	 * @var Base_Object|string|array|null
	 */
	protected $interacting_object;

	/**
	 * The target of the interaction.
	 *
	 * @var Base_Object|string|array|null
	 */
	protected $interaction_target;
}
