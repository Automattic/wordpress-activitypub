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
 *
 * @method string[]|null     get_also_known_as()               Gets the also known as property of the actor.
 * @method array|null        get_attribution_domains()         Gets domains allowed to use fediverse:creator for this actor.
 * @method bool|null         get_discoverable()                Gets whether the actor is discoverable.
 * @method string[]|null     get_endpoints()                   Gets the endpoint property of the actor.
 * @method string|null       get_featured()                    Gets the featured posts collection of the actor.
 * @method string|null       get_featured_tags()               Gets the featured tags collection of the actor.
 * @method string|null       get_followers()                   Gets the followers collection of the actor.
 * @method string|null       get_following()                   Gets the following collection of the actor.
 * @method array|null        get_implements()                  Gets the list of implemented specifications.
 * @method string|null       get_inbox()                       Gets the inbox property of the actor.
 * @method bool|null         get_indexable()                   Gets whether the actor is indexable.
 * @method bool|null         get_invisible()                   Gets whether the actor is invisible.
 * @method string|null       get_liked()                       Gets the liked collection of the actor.
 * @method bool|null         get_manually_approves_followers() Gets whether the actor manually approves followers.
 * @method string|null       get_moderators()                  Gets the moderators endpoint URL.
 * @method string|null       get_moved_to()                    Gets the target of the actor move.
 * @method string|null       get_outbox()                      Gets the outbox property of the actor.
 * @method bool|null         get_posting_restricted_to_mods()  Gets whether posting is restricted to moderators.
 * @method string|null       get_preferred_username()          Gets the preferred username of the actor.
 * @method string|array|null get_public_key()                  Gets the public key of the actor.
 * @method array             get_streams()                     Gets the list of supplementary collections.
 * @method string|null       get_webfinger()                   Gets the WebFinger resource.
 *
 * @method Actor set_also_known_as( array $also_known_as )                            Sets the also known as property of the actor.
 * @method Actor set_attribution_domains( array $attribution_domains )                Sets domains allowed to use fediverse:creator for this actor.
 * @method Actor set_discoverable( bool $discoverable )                               Sets whether the actor is discoverable.
 * @method Actor set_endpoints( string|array $endpoints )                             Sets the endpoint property of the actor.
 * @method Actor set_featured( string $featured )                                     Sets the featured posts collection of the actor.
 * @method Actor set_featured_tags( string $featured_tags )                           Sets the featured tags collection of the actor.
 * @method Actor set_followers( string $followers )                                   Sets the followers collection of the actor.
 * @method Actor set_following( string $following )                                   Sets the following collection of the actor.
 * @method Actor set_implements( array $implements )                                  Sets the list of implemented specifications.
 * @method Actor set_inbox( string $inbox )                                           Sets the inbox property of the actor.
 * @method Actor set_indexable( bool $indexable )                                     Sets whether the actor is indexable.
 * @method Actor set_invisible( bool $invisible )                                     Sets whether the actor is invisible.
 * @method Actor set_liked( string $liked )                                           Sets the liked collection of the actor.
 * @method Actor set_manually_approves_followers( bool $manually_approves_followers ) Sets whether the actor manually approves followers.
 * @method Actor set_moderators( string $moderators )                                 Sets the moderators endpoint URL.
 * @method Actor set_moved_to( string $moved_to )                                     Sets the target of the actor move.
 * @method Actor set_outbox( string $outbox )                                         Sets the outbox property of the actor.
 * @method Actor set_posting_restricted_to_mods( bool $posting_restricted_to_mods )   Sets whether posting is restricted to moderators.
 * @method Actor set_preferred_username( string $preferred_username )                 Sets the preferred username of the actor.
 * @method Actor set_public_key( string|array $public_key )                           Sets the public key of the actor.
 * @method Actor set_streams( array $streams )                                        Sets the list of supplementary collections.
 * @method Actor set_webfinger( string $webfinger )                                   Sets the WebFinger resource.
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
