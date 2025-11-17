import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { __unstableStripHTML as stripHTML } from '@wordpress/dom';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';

/**
 * Get plain text excerpt from post.
 */
const getPlainTextExcerpt = ( item: FeedPost ): string => {
	const text = item.excerpt?.rendered || item.content?.rendered || '';
	return decodeEntities( stripHTML( text ) );
};

export const excerptField: Field< FeedPost > = {
	id: 'excerpt.rendered',
	label: __( 'Excerpt', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => getPlainTextExcerpt( item ),
	render: ( { item }: { item: FeedPost } ) => {
		const plainText = getPlainTextExcerpt( item ).trim();

		// Show more text for better context (300 chars instead of 200)
		const truncated = plainText.length > 300 ? plainText.substring( 0, 300 ) + '…' : plainText;

		if ( ! truncated ) {
			return null;
		}

		return <div className="activitypub-feed-excerpt">{ truncated }</div>;
	},
};
