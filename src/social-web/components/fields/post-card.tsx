import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';
import { metadataField } from './metadata';
import { createTitleField } from './title';
import { excerptField } from './excerpt';

/**
 * Post card field for list view - stacks all content vertically
 */
export const createPostCardField = ( onSelectItem: ( id: number ) => void ): Field< FeedPost > => {
	const titleField = createTitleField( onSelectItem );

	return {
		id: 'post_card',
		label: __( 'Post', 'activitypub' ),
		enableHiding: false,
		enableSorting: false,
		getValue: ( { item }: { item: FeedPost } ) => {
			const author = item.actor_info?.name || '';
			const title = item.title?.rendered || '';
			const excerpt = item.excerpt?.rendered || item.content?.rendered || '';
			return `${ author } ${ title } ${ excerpt }`;
		},
		render: ( { item }: { item: FeedPost } ) => {
			return (
				<div className="activitypub-feed-post-card">
					{ metadataField.render?.( { item } ) }
					{ titleField.render?.( { item } ) }
					{ excerptField.render?.( { item } ) }
				</div>
			);
		},
	};
};
