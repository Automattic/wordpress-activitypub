/**
 * Utility functions for ActivityPub App.
 */

import { sprintf, _x } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';

/**
 * Format relative time in short format (5m, 2h, 6d)
 * For dates older than a week, returns the site's date format
 *
 * @param dateString - The date string to format
 * @return The formatted relative time string
 */
export function getRelativeTime( dateString: string ): string {
	// Ensure the date string is parsed as UTC by adding 'Z' if not present
	const date = new Date( dateString.endsWith( 'Z' ) ? dateString : dateString + 'Z' );
	const now: number = Date.now();

	const diffMs: number = now - date.getTime();
	const diffMinutes: any = Math.floor( diffMs / ( 1000 * 60 ) );
	const diffHours: any = Math.floor( diffMs / ( 1000 * 60 * 60 ) );
	const diffDays: any = Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );

	if ( diffMinutes < 60 ) {
		return sprintf(
			/* translators: %d: number of minutes */
			_x( '%dm', 'short time format: minutes', 'activitypub' ),
			diffMinutes
		);
	} else if ( diffHours < 24 ) {
		return sprintf(
			/* translators: %d: number of hours */
			_x( '%dh', 'short time format: hours', 'activitypub' ),
			diffHours
		);
	} else if ( diffDays < 7 ) {
		return sprintf(
			/* translators: %d: number of days */
			_x( '%dd', 'short time format: days', 'activitypub' ),
			diffDays
		);
	}

	// Use site's date format for dates older than a week
	return dateI18n( getSettings().formats.date, dateString );
}
