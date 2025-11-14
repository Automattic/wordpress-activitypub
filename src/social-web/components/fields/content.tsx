import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

/**
 * Content field for reader-style view
 * Displays rich content with HTML formatting
 */
export const createContentField = (): Field< FeedPost > => ( {
	id: 'content',
	label: __( 'Content', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		// Strip HTML tags for plain text value (used for search/sort)
		const text = item.excerpt?.rendered || item.content?.rendered || '';
		return text.replace( /<[^>]*>/g, '' ).replace( /&[^;]+;/g, '' );
	},
	render: ( { item }: { item: FeedPost } ) => {
		// Use full content
		const content = item.content?.rendered || '';

		// Check if content is actually empty (not just whitespace)
		const hasRealContent =
			content
				.trim()
				.replace( /<\/?p>/g, '' )
				.replace( /&nbsp;/g, '' )
				.trim().length > 0;

		if ( ! hasRealContent ) {
			return null;
		}

		return (
			<div className="activitypub-feed-post">
				<div className="activitypub-feed-content" dangerouslySetInnerHTML={ { __html: content } } />
			</div>
		);
	},
} );
