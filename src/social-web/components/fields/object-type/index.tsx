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
			// @see Base_Object::TYPES
			Article: __( 'Articles', 'activitypub' ),
			Audio: __( 'Music & Podcasts', 'activitypub' ),
			Document: __( 'Documents & Files', 'activitypub' ),
			Event: __( 'Events & Meetups', 'activitypub' ),
			Image: __( 'Photos & Images', 'activitypub' ),
			Note: __( 'Notes & Updates', 'activitypub' ),
			Page: __( 'Pages', 'activitypub' ),
			Place: __( 'Places & Locations', 'activitypub' ),
			Video: __( 'Videos', 'activitypub' ),
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
