<?php
/**
 * Conversation Context source file.
 *
 * @package Activitypub
 */

namespace Activitypub\Conversation;

use Activitypub\Http;

use function Activitypub\is_collection;
use function Activitypub\object_to_uri;

/**
 * Reaches a conversation through the `context` property.
 *
 * FEP-7888 makes `context` a resolvable pointer at whatever groups objects together, and FEP-2931
 * says that when it resolves to a Collection, that collection is the canonical membership of the
 * context, to be iterated for backfill. That is the one shape which tells us what belongs to the
 * conversation, so a context resolving to anything else is not a source of objects here.
 *
 * When it is available this is the cheapest source by far: one collection for the whole
 * conversation, already in creation order, rather than a request per object.
 *
 * @since unreleased
 *
 * @see https://fediverse.codeberg.page/fep/fep/7888/
 * @see https://fediverse.codeberg.page/fep/fep/2931/
 */
class Context implements Source {

	/**
	 * Whether the object names a context.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return bool True when there is a context to resolve.
	 */
	public function supports( $activity_object ) {
		return ! empty( $activity_object['context'] );
	}

	/**
	 * Read the objects listed by the object's context collection.
	 *
	 * @param array $activity_object The ActivityPub object.
	 *
	 * @return array The ActivityPub objects in the context.
	 */
	public function parse( $activity_object ) {
		if ( ! $this->supports( $activity_object ) ) {
			return array();
		}

		$context = Http::get_remote_object( object_to_uri( $activity_object['context'] ) );

		if ( \is_wp_error( $context ) || ! \is_array( $context ) || ! is_collection( $context ) ) {
			return array();
		}

		return Collection_Reader::read( $context );
	}
}
