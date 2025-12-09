import { __ } from '@wordpress/i18n';
import { resolveSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';
import { objectTypeConfig } from '../../object-types';

export const objectTypeField: Field< FeedPost > = {
	id: 'ap_object_type',
	type: 'integer',
	label: __( 'Type', 'activitypub' ),
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ): number => item.ap_object_type?.[ 0 ],
	getElements: async (): Promise< { value: number; label: string }[] > => {
		const records: Term[] = await resolveSelect( coreDataStore ).getEntityRecords( 'taxonomy', 'ap_object_type', {
			per_page: -1,
			orderby: 'count',
			order: 'desc',
			hide_empty: true,
		} );

		if ( ! records ) {
			return [];
		}

		// Map terms with translations from objectTypeConfig
		return records.map( ( term: Term ): { value: number; label: string } => ( {
			value: term.id,
			label: objectTypeConfig[ term.name ]?.label || term.name,
		} ) );
	},
	render: (): null => null,
	filterBy: {
		operators: [ 'is' ],
	},
};
