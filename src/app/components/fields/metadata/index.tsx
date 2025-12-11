import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';
import { getRelativeTime } from '../../../utils';
import Avatar from '../../avatar';

export const metadataField: Field< FeedPost > = {
	id: 'metadata',
	label: __( 'Metadata', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		const author = item.actor_info?.name || '';
		const relativeTime = item.date ? getRelativeTime( item.date ) : '';

		return `${ author } · ${ relativeTime }`;
	},
	render: ( { item }: { item: FeedPost } ) => {
		const name = decodeEntities( item.actor_info?.name || __( 'Unknown author', 'activitypub' ) );
		const relativeTime = item.date ? getRelativeTime( item.date ) : '';

		return (
			<div className="activitypub-feed-post-meta">
				<Avatar item={ item } />
				<span className="author">{ name }</span>
				{ relativeTime && (
					<>
						<span className="separator">·</span>
						<span className="date">{ relativeTime }</span>
					</>
				) }
			</div>
		);
	},
};
