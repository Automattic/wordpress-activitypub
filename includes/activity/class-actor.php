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
			'attributionDomains'        => array(
				'@id'   => 'toot:attributionDomains',
				'@type' => '@id',
			),
			'postingRestrictedToMods'   => 'lemmy:postingRestrictedToMods',
			'discoverable'              => 'toot:discoverable',
			'indexable'                 => 'toot:indexable',
		),
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
	 * @var string
	 *    | null
	 */
	protected $inbox;

	/**
	 * A reference to an ActivityStreams OrderedCollection comprised of
	 * all the messages produced by the actor.
	 *
	 * @see https://www.w3.org/TR/activitypub/#outbox
	 *
	 * @var string
	 *    | null
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
	 * It's not part of the ActivityPub protocol but it's a quite common
	 * practice to handle an actor public key with a publicKey array:
	 * [
	 *     'id' => 'https://my-example.com/actor#main-key'
	 *     'owner' => 'https://my-example.com/actor',
	 *     'publicKeyPem' => '-----BEGIN PUBLIC KEY-----
	 *                       MIIBI [...]
	 *                       DQIDAQAB
	 *                       -----END PUBLIC KEY-----'
	 * ]
	 *
	 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Authentication_Authorization#Signing_requests_using_HTTP_Signatures
	 *
	 * @var string|array|null
	 */
	protected $public_key;

	/**
	 * It's not part of the ActivityPub protocol but it's a quite common
	 * practice to lock an account. If anabled, new followers will not be
	 * automatically accepted, but will instead require you to manually
	 * approve them.
	 *
	 * WordPress does only support 'false' at the moment.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#as
	 *
	 * @context as:manuallyApprovesFollowers
	 *
	 * @var boolean
	 */
	protected $manually_approves_followers = false;

	/**
	 * Used to mark an object as containing sensitive content.
	 * Mastodon displays a content warning, requiring users to click
	 * through to view the content.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#sensitive
	 *
	 * @var boolean
	 */
	protected $sensitive = null;

	/**
	 * Domains allowed to use `fediverse:creator` for this actor in
	 * published articles.
	 *
	 * @see https://blog.joinmastodon.org/2024/07/highlighting-journalism-on-mastodon/
	 *
	 * @var array
	 */
	protected $attribution_domains = null;
}
