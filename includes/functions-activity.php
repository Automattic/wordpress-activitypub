<?php
/**
 * Activity functions.
 *
 * Functions for working with ActivityPub activities, objects, and actors.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Actor;
use Activitypub\Activity\Base_Object;

/**
 * Returns the ActivityPub default JSON-context.
 *
 * @return array The activitypub context.
 *
 * @deprecated 7.6.0 Use the respective context function instead.
 */
function get_context() {
	\_deprecated_function( __FUNCTION__, '7.6.0', 'Use the respective context function instead.' );

	$context = Activity::JSON_LD_CONTEXT;

	/**
	 * Filters the ActivityPub JSON-LD context.
	 *
	 * This filter allows developers to modify or extend the JSON-LD context used
	 * in ActivityPub responses. The context defines the vocabulary and terms used
	 * in the ActivityPub JSON objects.
	 *
	 * @param array $context The default ActivityPub JSON-LD context array.
	 */
	return \apply_filters( 'activitypub_json_context', $context );
}

/**
 * Extract recipient URLs from Activity object.
 *
 * @param array $data The Activity object as array.
 *
 * @return array The list of user URLs.
 */
function extract_recipients_from_activity( $data ) {
	$recipient_items = array();

	foreach ( array( 'to', 'bto', 'cc', 'bcc', 'audience' ) as $i ) {
		$recipient_items = \array_merge( $recipient_items, extract_recipients_from_activity_property( $i, $data ) );
	}

	// An Accept/Reject that wraps a Follow is addressed only through the embedded Follow's actor.
	if (
		\in_array( $data['type'], array( 'Accept', 'Reject' ), true ) &&
		! empty( $data['object'] ) &&
		\is_array( $data['object'] ) &&
		! empty( $data['object']['actor'] )
	) {
		$recipient_items[] = object_to_uri( $data['object']['actor'] );
	}

	return \array_unique( \array_filter( $recipient_items ) );
}

/**
 * Extract recipient URLs from a specific property of an Activity object.
 *
 * Checks the activity level first, then falls back to the object property,
 * and finally checks the instrument property (used by QuoteRequest activities).
 *
 * @param string $property The property to extract recipients from (e.g., 'to', 'cc').
 * @param array  $data     The Activity object as array.
 *
 * @return array The list of user URLs.
 */
function extract_recipients_from_activity_property( $property, $data ) {
	$recipients = array();

	if ( ! empty( $data[ $property ] ) ) {
		$recipients = $data[ $property ];
	} elseif ( ! empty( $data['object'][ $property ] ) ) {
		$recipients = $data['object'][ $property ];
	} elseif ( ! empty( $data['instrument'][ $property ] ) ) {
		// QuoteRequest activities have addressing in the instrument (the quoting Note).
		$recipients = $data['instrument'][ $property ];
	}

	$recipients = \array_map( '\Activitypub\object_to_uri', (array) $recipients );

	return \array_unique( \array_filter( $recipients ) );
}

/**
 * Determine the visibility of the activity based on its recipients.
 *
 * @param array $activity The activity data.
 *
 * @return string One of ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
 *                ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC, or
 *                ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE.
 */
function get_activity_visibility( $activity ) {
	// Set default visibility for specific activity types.
	if ( ! empty( $activity['type'] ) && \in_array( $activity['type'], array( 'Accept', 'Delete', 'Follow', 'Reject', 'Undo' ), true ) ) {
		return ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
	}

	// Check 'to' field for public visibility.
	$to = extract_recipients_from_activity_property( 'to', $activity );
	if ( ! empty( \array_intersect( $to, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS ) ) ) {
		return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
	}

	// Check 'cc' field for quiet public visibility.
	$cc = extract_recipients_from_activity_property( 'cc', $activity );
	if ( ! empty( \array_intersect( $cc, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS ) ) ) {
		return ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC;
	}

	return ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
}

