import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { __unstableStripHTML as stripHTML, safeHTML } from '@wordpress/dom';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';
import '../content/style.scss';

/**
 * Smart content field that automatically chooses between excerpt and content
 * based on the post's ActivityPub object type.
 *
 * - Notes: Show full content (HTML)
 * - All other types (Articles, etc.): Show excerpt (plain text)
 */
export const smartContentField: Field< FeedPost > = {
	id: 'smart-content',
	label: __( 'Content', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		const text = item.excerpt?.rendered || item.content?.rendered || '';
		return decodeEntities( stripHTML( text ) );
	},
	render: ( { item }: { item: FeedPost } ) => {
		// Get the taxonomy term to check the object type name
		const objectTypeId = item.ap_object_type?.[ 0 ];
		const objectTypeTerm = useSelect(
			( select ) => {
				if ( ! objectTypeId ) {
					return null;
				}
				return select( coreDataStore ).getEntityRecord( 'taxonomy', 'ap_object_type', objectTypeId );
			},
			[ objectTypeId ]
		);

		// Check if this is a Note type by checking the term name
		const isNote = objectTypeTerm?.name === 'Note';

		if ( isNote ) {
			// Show full content for Notes (HTML)
			const content = safeHTML( decodeEntities( item.content?.rendered || '' ) );

			return (
				<div className="activitypub-feed-post">
					<div
						className="activitypub-feed-content"
						dangerouslySetInnerHTML={ { __html: content || '<p>\u00A0</p>' } }
					/>
				</div>
			);
		}

		// Show excerpt for Articles and other types (plain text)
		const plainText = decodeEntities( stripHTML( item.excerpt?.rendered || '' ) ).trim();

		return <div className="activitypub-feed-excerpt">{ plainText || '\u00A0' }</div>;
	},
};
