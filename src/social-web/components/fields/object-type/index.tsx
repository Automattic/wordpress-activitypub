import { __ } from '@wordpress/i18n';
import { resolveSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';

export const objectTypeField: Field< FeedPost > = {
	id: 'ap_object_type',
	type: 'integer',
	label: __( 'Type', 'activitypub' ),
	enableHiding: false,
	enableSorting: false,
	getValue: ( { item } ) => item.ap_object_type?.[ 0 ] ?? item.ap_object_type,
	getElements: async () => {
		const records = await resolveSelect( coreDataStore ).getEntityRecords( 'taxonomy', 'ap_object_type' );
		return (
			records?.map( ( term: any ) => ( {
				value: term.id,
				label: term.name,
			} ) ) || []
		);
	},
	render: () => null,
	filterBy: {
		operators: [ 'is' ],
	},
};
