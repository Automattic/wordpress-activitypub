import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { __unstableStripHTML as stripHTML, safeHTML } from '@wordpress/dom';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';

/**
 * Smart content field that automatically chooses between excerpt and content
 * based on the post's object type.
 *
 * - Articles: Show excerpt (plain text)
 * - All other types: Show full content (HTML)
 */
export const smartContentField: Field< FeedPost > = {
	id: 'smart-content',
	label: __( 'Content', 'activitypub' ),
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		const text = item.excerpt?.rendered || item.content?.rendered || '';
		return decodeEntities( stripHTML( text ) );
	},
	render: ( { item }: { item: FeedPost } ) => {
		// Check if this is an Article type by checking if it has an explicit excerpt
		// Articles typically have both excerpt and content, where excerpt is a summary
		const hasExplicitExcerpt = item.excerpt?.rendered && item.excerpt.rendered.length > 0;
		const isArticle = hasExplicitExcerpt;

		if ( isArticle ) {
			// Show excerpt for Articles (plain text)
			const plainText = decodeEntities( stripHTML( item.excerpt?.rendered || '' ) ).trim();

			// Always render, even if empty - don't hide
			return <div className="activitypub-feed-excerpt">{ plainText || '\u00A0' }</div>;
		}

		// Show full content for non-Articles (HTML)
		const content = safeHTML( decodeEntities( item.content?.rendered || '' ) );

		// Always render, even if empty - don't hide
		return (
			<div className="activitypub-feed-post">
				<div
					className="activitypub-feed-content"
					dangerouslySetInnerHTML={ { __html: content || '<p>\u00A0</p>' } }
				/>
			</div>
		);
	},
};
