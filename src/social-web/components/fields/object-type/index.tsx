import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';
import type { FeedPost } from '../../../types';

/**
 * Object type filter field
 *
 * @param apObjectTypes - Array of taxonomy terms from ap_object_type
 * @return Field configuration for object type filtering
 */
export function objectTypeField( apObjectTypes?: any[] ): Field< FeedPost > {
	return {
		id: 'ap_object_type',
		label: __( 'Type', 'activitypub' ),
		enableHiding: false,
		enableSorting: false,
		elements:
			apObjectTypes?.map( ( term ) => ( {
				value: term.id,
				label: term.name,
			} ) ) || [],
		getValue: ( { item } ) => item.ap_object_type?.[ 0 ],
		render: ( { item } ) => {
			const termId = item.ap_object_type?.[ 0 ];
			const term = apObjectTypes?.find( ( t ) => t.id === termId );
			return <span>{ term?.name || '—' }</span>;
		},
		filterBy: {
			operators: [ 'isAny' ],
		},
	};
}
