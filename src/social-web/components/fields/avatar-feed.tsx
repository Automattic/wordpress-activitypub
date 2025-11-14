/**
 * Avatar field for Feed Posts.
 */

import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

const defaultAvatar =
	'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23999"%3E%3Cpath d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/%3E%3C/svg%3E';

export const avatarFeedField: Field< FeedPost > = {
	id: 'avatar',
	label: __( 'Avatar', 'activitypub' ),
	type: 'media',
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => item.actor_info?.icon || defaultAvatar,
	render: ( { item }: { item: FeedPost } ) => {
		const avatarUrl = item.actor_info?.icon || defaultAvatar;
		const author = item.actor_info?.name || __( 'Unknown author', 'activitypub' );

		return <img src={ avatarUrl } alt={ author } className="activitypub-feed-avatar" />;
	},
};
