<?php
/**
 * ActivityPub Object of type Event.
 *
 * @package activity-event-transformers
 * @license AGPL-3.0-or-later
 */

namespace Activitypub\Activity\Objects;

use Activitypub\Activity\Base_Object;

/**
 * Event is an implementation of one of the Activity Streams Event object type.
 *
 * This class contains extra keys as used by Mobilizon to ensure compatibility.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-event
 */
class Event extends Base_Object {
	// Human friendly minimal context for full Mobilizon compatible ActivityPub events.
	const JSON_LD_CONTEXT = array(
		'https://schema.org/',                   // The base context is schema.org, cause it is used a lot.
		'https://www.w3.org/ns/activitystreams', // The ActivityStreams context overrides everyting also defined in schema.org.
		array(                                   // The keys here override/extend the context even more.
			'pt'                            => 'https://joinpeertube.org/ns#',
			'mz'                            => 'https://joinmobilizon.org/ns#',
			'status'                        => 'http://www.w3.org/2002/12/cal/ical#status',
			'commentsEnabled'               => 'pt:commentsEnabled',
			'isOnline'                      => 'mz:isOnline',
			'timezone'                      => 'mz:timezone',
			'participantCount'              => 'mz:participantCount',
			'anonymousParticipationEnabled' => 'mz:anonymousParticipationEnabled',
			'joinMode'                      => array(
				'@id'   => 'mz:joinMode',
				'@type' => 'mz:joinModeType',
			),
			'externalParticipationUrl'      => array(
				'@id'   => 'mz:externalParticipationUrl',
				'@type' => 'schema:URL',
			),
			'repliesModerationOption'       => array(
				'@id'   => 'mz:repliesModerationOption',
				'@type' => '@vocab',
			),
			'contacts'                      => array(
				'@id'   => 'mz:contacts',
				'@type' => '@id',
			),
		),
	);

	/**
	 * Mobilizon compatible values for repliesModertaionOption.
	 * @var array
	 */
	const REPLIES_MODERATION_OPTION_TYPES = array( 'allow_all', 'closed' );

	/**
	 * Mobilizon compatible values for joinModeTypes.
	 */
	const JOIN_MODE_TYPES = array( 'free', 'restricted', 'external' ); // and 'invite', but not used by mobilizon atm

	/**
	 * Allowed values for ical VEVENT STATUS.
	 * @var array
	 */
	const ICAL_EVENT_STATUS_TYPES = array( 'TENTATIVE', 'CONFIRMED', 'CANCELLED' );

	/**
	 * Default event categories.
	 *
	 * These values currently reflect the default set as proposed by Mobilizon to maximize interoperability.
	 * @var array
	 */
	const DEFAULT_EVENT_CATEGORIES = array(
		'ARTS',
		'BOOK_CLUBS',
		'BUSINESS',
		'CAUSES',
		'COMEDY',
		'CRAFTS',
		'FOOD_DRINK',
		'HEALTH',
		'MUSIC',
		'AUTO_BOAT_AIR',
		'COMMUNITY',
		'FAMILY_EDUCATION',
		'FASHION_BEAUTY',
		'FILM_MEDIA',
		'GAMES',
		'LANGUAGE_CULTURE',
		'LEARNING',
		'LGBTQ',
		'MOVEMENTS_POLITICS',
		'NETWORKING',
		'PARTY',
		'PERFORMING_VISUAL_ARTS',
		'PETS',
		'PHOTOGRAPHY',
		'OUTDOORS_ADVENTURE',
		'SPIRITUALITY_RELIGION_BELIEFS',
		'SCIENCE_TECH',
		'SPORTS',
		'THEATRE',
		'MEETING', // Default value.
	);

	/**
	 * Event is an implementation of one of the
	 * Activity Streams
	 *
	 * @var string
	 */
	protected $type = 'Event';

	/**
	 * The Title of the event.
	 */
	protected $name;

	/**
	 * The events contacts
	 *
	 * @context {
	 *   '@id'   => 'mz:contacts',
	 *   '@type' => '@id',
	 * }
	 *
	 * @var array Array of contacts (ActivityPub actor IDs).
	 */
	protected $contacts;

	/**
	 * Extension invented by PeerTube whether comments/replies are <enabled>
	 * Mobilizon also implemented this as a fallback to their own
	 * repliesModerationOption.
	 *
	 * @see https://docs.joinpeertube.org/api/activitypub#video
	 * @see https://docs.joinmobilizon.org/contribute/activity_pub/
	 * @var bool|null
	 */
	protected $comments_enabled;

	/**
	 * @context https://joinmobilizon.org/ns#timezone
	 * @var string
	 */
	protected $timezone;

	/**
	 * @context https://joinmobilizon.org/ns#repliesModerationOption
	 * @see https://docs.joinmobilizon.org/contribute/activity_pub/#repliesmoderation
	 * @var string
	 */
	protected $replies_moderation_option;

