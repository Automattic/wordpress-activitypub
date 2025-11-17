import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export const excerptField: Field< FeedPost > = {
	id: 'excerpt.rendered',
	label: __( 'Excerpt', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		// Strip HTML tags for plain text value
		const text = item.excerpt?.rendered || item.content?.rendered || '';
		const stripped = text.replace( /<[^>]*>/g, '' );
		return decodeEntities( stripped );
	},
	render: ( { item }: { item: FeedPost } ) => {
		const excerpt = item.excerpt?.rendered || item.content?.rendered || '';
		// Strip HTML tags, remove backslash escapes, and decode HTML entities
		const stripped = excerpt.replace( /<[^>]*>/g, '' ).trim();
		const unescaped = stripped.replace( /\\(.)/g, '$1' );
		const plainText = decodeEntities( unescaped );

		// Show more text for better context (300 chars instead of 200)
		const truncated = plainText.length > 300 ? plainText.substring( 0, 300 ) + '…' : plainText;

		if ( ! truncated ) {
			return null;
		}

		return <div className="activitypub-feed-excerpt">{ truncated }</div>;
	},
};
