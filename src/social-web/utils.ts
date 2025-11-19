/**
 * Utility functions for Social Web
 */

import { sprintf, _x } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

/**
 * Format relative time in short format (5m, 2h, 6d)
 * For dates older than 6 days, returns a localized date format
 *
 * @param dateString - The date string to format
 * @return The formatted relative time string
 */
export function getRelativeTimeShort( dateString: string ): string {
	const now = Date.now();
	// Ensure the date string is parsed as UTC by adding 'Z' if not present
	const utcDateString = dateString.endsWith( 'Z' ) ? dateString : dateString + 'Z';
	const date = new Date( utcDateString );
	const diffMs = now - date.getTime();
	const diffMinutes = Math.floor( diffMs / ( 1000 * 60 ) );
	const diffHours = Math.floor( diffMs / ( 1000 * 60 * 60 ) );
	const diffDays = Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );

	if ( diffMinutes < 1 ) {
		return sprintf(
			/* translators: %d: number of minutes */
			_x( '%dm', 'short time format: minutes', 'activitypub' ),
			0
		);
	} else if ( diffMinutes < 60 ) {
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
	} else if ( diffDays <= 6 ) {
		return sprintf(
			/* translators: %d: number of days */
			_x( '%dd', 'short time format: days', 'activitypub' ),
			diffDays
		);
	}
	// Use WordPress date localization for older dates
	return dateI18n( 'll', dateString );
}
