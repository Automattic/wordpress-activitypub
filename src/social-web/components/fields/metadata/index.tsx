import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import type { Field } from '@wordpress/dataviews';
import { useSettings } from '../../../contexts/settings-context';
import type { FeedPost } from '../../types';

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
		const { defaultAvatar } = useSettings();
		const name = decodeEntities( item.actor_info?.name || __( 'Unknown author', 'activitypub' ) );
		const avatarUrl = item.actor_info?.icon || defaultAvatar;
		const date = item.date
			? new Date( item.date ).toLocaleDateString( undefined, {
					year: 'numeric',
					month: 'short',
					day: 'numeric',
			  } )
			: '';

		return (
			<div className="activitypub-feed-post-meta">
				<img
					src={ avatarUrl }
					alt={ name }
					className="activitypub-feed-avatar"
					onError={ ( e ) => {
						( e.target as HTMLImageElement ).src = defaultAvatar;
					} }
				/>
				<span className="author">{ name }</span>
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
