/**
 * Name field for Feed Posts.
 */

import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export const nameFeedField: Field< FeedPost > = {
	id: 'name',
	label: __( 'Author', 'activitypub' ),
	enableHiding: false,
	enableSorting: true,
	getValue: ( { item }: { item: FeedPost } ) => item.actor_info?.name || '',
	render: ( { item }: { item: FeedPost } ) => {
		const name = item.actor_info?.name || __( 'Unknown author', 'activitypub' );

		return <span className="author">{ name }</span>;
	},
};
