<?php
/**
 * Inspired by the PHP ActivityPub Library by @Landrok
 *
 * @link https://github.com/landrok/activitypub
 */

namespace Activitypub\Activity;

class Actor extends Activity_Object {
	/**
	 * @var string
	 */
	protected $type = 'Object';

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
}
