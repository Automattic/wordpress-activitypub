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

	return \array_unique( $recipient_items );
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
	if ( ! empty( $activity['type'] ) && in_array( $activity['type'], array( 'Accept', 'Delete', 'Follow', 'Reject', 'Undo' ), true ) ) {
		return ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
	}

	// Check 'to' field for public visibility.
	$to = extract_recipients_from_activity_property( 'to', $activity );
	if ( ! empty( array_intersect( $to, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS ) ) ) {
		return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
	}

	// Check 'cc' field for quiet public visibility.
	$cc = extract_recipients_from_activity_property( 'cc', $activity );
	if ( ! empty( array_intersect( $cc, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS ) ) ) {
		return ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC;
	}

	// Activities with no recipients are treated as public.
	$recipients = extract_recipients_from_activity( $activity );
	if ( empty( $recipients ) ) {
		return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
	}

	return ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
}

/**
 * Check if passed Activity is Public.
 *
 * @see https://github.com/w3c/activitypub/issues/404#issuecomment-2926310561
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
		return true;
	}

	return ! empty( array_intersect( $recipients, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS ) );
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
	if ( ! $data || is_string( $data ) ) {
		return $data;
	}

	if ( is_object( $data ) ) {
		$data = $data->to_array();
	}

	/*
	 * Check if it is a list, then take first item.
	 * This plugin does not support collections.
	 */
	if ( array_is_list( $data ) ) {
		$data = $data[0];
	}

	// Check if it is simplified now.
	if ( is_string( $data ) ) {
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

		default:
			$data = $data['id'];
			break;
	}

	return $data;
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
	$types = apply_filters( 'activitypub_activity_types', Activity::TYPES );

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
	$types = apply_filters( 'activitypub_actor_types', Actor::TYPES );

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
	$types = apply_filters( 'activitypub_collection_types', array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ) );

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
	if ( is_string( $data ) ) {
		return in_array( $data, $types, true );
	}

	if ( is_array( $data ) && isset( $data['type'] ) ) {
		return in_array( $data['type'], $types, true );
	}

	if ( $data instanceof Base_Object ) {
		return in_array( $data->get_type(), $types, true );
	}

	return false;
}
