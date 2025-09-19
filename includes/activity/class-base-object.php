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
 * Base_Object is an implementation of one of the
 * Activity Streams Core Types.
 *
 * The Object is the primary base type for the Activity Streams
 * vocabulary.
 *
 * Note: Object is a reserved keyword in PHP. It has been suffixed with
 * 'Base_' for this reason.
 *
 * @see https://www.w3.org/TR/activitystreams-core/#object
 */
class Base_Object extends Generic_Object {
	/**
	 * The JSON-LD context for the object.
	 *
	 * @var array
	 */
	const JSON_LD_CONTEXT = array(
		'https://www.w3.org/ns/activitystreams',
		array(
			'Hashtag'           => 'as:Hashtag',
			'sensitive'         => 'as:sensitive',
			'dcterms'           => 'http://purl.org/dc/terms/',
			'gts'               => 'https://gotosocial.org/ns#',
			'interactionPolicy' => array(
				'@id'   => 'gts:interactionPolicy',
				'@type' => '@id',
			),
			'canQuote'          => array(
				'@id'   => 'gts:canQuote',
				'@type' => '@id',
			),
			'canReply'          => array(
				'@id'   => 'gts:canReply',
				'@type' => '@id',
			),
			'canLike'           => array(
				'@id'   => 'gts:canLike',
				'@type' => '@id',
			),
			'canAnnounce'       => array(
				'@id'   => 'gts:canAnnounce',
				'@type' => '@id',
			),
			'automaticApproval' => array(
				'@id'   => 'gts:automaticApproval',
				'@type' => '@id',
			),
			'manualApproval'    => array(
				'@id'   => 'gts:manualApproval',
				'@type' => '@id',
			),
			'always'            => array(
				'@id'   => 'gts:always',
				'@type' => '@id',
			),
		),
	);

	/**
	 * The default types for Objects.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#object-types
	 *
	 * @var array
	 */
	const TYPES = array(
		'Article',
		'Audio',
		'Document',
		'Event',
		'Image',
		'Note',
		'Page',
		'Place',
		'Profile',
		'Relationship',
		'Tombstone',
		'Video',
	);

	/**
	 * The type of the object.
	 *
	 * @var string
	 */
	protected $type = 'Object';

	/**
	 * A resource attached or related to an object that potentially
	 * requires special handling.
	 * The intent is to provide a model that is at least semantically
	 * similar to attachments in email.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-attachment
	 *
	 * @var string|null
	 */
	protected $attachment;

	/**
	 * One or more entities to which this object is attributed.
	 * The attributed entities might not be Actors. For instance, an
	 * object might be attributed to the completion of another activity.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-attributedto
	 *
	 * @var string|null
	 */
	protected $attributed_to;

	/**
	 * One or more entities that represent the total population of
	 * entities for which the object can be considered to be relevant.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-audience
	 *
	 * @var string|null
	 */
	protected $audience;

	/**
	 * The content or textual representation of the Object encoded as a
	 * JSON string. By default, the value of content is HTML.
	 * The mediaType property can be used in the object to indicate a
	 * different content type.
	 *
	 * The content MAY be expressed using multiple language-tagged
	 * values.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-content
	 *
	 * @var string|null
	 */
	protected $content;

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
	 * @var string|null
	 */
	protected $context;

	/**
	 * The content MAY be expressed using multiple language-tagged
	 * values.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-content
	 *
	 * @var array|null
	 */
	protected $content_map;

	/**
	 * A simple, human-readable, plain-text name for the object.
	 * HTML markup MUST NOT be included.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-name
	 *
	 * @var string|null xsd:string
	 */
	protected $name;

	/**
	 * The name MAY be expressed using multiple language-tagged values.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-name
	 *
	 * @var array|null rdf:langString
	 */
	protected $name_map;

	/**
	 * The date and time describing the actual or expected ending time
	 * of the object.
	 * When used with an Activity object, for instance, the endTime
	 * property specifies the moment the activity concluded or
	 * is expected to conclude.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-endtime
	 *
	 * @var string|null
	 */
	protected $end_time;

	/**
	 * The entity (e.g. an application) that generated the object.
	 *
	 * @var string|null
	 */
	protected $generator;

	/**
	 * An entity that describes an icon for this object.
	 * The image should have an aspect ratio of one (horizontal)
	 * to one (vertical) and should be suitable for presentation
	 * at a small size.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-icon
	 *
	 * @var string|array|null
	 */
	protected $icon;

	/**
	 * An entity that describes an image for this object.
	 * Unlike the icon property, there are no aspect ratio
	 * or display size limitations assumed.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-image-term
	 *
	 * @var string|array|null
	 */
	protected $image;

	/**
	 * One or more entities for which this object is considered a
	 * response.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-inreplyto
	 *
	 * @var string|null
	 */
	protected $in_reply_to;

	/**
	 * One or more physical or logical locations associated with the
	 * object.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-location
	 *
	 * @var string|null
	 */
	protected $location;

	/**
	 * An entity that provides a preview of this object.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-preview
	 *
	 * @var string|null
	 */
	protected $preview;

	/**
	 * The date and time at which the object was published
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-published
	 *
	 * @var string|null xsd:dateTime
	 */
	protected $published;

