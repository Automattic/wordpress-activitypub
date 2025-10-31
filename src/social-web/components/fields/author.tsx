import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export const authorField: Field< FeedPost > = {
	id: 'actor.post_title',
	label: __( 'Author', 'activitypub' ),
	enableHiding: false,
	enableSorting: false,
	enableGlobalSearch: true,
	getValue: ( { item }: { item: FeedPost } ) => {
		// DataViews will automatically extract actor.post_title using dot notation
		// but we provide a fallback getValue for consistency
		return ( item as any ).actor?.post_title || '';
	},
};
