import { __ } from '@wordpress/i18n';
import { resolveSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';

export const tagField: Field< FeedPost > = {
	id: 'ap_tag',
	type: 'integer',
	label: __( 'Tag', 'activitypub' ),
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ): number => item.ap_tag?.[ 0 ],
	getElements: async (): Promise< { value: number; label: string }[] > => {
		const records: Term[] = await resolveSelect( coreDataStore ).getEntityRecords( 'taxonomy', 'ap_tag', {
			per_page: 10,
			orderby: 'count',
			order: 'desc',
		} );

		if ( ! records ) {
			return [];
		}

		// Map popular tags with # prefix
		return records.map( ( term: Term ): { value: number; label: string } => ( {
			value: term.id,
			label: `#${ term.name }`,
		} ) );
	},
	render: (): null => null,
	filterBy: {
		operators: [ 'isAny' ],
	},
};
