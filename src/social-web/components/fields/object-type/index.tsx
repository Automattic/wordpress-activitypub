import { __ } from '@wordpress/i18n';
import { resolveSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';

export const objectTypeField: Field< FeedPost > = {
	id: 'ap_object_type',
	type: 'integer',
	label: __( 'Type', 'activitypub' ),
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item }: { item: FeedPost } ): number => item.ap_object_type?.[ 0 ],
	getElements: async (): Promise< { value: number; label: string }[] > => {
		const translations: Record< string, string > = {
			Note: __( 'Note', 'activitypub' ),
			Article: __( 'Article', 'activitypub' ),
		};
		const records: Term[] = await resolveSelect( coreDataStore ).getEntityRecords( 'taxonomy', 'ap_object_type' );

		if ( ! records ) {
			return [];
		}

		// Map all terms with translations for known types
		return records.map( ( term: Term ): { value: number; label: string } => ( {
			value: term.id,
			label: translations[ term.name ] || term.name,
		} ) );
	},
	render: (): null => null,
	filterBy: {
		operators: [ 'is' ],
	},
};