	/**
	 * The date and time describing the actual or expected starting time
	 * of the object.
	 * When used with an Activity object, for instance, the startTime
	 * property specifies the moment the activity began
	 * or is scheduled to begin.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-starttime
	 *
	 * @var string|null xsd:dateTime
	 */
	protected $start_time;

	/**
	 * A natural language summarization of the object encoded as HTML.
	 * Multiple language tagged summaries MAY be provided.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-summary
	 *
	 * @var string|null
	 */
	protected $summary;

	/**
	 * The content MAY be expressed using multiple language-tagged
	 * values.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-summary
	 *
	 * @var string[]|null
	 */
	protected $summary_map;

	/**
	 * One or more "tags" that have been associated with an objects.
	 * A tag can be any kind of Object.
	 * The key difference between attachment and tag is that the former
	 * implies association by inclusion, while the latter implies
	 * associated by reference.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-tag
	 *
	 * @var string|null
	 */
	protected $tag;

	/**
	 * The date and time at which the object was updated
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-updated
	 *
	 * @var string|null xsd:dateTime
	 */
	protected $updated;

	/**
	 * One or more links to representations of the object.
	 *
	 * @var string|null
	 */
	protected $url;

	/**
	 * An entity considered to be part of the public primary audience
	 * of an Object
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-to
	 *
	 * @var string|array|null
	 */
	protected $to;

	/**
	 * An Object that is part of the private primary audience of this
	 * Object.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-bto
	 *
	 * @var string|array|null
	 */
	protected $bto;

	/**
	 * An Object that is part of the public secondary audience of this
	 * Object.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-cc
	 *
	 * @var string|array|null
	 */
	protected $cc;

	/**
	 * One or more Objects that are part of the private secondary
	 * audience of this Object.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-bcc
	 *
	 * @var string|array|null
	 */
	protected $bcc;

	/**
	 * The MIME media type of the value of the content property.
	 * If not specified, the content property is assumed to contain
	 * text/html content.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-mediatype
	 *
	 * @var string|null
	 */
	protected $media_type;

	/**
	 * When the object describes a time-bound resource, such as an audio
	 * or video, a meeting, etc., the duration property indicates the
	 * object's approximate duration.
	 * The value MUST be expressed as a xsd:duration as defined by
	 * xmlschema11-2, section 3.3.6 (e.g. a period of 5 seconds is
	 * represented as "PT5S").
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-duration
	 *
	 * @var string|null
	 */
	protected $duration;

	/**
	 * Intended to convey some sort of source from which the content
	 * markup was derived, as a form of provenance, or to support
	 * future editing by clients.
	 *
	 * @see https://www.w3.org/TR/activitypub/#source-property
	 *
	 * @var array|null
	 */
	protected $source;

	/**
	 * A Collection containing objects considered to be responses to
	 * this object.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-replies
	 *
	 * @var string|array|null
	 */
	protected $replies;

	/**
	 * A Collection containing objects considered to be likes for
	 * this object.
	 *
	 * @see https://www.w3.org/TR/activitypub/#likes
	 *
	 * @var array|null
	 */
	protected $likes;

	/**
	 * A Collection containing objects considered to be shares for
	 * this object.
	 *
	 * @see https://www.w3.org/TR/activitypub/#shares
	 *
	 * @var array|null
	 */
	protected $shares;

	/**
	 * Used to mark an object as containing sensitive content.
	 * Mastodon displays a content warning, requiring users to click
	 * through to view the content.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#sensitive
	 *
	 * @var boolean|null
	 */
	protected $sensitive;

	/**
	 * The dcterms namespace.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/b2b8/fep-b2b8.md#sensitive
	 * @see https://www.dublincore.org/specifications/dublin-core/dcmi-terms/
	 *
	 * @var array|null
	 */
	protected $dcterms;

	/**
	 * Interaction policy is an attempt to limit the harmful effects of unwanted replies and
	 * other interactions on a user's posts (e.g., "reply guys").
	 *
	 * It is also used by Mastodon to limit the ability to quote posts.
	 *
	 * @see https://docs.gotosocial.org/en/latest/federation/interaction_policy/
	 * @see https://blog.joinmastodon.org/2025/09/introducing-quote-posts/
	 *
	 * @var array|null
	 */
	protected $interaction_policy;

	/**
	 * Generic getter.
	 *
	 * @param string $key The key to get.
	 *
	 * @return mixed The value.
	 */
	public function get( $key ) {
		if ( ! $this->has( $key ) ) {
			return new \WP_Error( 'invalid_key', __( 'Invalid key', 'activitypub' ), array( 'status' => 404 ) );
		}

		return parent::get( $key );
	}

	/**
	 * Generic setter.
	 *
	 * @param string $key   The key to set.
	 * @param string $value The value to set.
	 *
	 * @return mixed The value.
	 */
	public function set( $key, $value ) {
		if ( ! $this->has( $key ) ) {
			return new \WP_Error( 'invalid_key', __( 'Invalid key', 'activitypub' ), array( 'status' => 404 ) );
		}

		return parent::set( $key, $value );
	}

	/**
	 * Generic adder.
	 *
	 * @param string $key   The key to set.
	 * @param mixed  $value The value to add.
	 *
	 * @return mixed The value.
	 */
	public function add( $key, $value ) {
		if ( ! $this->has( $key ) ) {
			return new \WP_Error( 'invalid_key', __( 'Invalid key', 'activitypub' ), array( 'status' => 404 ) );
		}

		return parent::add( $key, $value );
	}
}
