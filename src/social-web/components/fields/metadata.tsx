import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';
import { avatarFeedField } from './avatar-feed';
import { nameFeedField } from './name-feed';

export const metadataField: Field< FeedPost > = {
	id: 'metadata',
	label: __( 'Metadata', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		const author = item.actor_info?.name || '';
		const date = item.date ? new Date( item.date ).toLocaleDateString() : '';
		return `${ author } · ${ date }`;
	},
	render: ( { item }: { item: FeedPost } ) => {
		const date = item.date
			? new Date( item.date ).toLocaleDateString( undefined, {
					year: 'numeric',
					month: 'short',
					day: 'numeric',
			  } )
			: '';

		return (
			<div className="activitypub-feed-post-meta">
				{ avatarFeedField.render?.( { item } ) }
				{ nameFeedField.render?.( { item } ) }
				{ date && (
					<>
						<span className="separator">·</span>
						<span className="date">{ date }</span>
					</>
				) }
			</div>
		);
	},
};
