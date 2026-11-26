<?php
/**
 * Represents a category as a SKOS concept.
 *
 * A Category is an alias for a {@link https://www.w3.org/TR/skos-reference/ SKOS Concept} as defined by {@link https://fediverse.codeberg.page/fep/fep/2fa1/ FEP-2fa1}.
 *
 * @package Activitypub
 */

namespace Activitypub\Activity;

/**
 * Represents a category as a SKOS concept.
 *
 * A Category is an alias for a {@link https://www.w3.org/TR/skos-reference/ SKOS Concept} as defined by {@link https://fediverse.codeberg.page/fep/fep/2fa1/ FEP-2fa1}.
 *
 * @method string|array|null get_in_scheme() Gets the classification scheme(s) the category belongs to.
 * @method string|array|null get_exact_match() Gets concepts with an exact match to this category.
 * @method string|array|null get_close_match() Gets concepts with a close match to this category.
 * @method string|array|null get_broader() Gets the broader concept(s) (skos:broader).
 *
 * @method Category set_in_scheme( string|array $in_scheme ) Sets the classification scheme(s) the category belongs to.
 * @method Category set_exact_match( string|array $exact_match ) Sets concepts with an exact match to this category.
 * @method Category set_close_match( string|array $close_match ) Sets concepts with a close match to this category.
 * @method Category set_broader( string|array $broader ) Sets the broader concept(s).
 */
class Category extends Base_Object {
	/**
	 * The JSON-LD context for the object.
	 *
	 * @var array
	 */
	const JSON_LD_CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
		'https://w3id.org/fep/2fa1',
	);

	/**
	 * The type of the category object.
	 *
	 * @var string
	 */
	protected $type = 'Category';

	/**
	 * The classification scheme to which this concept belongs.
	 *
	 * Maps to {@link https://www.w3.org/TR/skos-reference/#L1693 skos:inScheme}
	 *
	 * @var string|array|null
	 */
	protected $in_scheme;

	/**
	 * A concept that is an exact match of this concept.
	 *
	 * Maps to {@link https://www.w3.org/TR/skos-reference/#L1699  skos:exactMatch}
	 *
	 * @var string|array|null
	 */
	protected $exact_match;

	/**
	 * A concept that is a close match of this concept.
	 *
	 * Maps to {@link https://www.w3.org/TR/skos-reference/#L1698 skos:closeMatch}
	 *
	 * @var string|array|null
	 */
	protected $close_match;

	/**
	 * A concept that is broader than this concept.
	 *
	 * Maps to {@link https://www.w3.org/TR/skos-reference/#L1695 skos:broader}
	 *
	 * @var string|array|null
	 */
	protected $broader;
}
