import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';

export const titleField: Field< FeedPost > = {
	id: 'title.rendered',
	label: __( 'Title', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	enableGlobalSearch: true,
	getValue: ( { item }: { item: FeedPost } ) => decodeEntities( item.title?.rendered || '' ),
	render: ( { item }: { item: FeedPost } ) => {
		if ( ! item.title?.rendered ) {
			return null;
		}

		// Remove backslash escapes and decode entities
		const unescaped = item.title.rendered.replace( /\\(.)/g, '$1' );
		return <div className="activitypub-feed-post-title">{ decodeEntities( unescaped ) }</div>;
	},
};