/**
 * Check if passed Activity is Public.
 *
 * @see https://github.com/w3c/activitypub/issues/404#issuecomment-2926310561
 * @see https://www.w3.org/TR/activitypub/#delivery (Section 7.1, "Silent and private activities")
 *
 * @param Base_Object|array $data The Activity object as Base_Object or array.
 *
 * @return boolean True if public, false if not.
 */
function is_activity_public( $data ) {
	if ( $data instanceof Base_Object ) {
		$data = $data->to_array();
	}

	$recipients = extract_recipients_from_activity( $data );

	if ( empty( $recipients ) ) {
		return false;
	}

	return ! empty( \array_intersect( $recipients, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS ) );
}

/**
 * Check if passed Activity is a reply.
 *
 * @param array $data The Activity object as array.
 *
 * @return boolean True if a reply, false if not.
 */
function is_activity_reply( $data ) {
	return ! empty( $data['object']['inReplyTo'] );
}

/**
 * Check if passed Activity is a quote.
 *
 * Checks for quote properties: quote, quoteUrl, quoteUri, or _misskey_quote.
 *
 * @param array $data The Activity object as array.
 *
 * @return boolean True if a quote, false if not.
 */
function is_quote_activity( $data ) {
	return ! empty( $data['object']['quote'] ) ||
		! empty( $data['object']['quoteUrl'] ) ||
		! empty( $data['object']['quoteUri'] ) ||
		! empty( $data['object']['_misskey_quote'] );
}

/**
 * Get the URI of an ActivityPub object.
 *
 * @param array|string $data The ActivityPub object.
 *
 * @return string|null The URI of the ActivityPub object.
 */
function object_to_uri( $data ) {
	// Check whether it is already simple.
	if ( ! $data || \is_string( $data ) ) {
		return $data;
	}

	if ( \is_object( $data ) ) {
		$data = $data->to_array();
	}

	/*
	 * Check if it is a list, then take first item.
	 * This plugin does not support collections.
	 */
	if ( \array_is_list( $data ) ) {
		$data = $data[0];
	}

	// Check if it is simplified now.
	if ( \is_string( $data ) ) {
		return $data;
	}

	$type = 'Object';
	if ( isset( $data['type'] ) ) {
		$type = $data['type'];
	}

	// Return part of Object that makes most sense.
	switch ( $type ) {
		case 'Audio':    // See https://www.w3.org/TR/activitystreams-vocabulary/#dfn-audio.
		case 'Document': // See https://www.w3.org/TR/activitystreams-vocabulary/#dfn-document.
		case 'Image':    // See https://www.w3.org/TR/activitystreams-vocabulary/#dfn-image.
		case 'Video':    // See https://www.w3.org/TR/activitystreams-vocabulary/#dfn-video.
			$data = object_to_uri( $data['url'] );
			break;

		case 'Link':     // See https://www.w3.org/TR/activitystreams-vocabulary/#dfn-link.
		case 'Mention':  // See https://www.w3.org/TR/activitystreams-vocabulary/#dfn-mention.
			$data = $data['href'];
			break;

		case 'FeaturedItem': // See https://github.com/mastodon/featured_collections/pull/1.
			$data = object_to_uri( $data['featuredObject'] ?? null );
			break;

		default:
			if ( isset( $data['id'] ) ) {
				$data = $data['id'];
			} elseif ( isset( $data['url'] ) ) {
				$data = object_to_uri( $data['url'] );
			} elseif ( isset( $data['href'] ) ) {
				$data = $data['href'];
			} else {
				$data = null;
			}
			break;
	}

	return $data;
}

/**
 * Check whether two references point at the same actor.
 *
 * Both values are resolved to their canonical URI via object_to_uri() before
 * comparison. Empty references never match, so a missing actor can never be
 * mistaken for a match.
 *
 * @param array|object|string $a The first actor reference.
 * @param array|object|string $b The second actor reference.
 *
 * @return bool True when both resolve to the same non-empty URI.
 */
function is_same_actor( $a, $b ) {
	$a = object_to_uri( $a );
	$b = object_to_uri( $b );

	return ! empty( $a ) && ! empty( $b ) && $a === $b;
}

