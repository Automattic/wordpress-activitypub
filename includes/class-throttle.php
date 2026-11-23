<?php
/**
 * Throttle class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * How often one caller may do something.
 *
 * Counts uses of an allowance per bucket and caller in a time window. The REST routes count
 * their callers with it (see `Rest\Rate_Limit`), and other code that reacts to remote input
 * counts what it limits, like the e-mail notifications per recipient.
 *
 * The count is not atomic: two parallel uses can both pass right at the limit. That is fine
 * for an allowance; a side effect that must happen only once claims its unit of work instead,
 * see `Scheduler\Statistics::send_monthly_email()`.
 *
 * @since unreleased
 */
class Throttle {
	/**
	 * Count one use against a caller's allowance.
	 *
	 * @param string $bucket What is being limited, for example `interactions`.
	 * @param string $caller Who is counted, for example a user, an IP address or a sender's host.
	 * @param int    $limit  How many uses a caller has per window.
	 * @param int    $window Optional. The window in seconds. Default one minute.
	 *
	 * @return array{allowed: bool, limit: int, remaining: int, reset: int} Whether the use fits, and the numbers behind it.
	 */
	public static function count( $bucket, $caller, $limit, $window = MINUTE_IN_SECONDS ) {
		/**
		 * Filters how many uses a caller has per window.
		 *
		 * @since 9.4.0
		 *
		 * @param int    $limit  The allowance the caller asks for.
		 * @param string $bucket What is being limited, for example `interactions` (requests per
		 *                       minute) or `mail` (notification mails per recipient and hour).
		 */
		$limit = (int) \apply_filters( 'activitypub_rate_limit', $limit, $bucket );

		/*
		 * The window is part of the entry's name, so it ends by itself instead of being pushed
		 * forward by every use, and only the first use of a window writes a new entry.
		 */
		$slot  = (int) \floor( \time() / $window );
		$reset = ( $slot + 1 ) * $window;

		// The caller is hashed, so no IP address ends up in an option name, a backup or a debug dump.
		$key   = \sprintf( 'activitypub_rate_%s_%s_%d', $bucket, \md5( $caller ), $slot );
		$count = (int) \get_transient( $key );

		$entry = array(
			'allowed'   => false,
			'limit'     => $limit,
			'remaining' => 0,
			'reset'     => $reset,
		);

		// Without a caller there is nothing to count, so the use is refused rather than let through.
		if ( '' === $caller || $count >= $limit ) {
			return $entry;
		}

		\set_transient( $key, $count + 1, $window );

		$entry['allowed']   = true;
		$entry['remaining'] = $limit - $count - 1;

		return $entry;
	}
}
