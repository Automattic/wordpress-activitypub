import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export const statusField: Field< FeedPost > = {
	id: 'status',
	label: __( 'Status', 'activitypub' ),
	enableHiding: true,
	enableSorting: true,
	getValue: ( { item }: { item: FeedPost } ) => item.status || '',
};