/**
 * Check whether two references live on the same host.
 *
 * Both values are resolved to their canonical URI via object_to_uri(), then
 * their hosts are compared case-insensitively. Empty references, or references
 * without a host, never match.
 *
 * @param array|object|string $a The first reference.
 * @param array|object|string $b The second reference.
 *
 * @return bool True when both resolve to a URI on the same host.
 */
function is_same_host( $a, $b ) {
	$host_a = \wp_parse_url( (string) object_to_uri( $a ), PHP_URL_HOST );
	$host_b = \wp_parse_url( (string) object_to_uri( $b ), PHP_URL_HOST );

	return ! empty( $host_a ) && ! empty( $host_b ) && \strtolower( $host_a ) === \strtolower( $host_b );
}

/**
 * Whether an object is served under its own canonical id.
 *
 * An object is only trustworthy to cache when its own `id` is the URL it was
 * actually served from: otherwise one host could serve a document — and its
 * public key — under another host's id. Reads the raw `id` attribute only
 * (never the `url`/`href` fallback that object_to_uri() applies), because the
 * cache is keyed on `id`, so "is this canonical?" must ask the same field the
 * write uses. The comparison ignores the URL fragment and a trailing slash;
 * everything else (scheme, host, port, path, query) must match exactly.
 * Host-level equality is deliberately NOT enough — any different id on the same
 * host is still a distinct cache entry that a document served elsewhere must not write.
 *
 * @param array|string $item The fetched object, or its id.
 * @param string       $url  The URL the object was served from.
 *
 * @return bool True when the object's id is the canonical URL it was served from.
 */
function id_matches_url( $item, $url ) {
	if ( \is_array( $item ) ) {
		$id = isset( $item['id'] ) && \is_string( $item['id'] ) ? $item['id'] : '';
	} elseif ( \is_string( $item ) ) {
		$id = $item;
	} else {
		$id = '';
	}

	$id  = \strip_fragment_from_url( $id );
	$url = \strip_fragment_from_url( (string) $url );

	if ( '' === $id || '' === $url ) {
		return false;
	}

	return \untrailingslashit( $id ) === \untrailingslashit( $url );
}

/**
 * Normalize an actor URI so two spellings of the same identity compare equal.
 *
 * Folds only what RFC 3986 calls case-insensitive, the scheme and host, plus a default port
 * and a trailing slash, and drops the fragment. Path and query keep their case, and `http`
 * stays distinct from `https`. Userinfo is dropped, so a few technically distinct URIs compare
 * equal, which errs towards matching a block rather than missing one.
 *
 * Deliberately not used by `id_matches_url()`, which guards a cache write keyed on the exact
 * id: folding there would confirm a document under one spelling and store it under another.
 * A mismatch there is not a rejection, {@see \Activitypub\Http::get_remote_object()} re-fetches
 * the declared id and requires that to self-confirm, so the strictness costs one request rather
 * than refusing a document that spells its own host differently.
 *
 * @since 9.3.0
 *
 * @param string $uri The actor URI.
 *
 * @return string The normalized URI, or an empty string when there is nothing to compare.
 */
