import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export const createTitleField = ( onSelectItem: ( id: number ) => void ): Field< FeedPost > => ( {
	id: 'title.rendered',
	label: __( 'Title', 'activitypub' ),
	enableHiding: true,
	enableSorting: true,
	enableGlobalSearch: true,
	getValue: ( { item }: { item: FeedPost } ) => item.title?.rendered || '',
	render: ( { item }: { item: FeedPost } ) => {
		if ( ! item.title?.rendered ) {
			return null;
		}

		return (
			<div className="activitypub-feed-post-title" dangerouslySetInnerHTML={ { __html: item.title.rendered } } />
		);
	},
} );
