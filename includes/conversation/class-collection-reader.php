<?php
/**
 * Conversation Collection Reader file.
 *
 * @package Activitypub
 */

namespace Activitypub\Conversation;

use Activitypub\Http;

/**
 * Reads the items out of an ActivityStreams collection.
 *
 * Both the `context` collection of FEP-2931 and Mastodon's `replies` are collections that may be
 * split across pages, and nothing in the plugin walked one before this: `Scheduler\Collection_Sync`
 * reads `orderedItems` off the first document and never follows `next`.
 *
 * @since unreleased
 */
class Collection_Reader {

	/**
	 * How many documents one read may fetch.
	 *
	 * Nothing obliges a remote server to end a collection, and a `next` pointing back at itself
	 * is enough to make a reader fetch until the request dies. This is the reader's own floor;
	 * a caller walking several collections is expected to bound the walk as well.
	 *
	 * @var int
	 */
	const MAX_REQUESTS = 50;

	/**
	 * How many items one read may collect.
	 *
	 * Page size is the remote server's choice, so one response can list as many objects as it
	 * likes. Without this the whole list is built in memory before a caller ever sees it.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 500;

	/**
	 * Read the items of a collection.
	 *
	 * @param string|array $collection The collection, or its URI.
	 *
	 * @return array The items, in the order the collection listed them.
	 */
	public static function read( $collection ) {
		$items = array();
		$seen  = array();
		$page  = self::fetch( $collection );

		// Seeded whichever way the collection arrived, or a `next` naming the document we already
		// hold is fetched once before the cycle guard notices. `Context` hands us a fetched array.
		$id = \is_string( $collection ) ? $collection : ( $page['id'] ?? '' );

		if ( $id ) {
			$seen[ $id ] = true;
		}

		while ( $page ) {
			$page_items = $page['orderedItems'] ?? $page['items'] ?? array();
			$items      = \array_merge( $items, $page_items );

			if ( \count( $items ) >= self::MAX_ITEMS ) {
				return \array_slice( $items, 0, self::MAX_ITEMS );
			}

			/*
			 * `next` continues a page. `first` is how a collection defers its items to pages, so
			 * it is only worth following when this document listed none of its own: a collection
			 * that carries both would otherwise hand back the same objects a second time.
			 */
			$next = $page['next'] ?? ( $page_items ? null : ( $page['first'] ?? null ) );

			/*
			 * A repeat means a cycle. The request cap alone would stop it, but only after paying
			 * for the whole budget, and it cannot tell a short loop from a long collection.
			 */
			if ( ! \is_string( $next ) || isset( $seen[ $next ] ) || \count( $seen ) >= self::MAX_REQUESTS ) {
				break;
			}

			$seen[ $next ] = true;
			$page          = self::fetch( $next );
		}

		return $items;
	}

	/**
	 * Fetch one document of a collection.
	 *
	 * @param string|array $document The document, or its URI.
	 *
	 * @return array|null The document, or null when it could not be read.
	 */
	private static function fetch( $document ) {
		if ( \is_string( $document ) ) {
			$document = Http::get_remote_object( $document );
		}

		if ( \is_wp_error( $document ) || ! \is_array( $document ) ) {
			return null;
		}

		return $document;
	}
}
