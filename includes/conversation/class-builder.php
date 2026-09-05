<?php
/**
 * Conversation Builder file.
 *
 * @package Activitypub
 */

namespace Activitypub\Conversation;

use Activitypub\Http;

use function Activitypub\is_same_host;
use function Activitypub\object_to_uri;

/**
 * Reconstructs the conversation a remote object belongs to.
 *
 * Takes an object id, runs the registered sources over it, and hands back the objects that make up
 * the conversation in the order they were created. It stores nothing: what the objects become,
 * comments on a local post or cached remote posts, is the caller's decision.
 *
 * The entry point is an id rather than an object so that a walk is serialisable, and can therefore
 * be scheduled and re-queued instead of running inside an inbox request.
 *
 * @since unreleased
 */
class Builder {

	/**
	 * How many objects one conversation may yield.
	 *
	 * A thread is published by someone else and can be arbitrarily large, so the ceiling belongs
	 * here rather than in the caller. Reaching it returns what was gathered: a partial
	 * conversation is still better than none, and the work is already paid for.
	 *
	 * @var int
	 */
	const MAX_OBJECTS = 100;

	/**
	 * The id of the object the conversation is reconstructed from.
	 *
	 * @var string
	 */
	private $object_id;

	/**
	 * Constructor.
	 *
	 * @param string $object_id The id of the object to start from.
	 */
	public function __construct( $object_id ) {
		$this->object_id = $object_id;
	}

	/**
	 * The sources available to a conversation.
	 *
	 * @return Source[] The sources, keyed by name.
	 */
	public static function get_sources() {
		/**
		 * Filters the sources a conversation is reconstructed from.
		 *
		 * Each entry implements {@see Source}. Adding a way of reaching a conversation is a new
		 * entry here rather than a change to the builder.
		 *
		 * @since unreleased
		 *
		 * @param Source[] $sources The sources, keyed by name.
		 */
		return \apply_filters(
			'activitypub_conversation_sources',
			array(
				'context'     => new Context(),
				'replies'     => new Replies(),
				'in_reply_to' => new In_Reply_To(),
			)
		);
	}

	/**
	 * Reconstruct the conversation.
	 *
	 * @param string[]|null $names Optional. Only run the sources with these names. Default all.
	 *
	 * @return array The ActivityPub objects of the conversation, oldest first.
	 */
	public function build( $names = null ) {
		$activity_object = Http::get_remote_object( $this->object_id );

		if ( \is_wp_error( $activity_object ) || ! \is_array( $activity_object ) ) {
			return array();
		}

		$collected = array();
		$this->collect( $activity_object, $collected );

		$sources = self::get_sources();

		if ( \is_array( $names ) ) {
			$sources = \array_intersect_key( $sources, \array_flip( $names ) );
		}

		foreach ( $sources as $source ) {
			// `$collected` only grows, so once it is full no later source can add to it.
			if ( \count( $collected ) >= self::MAX_OBJECTS ) {
				break;
			}

			if ( ! $source->supports( $activity_object ) ) {
				continue;
			}

			foreach ( $source->parse( $activity_object ) as $found ) {
				if ( \count( $collected ) >= self::MAX_OBJECTS ) {
					break;
				}

				$this->collect( $found, $collected );
			}
		}

		return $this->sort( $collected );
	}

	/**
	 * Keep an object, if it is one we may keep and have not already got.
	 *
	 * @param mixed $activity_object The object a source reported.
	 * @param array $collected       The objects kept so far, keyed by id.
	 */
	private function collect( $activity_object, &$collected ) {
		if ( ! \is_array( $activity_object ) ) {
			return;
		}

		$id = $activity_object['id'] ?? '';

		if ( ! $id || isset( $collected[ $id ] ) || ! $this->is_trustworthy( $activity_object ) ) {
			return;
		}

		$collected[ $id ] = $activity_object;
	}

	/**
	 * Whether an object may be taken at face value.
	 *
	 * Nothing here arrived over a signed request: every object was fetched because something else
	 * named it. Membership of a context is the context owner's claim, and FEP-11dd warns it cannot
	 * be relied upon, so each object has to stand on its own under the binding the rest of the
	 * plugin already applies: an id belongs to its author's host.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return bool True when the object may be kept.
	 */
	private function is_trustworthy( $activity_object ) {
		// An object naming no author cannot be bound at all, which would make omitting the
		// property the way around this check.
		return is_same_host( $activity_object['id'], $activity_object['attributedTo'] ?? '' );
	}

	/**
	 * Put the objects in the order they were created.
	 *
	 * `published` is the stated order, but it is the remote server's claim and a reply may carry a
	 * date older than what it replies to. A parent arriving after its child cannot be filed at all,
	 * since `Interactions::add_comment()` resolves `inReplyTo` against what already exists, so the
	 * date sort is followed by a pass that lifts every parent above its replies.
	 *
	 * @param array $objects The collected objects, keyed by id.
	 *
	 * @return array The objects, oldest first.
	 */
	private function sort( $objects ) {
		// Keys are the ids, and `place()` looks a parent up by one, so they have to survive.
		\uasort(
			$objects,
			function ( $a, $b ) {
				return self::published_at( $a ) <=> self::published_at( $b );
			}
		);

		$ordered = array();
		$placed  = array();

		foreach ( $objects as $id => $activity_object ) {
			$this->place( $id, $objects, $ordered, $placed );
		}

		return $ordered;
	}

	/**
	 * The instant an object was published.
	 *
	 * `published` is RFC3339, so one instant has several spellings and an offset sorts against a
	 * `Z` however the digits happen to fall. Comparing the strings would order `09:00:00+02:00`
	 * after `08:00:00Z`, though it is an hour earlier.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return int The Unix timestamp, or zero when there is no usable date.
	 */
	private static function published_at( $activity_object ) {
		return (int) \strtotime( $activity_object['published'] ?? '' );
	}

	/**
	 * Add one object to the ordered list, after whatever it replies to.
	 *
	 * @param string $id      The id of the object to place.
	 * @param array  $objects Every object in the conversation, keyed by id.
	 * @param array  $ordered The objects placed so far.
	 * @param array  $placed  Ids already placed, keyed by id.
	 */
	private function place( $id, $objects, &$ordered, &$placed ) {
		if ( ! isset( $objects[ $id ] ) || isset( $placed[ $id ] ) ) {
			return;
		}

		// Marked before the parent is placed, so a cycle cannot recurse forever.
		$placed[ $id ] = true;

		$parent_id = object_to_uri( $objects[ $id ]['inReplyTo'] ?? '' );

		if ( $parent_id ) {
			$this->place( $parent_id, $objects, $ordered, $placed );
		}

		$ordered[] = $objects[ $id ];
	}
}
