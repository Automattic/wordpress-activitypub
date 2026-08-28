<?php
/**
 * Feature_Authorization is an implementation of the FeatureAuthorization activity type,
 * as defined in FEP-7aa9 (https://w3id.org/fep/7aa9).
 *
 * This class represents a FeatureAuthorization activity for ActivityPub implementations.
 *
 * @package Activitypub
 */

namespace Activitypub\Activity\Extended_Object;

use Activitypub\Activity\Base_Object;

/**
 * Class representing a FeatureAuthorization activity.
 *
 * @see https://w3id.org/fep/7aa9
 *
 * @since 9.0.0
 *
 * @method Base_Object|string|array|null get_interacting_object() Gets the interacting object property of the object.
 * @method Base_Object|string|array|null get_interaction_target() Gets the interaction target property of the object.
 *
 * @method Feature_Authorization set_interacting_object( string|array|Base_Object|null $data ) Sets the interacting object property of the object.
 * @method Feature_Authorization set_interaction_target( string|array|Base_Object|null $data ) Sets the interaction target property of the object.
 */
class Feature_Authorization extends Base_Object {
	/**
	 * The JSON-LD context for the object.
	 *
	 * Intentionally minimal: stamps are always served standalone at their own
	 * URL, so we ship only the vocabulary the stamp document itself uses.
	 * Mirrors the Quote_Authorization (FEP-044f) approach.
	 *
	 * @var array
	 */
	const JSON_LD_CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
		array(
			'FeatureAuthorization' => 'https://w3id.org/fep/7aa9#FeatureAuthorization',
			'gts'                  => 'https://gotosocial.org/ns#',
			'interactingObject'    => array(
				'@id'   => 'gts:interactingObject',
				'@type' => '@id',
			),
			'interactionTarget'    => array(
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
	protected $type = 'FeatureAuthorization';

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
