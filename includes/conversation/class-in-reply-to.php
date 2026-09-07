<?php
/**
 * Conversation In_Reply_To source file.
 *
 * @package Activitypub
 */

namespace Activitypub\Conversation;

use Activitypub\Http;

use function Activitypub\object_to_uri;

/**
 * Reaches a conversation by climbing `inReplyTo` toward its root.
 *
 * The other sources only ever look level with or below the object they are given. This is the one
 * that looks up, which is where the replies made before the conversation reached us live, and it is
 * the cheapest of the three: one request per ancestor, no branching.
 *
 * Every object carries `inReplyTo`, so this works against servers that publish neither a context
 * nor a replies collection.
 *
 * @since unreleased
 */
class In_Reply_To implements Source {

	/**
	 * How many ancestors to climb.
	 *
	 * The chain is published by someone else and nothing bounds its length, so the climb carries
	 * its own floor.
	 *
	 * @var int
	 */
	const MAX_DEPTH = 10;

	/**
	 * Whether the object replies to something.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return bool True when there is an ancestor to climb to.
	 */
	public function supports( $activity_object ) {
		return ! empty( $activity_object['inReplyTo'] );
	}

	/**
	 * Collect the ancestors of the given object, nearest first.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return array The ActivityPub objects above it.
	 */
	public function parse( $activity_object ) {
		$found = array();
		$seen  = array();
		$depth = 0;

		while ( $this->supports( $activity_object ) && $depth < self::MAX_DEPTH ) {
			$uri = object_to_uri( $activity_object['inReplyTo'] );

			// Objects claiming to reply to each other would otherwise climb forever.
			if ( ! $uri || isset( $seen[ $uri ] ) ) {
				break;
			}

			$seen[ $uri ] = true;
			$parent       = Http::get_remote_object( $uri );

			if ( \is_wp_error( $parent ) || ! \is_array( $parent ) ) {
				break;
			}

			$found[]         = $parent;
			$activity_object = $parent;
			++$depth;
		}

		return $found;
	}
}
