<?php
/**
 * Conversation Source interface file.
 *
 * @package Activitypub
 */

namespace Activitypub\Conversation;

/**
 * One way of reaching the rest of a conversation.
 *
 * A remote object can point at its conversation through more than one property, and no
 * implementation publishes all of them. Each source knows about one of those properties, so support
 * for another is a new class rather than an edit to an existing one.
 *
 * @since unreleased
 */
interface Source {

	/**
	 * Whether this source can find anything from the given object.
	 *
	 * Answered without fetching, so the caller can skip a request it does not need to make.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return bool True when the object exposes what this source reads.
	 */
	public function supports( $activity_object );

	/**
	 * Collect the objects this source can reach.
	 *
	 * Ordering is the caller's business: a source returns what it found, in whatever order it
	 * found it.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return array The ActivityPub objects discovered.
	 */
	public function parse( $activity_object );
}
