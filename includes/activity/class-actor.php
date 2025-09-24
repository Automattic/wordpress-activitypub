<?php
/**
 * Inspired by the PHP ActivityPub Library by @Landrok
 *
 * @link https://github.com/landrok/activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Activity;

/**
 * \Activitypub\Activity\Actor is an implementation of
 * one an Activity Streams Actor.
 *
 * Represents an individual actor.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#actor-types
 */
class Actor extends Base_Object {
	// Reduced context for actors. TODO: still unused.
	const JSON_LD_CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
		'https://w3id.org/security/v1',
		'https://purl.archive.org/socialweb/webfinger',
		array(
			'schema'                    => 'http://schema.org#',
			'toot'                      => 'http://joinmastodon.org/ns#',
			'lemmy'                     => 'https://join-lemmy.org/ns#',
			'litepub'                   => 'http://litepub.social/ns#',
			'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
			'PropertyValue'             => 'schema:PropertyValue',
			'value'                     => 'schema:value',
			'Hashtag'                   => 'as:Hashtag',
			'featured'                  => array(
				'@id'   => 'toot:featured',
				'@type' => '@id',
			),
			'featuredTags'              => array(
				'@id'   => 'toot:featuredTags',
				'@type' => '@id',
			),
			'moderators'                => array(
				'@id'   => 'lemmy:moderators',
				'@type' => '@id',
			),
			'alsoKnownAs'               => array(
				'@id'   => 'as:alsoKnownAs',
				'@type' => '@id',
			),
			'movedTo'                   => array(
				'@id'   => 'as:movedTo',
				'@type' => '@id',
			),
			'attributionDomains'        => array(
				'@id'   => 'toot:attributionDomains',
				'@type' => '@id',
			),
			'implements'                => array(
				'@id'        => 'https://w3id.org/fep/844e/implements',
				'@type'      => '@id',
				'@container' => '@list',
			),
			'postingRestrictedToMods'   => 'lemmy:postingRestrictedToMods',
			'discoverable'              => 'toot:discoverable',
			'indexable'                 => 'toot:indexable',
			'invisible'                 => 'litepub:invisible',
		),
	);

	/**
	 * The default types for Actors.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#actor-types
	 *
	 * @var array
	 */
	const TYPES = array(
		'Application',
		'Group',
		'Organization',
		'Person',
		'Service',
	);

	/**
	 * The type of the object.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * A reference to an ActivityStreams OrderedCollection comprised of
	 * all the messages received by the actor.
	 *
	 * @see https://www.w3.org/TR/activitypub/#inbox
	 *
	 * @var string|null
	 */
	protected $inbox;

	/**
	 * A reference to an ActivityStreams OrderedCollection comprised of
	 * all the messages produced by the actor.
	 *
	 * @see https://www.w3.org/TR/activitypub/#outbox
	 *
	 * @var string|null
	 */
	protected $outbox;

	/**
	 * A link to an ActivityStreams collection of the actors that this
	 * actor is following.
	 *
	 * @see https://www.w3.org/TR/activitypub/#following
	 *
	 * @var string
	 */
	protected $following;

	/**
	 * A link to an ActivityStreams collection of the actors that
	 * follow this actor.
	 *
	 * @see https://www.w3.org/TR/activitypub/#followers
	 *
	 * @var string
	 */
	protected $followers;

	/**
	 * A link to an ActivityStreams collection of objects this actor has
	 * liked.
	 *
	 * @see https://www.w3.org/TR/activitypub/#liked
	 *
	 * @var string
	 */
	protected $liked;

	/**
	 * A list of supplementary Collections which may be of interest.
	 *
	 * @see https://www.w3.org/TR/activitypub/#streams-property
	 *
	 * @var array
	 */
	protected $streams = array();

	/**
	 * A short username which may be used to refer to the actor, with no
	 * uniqueness guarantees.
	 *
	 * @see https://www.w3.org/TR/activitypub/#preferredUsername
	 *
	 * @var string|null
	 */
	protected $preferred_username;

	/**
	 * A JSON object which maps additional typically server/domain-wide
	 * endpoints which may be useful either for this actor or someone
	 * referencing this actor. This mapping may be nested inside the
	 * actor document as the value or may be a link to a JSON-LD
	 * document with these properties.
	 *
	 * @see https://www.w3.org/TR/activitypub/#endpoints
	 *
	 * @var string|array|null
	 */
	protected $endpoints;

	/**
	 * It's not part of the ActivityPub protocol, but it's a quite common
	 * practice to handle an actor public key with a publicKey array:
	 * [
	 *     'id'           => 'https://my-example.com/actor#main-key'
	 *     'owner'        => 'https://my-example.com/actor',
	 *     'publicKeyPem' => '-----BEGIN PUBLIC KEY-----
	 *                       [...]
	 *                       -----END PUBLIC KEY-----'
	 * ]
	 *
	 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Authentication_Authorization#Signing_requests_using_HTTP_Signatures
	 *
	 * @var string|array|null
	 */
	protected $public_key;

	/**
	 * It's not part of the ActivityPub protocol, but it's a quite common
	 * practice to lock an account. If enabled, new followers will not be
	 * automatically accepted, but will instead require you to manually
	 * approve them.
	 *
	 * WordPress does only support 'false' at the moment.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#as
	 *
	 * @context as:manuallyApprovesFollowers
	 *
	 * @var boolean|null
	 */
	protected $manually_approves_followers = false;

	/**
	 * Domains allowed to use `fediverse:creator` for this actor in
	 * published articles.
	 *
	 * @see https://blog.joinmastodon.org/2024/07/highlighting-journalism-on-mastodon/
	 *
	 * @var array|null
	 */
	protected $attribution_domains = null;

	/**
	 * The target of the actor.
	 *
	 * @var string|null
	 */
	protected $moved_to;

	/**
	 * The alsoKnownAs of the actor.
	 *
	 * @var array|null
	 */
	protected $also_known_as;

	/**
	 * The Featured-Posts.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#featured
	 *
	 * @context {
	 *   "@id": "http://joinmastodon.org/ns#featured",
	 *   "@type": "@id"
	 * }
	 *
	 * @var string|null
	 */
	protected $featured;

	/**
	 * The Featured-Tags.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#featuredTags
	 *
	 * @context {
	 *   "@id": "http://joinmastodon.org/ns#featuredTags",
	 *   "@type": "@id"
	 * }
	 *
	 * @var string|null
	 */
	protected $featured_tags;

	/**
	 * Whether the User is discoverable.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#discoverable
	 *
	 * @context http://joinmastodon.org/ns#discoverable
	 *
	 * @var boolean|null
	 */
	protected $discoverable;

	/**
	 * Whether the User is indexable.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#indexable
	 *
	 * @context http://joinmastodon.org/ns#indexable
	 *
	 * @var boolean|null
	 */
	protected $indexable;

	/**
	 * The WebFinger Resource.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/2c59/fep-2c59.md
	 *
	 * @var string|null
	 */
	protected $webfinger;

	/**
	 * URL to the Moderators endpoint.
	 *
	 * @see https://join-lemmy.org/docs/contributors/05-federation.html
	 *
	 * @var string|null
	 */
	protected $moderators;

	/**
	 * Restrict posting to mods.
	 *
	 * @see https://join-lemmy.org/docs/contributors/05-federation.html
	 *
	 * @var boolean|null
	 */
	protected $posting_restricted_to_mods;

	/**
	 * Listing Implemented Specifications on the Application Actor
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/844e/fep-844e.md
	 *
	 * @var array|null
	 */
	protected $implements;

	/**
	 * Whether the User is invisible.
	 *
	 * @see https://litepub.social/
	 *
	 * @var boolean|null
	 */
	protected $invisible = null;
}
