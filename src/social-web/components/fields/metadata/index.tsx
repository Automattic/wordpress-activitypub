import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import type { Field } from '@wordpress/dataviews';
import { useSettings } from '../../../contexts/settings-context';
import type { FeedPost } from '../../types';

// Helper function to format relative time (max 6 days)
// dateString should be in GMT/UTC format
function getRelativeTime( dateString: string ): string {
	const now = Date.now();
	// Ensure the date string is parsed as UTC by adding 'Z' if not present
	const utcDateString = dateString.endsWith( 'Z' ) ? dateString : dateString + 'Z';
	const date = new Date( utcDateString );
	const diffMs = now - date.getTime();
	const diffMinutes = Math.floor( diffMs / ( 1000 * 60 ) );
	const diffHours = Math.floor( diffMs / ( 1000 * 60 * 60 ) );
	const diffDays = Math.floor( diffMs / ( 1000 * 60 * 60 * 24 ) );

	// If the date is in the future or just happened, show recent time
	if ( diffMinutes < 1 ) {
		return '0m';
	} else if ( diffMinutes < 60 ) {
		return `${ diffMinutes }m`;
	} else if ( diffHours < 24 ) {
		return `${ diffHours }h`;
	} else if ( diffDays <= 6 ) {
		return `${ diffDays }d`;
	}
	return date.toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	} );
}

export const metadataField: Field< FeedPost > = {
	id: 'metadata',
	label: __( 'Metadata', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		const author = item.actor_info?.name || '';
		const dateToUse = item.date_gmt || item.date;
		const relativeTime = dateToUse ? getRelativeTime( dateToUse ) : '';
		return `${ author } · ${ relativeTime }`;
	},
	render: ( { item }: { item: FeedPost } ) => {
		const { defaultAvatar } = useSettings();
		const name = decodeEntities( item.actor_info?.name || __( 'Unknown author', 'activitypub' ) );
		const avatarUrl = item.actor_info?.icon || '';
		// Use date_gmt for accurate time calculations, fallback to date
		const dateToUse = item.date_gmt || item.date;
		const relativeTime = dateToUse ? getRelativeTime( dateToUse ) : '';
		const postLink = item.link || '';

		return (
			<div className="activitypub-feed-post-meta">
				<img
					src={ avatarUrl }
					alt={ name }
					className="activitypub-feed-avatar"
					onError={ ( e ) => {
						( e.target as HTMLImageElement ).src = defaultAvatar;
					} }
				/>
				<span className="author">{ name }</span>
				{ relativeTime && postLink && (
					<>
						<span className="separator">·</span>
						<a
							href={ postLink }
							target="_blank"
							rel="noopener noreferrer"
							className="date"
							onClick={ ( e ) => e.stopPropagation() }
						>
							{ relativeTime }
						</a>
					</>
				) }
			</div>
		);
	},
};
