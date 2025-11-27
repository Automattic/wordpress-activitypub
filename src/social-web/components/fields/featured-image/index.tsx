/**
 * Featured Image field for DataViews.
 *
 * Displays the featured image from ActivityPub attachments.
 */

import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';
import './style.scss';

export const featuredImageField: Field< FeedPost > = {
	id: 'featured_image',
	label: __( 'Image', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => item.featured_image || '',
	render: ( { item }: { item: FeedPost } ) => {
		if ( ! item.featured_image ) {
			return null;
		}

		const altText = item.title?.rendered || __( 'Post Thumbnail', 'activitypub' );

		return (
			<div className="activitypub-featured-image">
				<img src={ item.featured_image } alt={ altText } loading="lazy" />
			</div>
		);
	},
};
