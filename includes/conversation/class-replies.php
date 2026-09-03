<?php
/**
 * Conversation Replies source file.
 *
 * @package Activitypub
 */

namespace Activitypub\Conversation;

use function Activitypub\object_to_uri;

/**
 * Reaches a conversation by walking `replies` downward.
 *
 * A `replies` collection lists only the direct replies of one object, so seeing a whole thread
 * means descending into the collection of each reply in turn. That makes this the broadest source
 * in practice, since it is what Mastodon publishes, and the most expensive one: the work grows with
 * the thread rather than with the request.
 *
 * It also only ever sees downward. Anything above the object we started from is out of reach here,
 * which is what {@see In_Reply_To} is for.
 *
 * @since unreleased
 *
 * @see https://docs.joinmastodon.org/spec/activitypub/#replies
 */
class Replies implements Source {

	/**
	 * How many levels of replies to descend.
	 *
	 * A thread is a tree of arbitrary depth published by someone else, so the walk needs a floor
	 * of its own rather than trusting the shape it is handed.
	 *
	 * @var int
	 */
	const MAX_DEPTH = 5;

	/**
	 * Whether the object names a replies collection.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return bool True when there are replies to walk.
	 */
	public function supports( $activity_object ) {
		return ! empty( $activity_object['replies'] );
	}

	/**
	 * Collect the replies below the given object.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return array The ActivityPub objects found below it.
	 */
	public function parse( $activity_object ) {
		if ( ! $this->supports( $activity_object ) ) {
			return array();
		}

		$seen = array();

		return $this->descend( $activity_object, 0, $seen );
	}

	/**
	 * Read one object's replies, then those of each reply.
	 *
	 * @param array $activity_object The object whose replies to read.
	 * @param int   $depth           How many levels below the starting object this is.
	 * @param array $seen            Collection URIs already read, keyed by URI.
	 *
	 * @return array The ActivityPub objects found.
	 */
	private function descend( $activity_object, $depth, &$seen ) {
		if ( $depth >= self::MAX_DEPTH || ! $this->supports( $activity_object ) ) {
			return array();
		}

		$uri = object_to_uri( $activity_object['replies'] );

		// A reply naming an ancestor's collection would otherwise walk the thread in circles.
		if ( ! $uri || isset( $seen[ $uri ] ) ) {
			return array();
		}

		$seen[ $uri ] = true;
		$found        = array();

		foreach ( Collection_Reader::read( $uri ) as $reply ) {
			if ( ! \is_array( $reply ) ) {
				continue;
			}

			$found[] = $reply;
			$found   = \array_merge( $found, $this->descend( $reply, $depth + 1, $seen ) );
		}

		return $found;
	}
}
