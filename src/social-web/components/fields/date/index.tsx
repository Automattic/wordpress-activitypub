import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export const dateField: Field< FeedPost > = {
	id: 'date',
	label: __( 'Date', 'activitypub' ),
	enableHiding: false,
	enableSorting: true,
	getValue: ( { item }: { item: FeedPost } ) => item.date || '',
	render: ( { item }: { item: FeedPost } ) => {
		if ( ! item.date ) {
			return '';
		}
		return new Date( item.date ).toLocaleDateString();
	},
};
