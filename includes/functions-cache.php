<?php
/**
 * Cache functions for remote data.
 *
 * A thin layer over the WordPress object cache, with transients as the fallback on
 * sites without a persistent object cache. Entries are addressed by a namespace (the
 * kind of thing) and the ActivityPub id, never by a raw key. The `activitypub_pre_cache_get`
 * filter and the set and delete actions let a plugin serve entries from, and mirror them
 * into, another store.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Build the cache key for a namespace and an identifier.
 *
 * The identifier is hashed: ActivityPub ids are URLs of any length, and memcached
 * limits keys to 250 characters.
 *
 * @param string $kind The kind of thing, for example `actor` or `object`.
 * @since unreleased
 *
 * @param string $id        The ActivityPub id.
 *
 * @return string The key.
 */
function cache_key( $kind, $id ) {
	return $kind . ':' . \hash( 'sha256', $id );
}

/**
 * Get an entry from the cache.
 *
 * @param string $kind The kind of thing, for example `actor` or `object`.
 * @since unreleased
 *
 * @param string $id        The ActivityPub id.
 *
 * @return array|null The entry, or null when there is none.
 */
function cache_get( $kind, $id ) {
	/**
	 * Short-circuits the cache lookup.
	 *
	 * Return an array to serve the entry from somewhere else, for example a post type.
	 *
	 * @param array|null $pre       The entry, or null to look in the cache.
	 * @param string     $kind The kind of thing.
	 * @param string     $id        The ActivityPub id.
	 */
	$pre = \apply_filters( 'activitypub_pre_cache_get', null, $kind, $id );
	if ( null !== $pre ) {
		return $pre;
	}

	$key = cache_key( $kind, $id );

	if ( \wp_using_ext_object_cache() ) {
		$value = \wp_cache_get( $key, 'activitypub' );
	} else {
		$value = \get_transient( 'activitypub_' . $key );
	}

	return \is_array( $value ) ? $value : null;
}

/**
 * Get several entries from the cache at once.
 *
 * @param string   $kind The kind of thing, for example `actor` or `object`.
 * @since unreleased
 *
 * @param string[] $ids       The ActivityPub ids.
 *
 * @return array<string, array|null> The entries keyed by id, in the order asked for, null where there is none.
 */
function cache_get_multiple( $kind, $ids ) {
	$result  = array();
	$pending = array();

	foreach ( $ids as $id ) {
		/** This filter is documented in includes/functions-cache.php */
		$pre = \apply_filters( 'activitypub_pre_cache_get', null, $kind, $id );

		$result[ $id ] = null !== $pre ? $pre : null;
		if ( null === $pre ) {
			$pending[ cache_key( $kind, $id ) ] = $id;
		}
	}

	if ( ! $pending ) {
		return $result;
	}

	if ( \wp_using_ext_object_cache() ) {
		foreach ( \wp_cache_get_multiple( \array_keys( $pending ), 'activitypub' ) as $key => $value ) {
			if ( \is_array( $value ) ) {
				$result[ $pending[ $key ] ] = $value;
			}
		}
	} else {
		foreach ( $pending as $key => $id ) {
			$value = \get_transient( 'activitypub_' . $key );
			if ( \is_array( $value ) ) {
				$result[ $id ] = $value;
			}
		}
	}

	return $result;
}

/**
 * Store an entry in the cache.
 *
 * Always with a lifetime: a persistent object cache evicts entries anyway, and a
 * transient without one becomes an autoloaded option.
 *
 * @param string $kind The kind of thing, for example `actor` or `object`.
 * @param string $id        The ActivityPub id.
 * @param array  $value     The entry.
 * @since unreleased
 *
 * @param int    $ttl       Seconds to keep it.
 *
 * @return bool Whether the entry was stored.
 */
function cache_set( $kind, $id, $value, $ttl ) {
	$key = cache_key( $kind, $id );

	if ( \wp_using_ext_object_cache() ) {
		$stored = \wp_cache_set( $key, $value, 'activitypub', $ttl );
	} else {
		$stored = \set_transient( 'activitypub_' . $key, $value, $ttl );
	}

	/**
	 * Fires after an entry was stored in the cache.
	 *
	 * @param string $kind The kind of thing.
	 * @param string $id        The ActivityPub id.
	 * @param array  $value     The entry.
	 * @param int    $ttl       Seconds it is kept.
	 */
	\do_action( 'activitypub_cache_set', $kind, $id, $value, $ttl );

	return (bool) $stored;
}

/**
 * Remove an entry from the cache.
 *
 * @param string $kind The kind of thing, for example `actor` or `object`.
 * @since unreleased
 *
 * @param string $id        The ActivityPub id.
 *
 * @return bool Whether an entry was removed.
 */
function cache_delete( $kind, $id ) {
	$key = cache_key( $kind, $id );

	if ( \wp_using_ext_object_cache() ) {
		$deleted = \wp_cache_delete( $key, 'activitypub' );
	} else {
		$deleted = \delete_transient( 'activitypub_' . $key );
	}

	/**
	 * Fires after an entry was removed from the cache.
	 *
	 * @param string $kind The kind of thing.
	 * @param string $id        The ActivityPub id.
	 */
	\do_action( 'activitypub_cache_delete', $kind, $id );

	return (bool) $deleted;
}