function normalize_actor_uri( $uri ) {
	$uri = \is_string( $uri ) ? \trim( $uri ) : '';

	if ( '' === $uri ) {
		return '';
	}

	/*
	 * Cut at the first `#`, which is the only place a fragment can start, rather than through
	 * `strip_fragment_from_url()`, which rebuilds from parsed parts and so leaves a hostless
	 * identifier alone. Without this a fragment on a handle survives normalization and
	 * `acct:user@example.com#x` slips past a block on `acct:user@example.com`. The host branch
	 * never re-appends one either way.
	 */
	$fragment = \strpos( $uri, '#' );

	if ( false !== $fragment ) {
		$uri = \substr( $uri, 0, $fragment );
	}

	$parts = \wp_parse_url( $uri );

	/*
	 * A handle has no parsable host, so its own host half is folded on its own. Anything else
	 * without one is malformed and is compared as it came in.
	 */
	if ( empty( $parts['host'] ) ) {
		// `acct:` and a leading `@` are spellings of the same handle, so they are dropped before
		// comparing. The local part keeps its case; only the host half is folded.
		$handle = \preg_replace( '/^acct:/i', '', $uri );
		$handle = \ltrim( $handle, '@' );

		if ( \preg_match( '/^(.*@)([^@]+)$/', $handle, $parsed ) ) {
			return $parsed[1] . fold_host( $parsed[2] );
		}

		return \untrailingslashit( $uri );
	}

	static $default = array(
		'http'  => 80,
		'https' => 443,
	);

	$scheme = \strtolower( $parts['scheme'] ?? '' );

	// A port that is the scheme's default is the same address written two ways.
	$is_default = isset( $parts['port'] ) && \array_key_exists( $scheme, $default ) && $default[ $scheme ] === (int) $parts['port'];
	$port       = isset( $parts['port'] ) && ! $is_default ? ':' . (int) $parts['port'] : '';

	// The trailing slash is folded on the path rather than the whole URI so a query cannot hide it.
	// A scheme-relative reference keeps its `//`; emitting `://` would match nothing.
	$authority  = ( '' === $scheme ? '//' : $scheme . '://' ) . fold_host( $parts['host'] );
	$normalized = $authority . $port . \untrailingslashit( $parts['path'] ?? '' );

	return isset( $parts['query'] ) ? $normalized . '?' . $parts['query'] : $normalized;
}

/**
 * Check if an `$data` is an Activity.
 *
 * @see https://www.w3.org/ns/activitystreams#activities
 *
 * @param array|object|string $data The data to check.
 *
 * @return boolean True if the `$data` is an Activity, false otherwise.
 */
function is_activity( $data ) {
	/**
	 * Filters the activity types.
	 *
	 * @param array $types The activity types.
	 */
	$types = \apply_filters( 'activitypub_activity_types', Activity::TYPES );

	return _is_type_of( $data, $types );
}

/**
 * Check if an `$data` is an Activity Object.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#object-types
 *
 * @param array|object|string $data The data to check.
 *
 * @return boolean True if the `$data` is an Activity Object, false otherwise.
 */
function is_activity_object( $data ) {
	/**
	 * Filters the activity object types.
	 *
	 * @param array $types The activity object types.
	 */
	$types = \apply_filters( 'activitypub_activity_object_types', Base_Object::TYPES );

	return _is_type_of( $data, $types );
}

/**
 * Check if an `$data` is an Actor.
 *
 * @see https://www.w3.org/ns/activitystreams#actor
 *
 * @param array|object|string $data The data to check.
 *
 * @return boolean True if the `$data` is an Actor, false otherwise.
 */
function is_actor( $data ) {
	/**
	 * Filters the actor types.
	 *
	 * @param array $types The actor types.
	 */
	$types = \apply_filters( 'activitypub_actor_types', Actor::TYPES );

	return _is_type_of( $data, $types );
}

/**
 * Check if an `$data` is a Collection.
 *
 * @see https://www.w3.org/ns/activitystreams#collections
 *
 * @param array|object|string $data The data to check.
 *
 * @return boolean True if the `$data` is a Collection, false otherwise.
 */
function is_collection( $data ) {
	/**
	 * Filters the collection types.
	 *
	 * @param array $types The collection types.
	 */
	$types = \apply_filters( 'activitypub_collection_types', array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) );

	return _is_type_of( $data, $types );
}

/**
 * Private helper to check if $data is of a given type set.
 *
 * @param array|object|string $data  The data to check.
 * @param array               $types The types to check against.
 *
 * @return boolean True if $data is of one of the types, false otherwise.
 */
function _is_type_of( $data, $types ) {
	if ( \is_string( $data ) ) {
		return \in_array( $data, $types, true );
	}

	if ( \is_array( $data ) && isset( $data['type'] ) ) {
		return \in_array( $data['type'], $types, true );
	}

	if ( $data instanceof Base_Object ) {
		return \in_array( $data->get_type(), $types, true );
	}

	return false;
}
