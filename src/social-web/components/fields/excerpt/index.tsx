import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { __unstableStripHTML as stripHTML } from '@wordpress/dom';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';
import './style.scss';

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
		const hasFeaturedImage = !! item.featured_image;

		if ( ! plainText && ! hasFeaturedImage ) {
			return null;
		}

		return (
			<div className="activitypub-feed-excerpt">
				{ plainText && <div className="activitypub-feed-excerpt__text">{ plainText }</div> }
				{ hasFeaturedImage && (
					<img
						src={ item.featured_image }
						alt={ item.title?.rendered || __( 'Post image', 'activitypub' ) }
						className="activitypub-feed-excerpt__image"
						loading="lazy"
					/>
				) }
			</div>
		);
	},
};
