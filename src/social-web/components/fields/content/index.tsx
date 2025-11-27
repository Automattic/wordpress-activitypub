import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { __unstableStripHTML as stripHTML, safeHTML } from '@wordpress/dom';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';
import { useObjectType } from '../../../contexts/object-type-context';
import './style.scss';

/**
 * Smart content field that automatically chooses between excerpt and content
 * based on the post's ActivityPub object type.
 *
 * - Notes: Show full content (HTML)
 * - All other types (Articles, etc.): Show excerpt (plain text)
 */
export const contentField: Field< FeedPost > = {
	id: 'content',
	label: __( 'Content', 'activitypub' ),
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		const text = item.excerpt?.rendered || item.content?.rendered || '';
		return decodeEntities( stripHTML( text ) );
	},
	render: ( { item }: { item: FeedPost } ) => {
		const { getObjectTypeName, isLoading } = useObjectType();

		// Get the object type name from the cached map
		const objectTypeId = item.ap_object_type?.[ 0 ];
		const objectTypeName = getObjectTypeName( objectTypeId );

		// While loading, show a placeholder to prevent flicker
		if ( isLoading && ! objectTypeName ) {
			return <div className="activitypub-feed-excerpt">{ '\u00A0' }</div>;
		}

		// Check if this is a Note type
		const isNote = objectTypeName === 'Note';
		const hasFeaturedImage = !! item.featured_image;

		if ( isNote ) {
			// Show full content for Notes (HTML)
			const content = safeHTML( decodeEntities( item.content?.rendered || '' ) );

			return (
				<div className="activitypub-feed-post">
					<div
						className="activitypub-feed-content"
						dangerouslySetInnerHTML={ { __html: content || '<p>\u00A0</p>' } }
					/>
					{ hasFeaturedImage && (
						<img
							src={ item.featured_image }
							alt={ decodeEntities( item.title?.rendered || __( 'Post image', 'activitypub' ) ) }
							className="activitypub-feed-content__image"
							loading="lazy"
						/>
					) }
				</div>
			);
		}

		// Show excerpt for Articles and other types (plain text)
		const plainText = contentField.getValue( { item } ).trim();

		if ( ! plainText && ! hasFeaturedImage ) {
			return <div className="activitypub-feed-excerpt">{ '\u00A0' }</div>;
		}

		return (
			<div className="activitypub-feed-excerpt">
				{ plainText && <div className="activitypub-feed-excerpt__text">{ plainText }</div> }
				{ hasFeaturedImage && (
					<img
						src={ item.featured_image }
						alt={ decodeEntities( item.title?.rendered || __( 'Post Thumbnail', 'activitypub' ) ) }
						className="activitypub-feed-excerpt__image"
						loading="lazy"
					/>
				) }
			</div>
		);
	},
};
