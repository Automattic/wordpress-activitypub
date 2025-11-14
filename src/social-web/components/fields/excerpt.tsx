import { __ } from '@wordpress/i18n';
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
		return text.replace( /<[^>]*>/g, '' ).replace( /&[^;]+;/g, '' );
	},
	render: ( { item }: { item: FeedPost } ) => {
		const excerpt = item.excerpt?.rendered || item.content?.rendered || '';
		// Strip HTML tags and decode HTML entities
		const plainText = excerpt
			.replace( /<[^>]*>/g, '' )
			.replace( /&nbsp;/g, ' ' )
			.replace( /&amp;/g, '&' )
			.replace( /&lt;/g, '<' )
			.replace( /&gt;/g, '>' )
			.replace( /&quot;/g, '"' )
			.replace( /&#039;/g, "'" )
			.trim();

		// Show more text for better context (300 chars instead of 200)
		const truncated = plainText.length > 300 ? plainText.substring( 0, 300 ) + '…' : plainText;

		if ( ! truncated ) {
			return null;
		}

		return <div className="activitypub-feed-excerpt">{ truncated }</div>;
	},
};