	/**
	 * @context https://joinmobilizon.org/ns#anonymousParticipationEnabled
	 * @see https://docs.joinmobilizon.org/contribute/activity_pub/#anonymousparticipationenabled
	 * @var bool
	 */
	protected $anonymous_participation_enabled;

	/**
	 * @context https://schema.org/category
	 * @var enum
	 */
	protected $category;

	/**
	 * @context https://schema.org/inLanguage
	 * @var
	 */
	protected $in_language;

	/**
	 * @context https://joinmobilizon.org/ns#isOnline
	 * @var bool
	 */
	protected $is_online;

	/**
	 * @context https://www.w3.org/2002/12/cal/ical#status
	 * @var enum
	 */
	protected $status;

	/**
	 * Which actor created the event.
	 *
	 * This field is needed due to the current group structure of Mobilizon.
	 *
	 * @todo this seems to not be a default property of an Object but needed by mobilizon.
	 * @var string
	 */
	protected $actor;

	/**
	 * @context https://joinmobilizon.org/ns#externalParticipationUrl
	 * @var string
	 */
	protected $external_participation_url;

	/**
	 * @context https://joinmobilizon.org/ns#joinMode
	 * @see https://docs.joinmobilizon.org/contribute/activity_pub/#joinmode
	 * @var
	 */
	protected $join_mode;

	/**
	 * @context https://joinmobilizon.org/ns#participantCount
	 * @var int
	 */
	protected $participant_count;

	/**
	 * @context https://schema.org/maximumAttendeeCapacity
	 * @see https://docs.joinmobilizon.org/contribute/activity_pub/#maximumattendeecapacity
	 * @var int
	 */
	protected $maximum_attendee_capacity;

	/**
	 * @context https://schema.org/remainingAttendeeCapacity
	 * @see https://docs.joinmobilizon.org/contribute/activity_pub/#remainignattendeecapacity
	 * @var int
	 */
	protected $remaining_attendee_capacity;

	/**
	 * Setter for the timezone.
	 *
	 * The passed timezone is only set when it is a valid one, otherwise the site's timezone is used.
	 *
	 * @param string $timezone The timezone string to be set, e.g. 'Europe/Berlin'.
	 */
	public function set_timezone( $timezone ) {
		if ( in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$this->timezone = $timezone;
		} else {
			$this->timezone = wp_timezone_string();
		}

		return $this;
	}

	/**
	 * Custom setter for repliesModerationOption which also directy sets commentsEnabled accordingly.
	 *
	 * @param string $type
	 */
	public function set_replies_moderation_option( $type ) {
		if ( in_array( $type, self::REPLIES_MODERATION_OPTION_TYPES, true ) ) {
			$this->replies_moderation_option = $type;
			$this->comments_enabled = ( 'allow_all' === $type ) ? true : false;
		} else {
			_doing_it_wrong(
				__METHOD__,
				'The replies moderation option must be either allow_all or closed.',
				'<version_placeholder>'
			);
		}

		return $this;
	}

	/**
	 * Custom setter for commentsEnabled which also directly sets repliesModerationOption accordingly.
	 *
	 * @param bool $comments_enabled
	 */
	public function set_comments_enabled( $comments_enabled ) {
		if ( is_bool( $comments_enabled ) ) {
			$this->comments_enabled = $comments_enabled;
			$this->replies_moderation_option = $comments_enabled ? 'allow_all' : 'closed';
		} else {
			_doing_it_wrong(
				__METHOD__,
				'The commentsEnabled must be boolean.',
				'<version_placeholder>'
			);
		}

		return $this;
	}

	/**
	 * Custom setter for the ical status that checks whether the status is an ical event status.
	 *
	 * @param string $status
	 */
	public function set_status( $status ) {
		if ( in_array( $status, self::ICAL_EVENT_STATUS_TYPES, true ) ) {
			$this->status = $status;
		} else {
			_doing_it_wrong(
				__METHOD__,
				'The status of the event must be a VEVENT iCal status.',
				'<version_placeholder>'
			);
		}

		return $this;
	}

	/**
	 * Custom setter for the event category.
	 *
	 * Falls back to Mobilizons default category.
	 *
	 * @param string $category
	 * @param bool   $mobilizon_compatibilty Whether the category must be compatibly with Mobilizon.
	 */
	public function set_category( $category, $mobilizon_compatibilty = true ) {
		if ( $mobilizon_compatibilty ) {
			$this->category = in_array( $category, self::DEFAULT_EVENT_CATEGORIES, true ) ? $category : 'MEETING';
		} else {
			$this->category = $category;
		}

		return $this;
	}

	/**
	 * Custom setter for an external participation url.
	 *
	 * Automatically sets the joinMode to true if called.
	 *
	 * @param string $url
	 */
	public function set_external_participation_url( $url ) {
		if ( preg_match( '/^https?:\/\/.*/i', $url ) ) {
			$this->external_participation_url = $url;
			$this->join_mode = 'external';
		}

		return $this;
	}
}
