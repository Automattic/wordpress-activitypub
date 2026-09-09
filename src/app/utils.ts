/**
 * Utility functions for ActivityPub App.
 */

/**
 * WordPress dependencies
 */
import { sprintf, _x } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';

/**
 * Format relative time in short format (5m, 2h, 6d)
 * For dates older than a week, returns the site's date format
 *
 * @param dateString The date string to format
 * @return The formatted relative time string
 */
export function getRelativeTime( dateString: string ): string {
	// Ensure the date string is parsed as UTC by adding 'Z' if not present
	const date: Date = new Date( dateString.endsWith( 'Z' ) ? dateString : dateString + 'Z' );
	const now: number = Date.now();
	const diffMs: number = now - date.getTime();

	const diffMinutes: number = Math.floor( diffMs / ( 1000 * 60 ) );
	if ( diffMinutes < 60 ) {
		return sprintf(
			/* translators: %d: number of minutes */
			_x( '%dm', 'short time format: minutes', 'activitypub' ),
			diffMinutes
		);
	}

	const diffHours: number = Math.floor( diffMs / ( 1000 * 60 * 60 ) );
	if ( diffHours < 24 ) {
		return sprintf(
			/* translators: %d: number of hours */
			_x( '%dh', 'short time format: hours', 'activitypub' ),
			diffHours
		);
	}

	const diffDays: number = Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );
	if ( diffDays < 7 ) {
		return sprintf(
			/* translators: %d: number of days */
			_x( '%dd', 'short time format: days', 'activitypub' ),
			diffDays
		);
	}

	// Use site's date format for dates older than a week
	return dateI18n( getSettings().formats.date, dateString );
}

/**
 * Return a URL that is safe to use as an `href`/`src` attribute value.
 *
 * Remote actor data is stored and served verbatim, so an actor could supply a
 * `javascript:` (or other script-executing) URL that React would render as-is.
 * Only http(s) and protocol-relative/relative URLs are allowed through; anything
 * else is replaced with a harmless fallback.
 *
 * @param url      The URL to validate.
 * @param fallback Value returned when the URL is unsafe. Defaults to '#'.
 * @return The original URL when safe, otherwise the fallback.
 */
export function safeUrl( url: string, fallback: string = '#' ): string {
	if ( ! url ) {
		return fallback;
	}

	try {
		// Resolve against the current origin so relative/protocol-relative URLs stay valid.
		const { protocol } = new URL( url, window.location.origin );
		return 'http:' === protocol || 'https:' === protocol ? url : fallback;
	} catch {
		return fallback;
	}
}
