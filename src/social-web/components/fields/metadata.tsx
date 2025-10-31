import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../types';

export const metadataField: Field< FeedPost > = {
	id: 'metadata',
	label: __( 'Metadata', 'activitypub' ),
	enableHiding: true,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ) => {
		const author = ( item as any ).actor?.post_title || '';
		const date = item.date ? new Date( item.date ).toLocaleDateString() : '';
		return `${ author } · ${ date }`;
	},
	render: ( { item }: { item: FeedPost } ) => {
		const author = ( item as any ).actor?.post_title || __( 'Unknown author', 'activitypub' );
		const date = item.date ? new Date( item.date ).toLocaleDateString() : '';

		return (
			<div
				style={ {
					color: '#757575',
					fontSize: '12px',
					marginTop: '2px',
				} }
			>
				{ author } { date && `· ${ date }` }
			</div>
		);
	},
};
