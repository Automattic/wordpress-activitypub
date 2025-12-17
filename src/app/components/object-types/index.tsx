/**
 * Object Types Component
 *
 * Displays ap_object_type taxonomy terms as a clickable list of object types.
 * Only shows object types that have posts for the currently active actor.
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { useEntityRecords } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { Icon, MenuItem, MenuGroup } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';
import { postContent, audio, file, calendar, image, comment, page, pin, video } from '@wordpress/icons';
import { STORE_NAME } from '../../store';
import type { AppSelectors } from '../../store';

/**
 * Internal dependencies
 */
import { useObjectTypeFilter } from '../../hooks/use-object-type-filter';

interface ObjectTypeConfigItem {
	label: string;
	icon: typeof postContent;
}

// Object type configuration with translations and icons - matches object-type field definitions
export const objectTypeConfig: Record< string, ObjectTypeConfigItem > = {
	// @see Base_Object::TYPES
	Article: { label: __( 'Articles', 'activitypub' ), icon: postContent },
	Note: { label: __( 'Notes & Updates', 'activitypub' ), icon: comment },
	Image: { label: __( 'Photos & Images', 'activitypub' ), icon: image },
	Event: { label: __( 'Events & Meetups', 'activitypub' ), icon: calendar },
	Video: { label: __( 'Videos', 'activitypub' ), icon: video },
	Audio: { label: __( 'Music & Podcasts', 'activitypub' ), icon: audio },
	Document: { label: __( 'Documents & Files', 'activitypub' ), icon: file },
	Page: { label: __( 'Pages', 'activitypub' ), icon: page },
	Place: { label: __( 'Places & Locations', 'activitypub' ), icon: pin },
};

export function ObjectTypes(): ReactNode {
	// Get active actor ID from store to filter object types
	const activeActorId = useSelect( ( select ) => ( select( STORE_NAME ) as AppSelectors ).getActiveActorId(), [] );

	// Only fetch when we have an active actor ID (including 0 for site actor)
	const hasActiveActor = activeActorId !== null;

	const { records: objectTypes, isResolving } = useEntityRecords< Term >(
		'taxonomy',
		'ap_object_type',
		hasActiveActor
			? {
					per_page: -1,
					user_id: activeActorId,
			  }
			: undefined
	);

	const { selectedObjectTypeId, updateObjectTypeFilter } = useObjectTypeFilter();

	// Toggle: if clicking the same object type, clear the filter
	const updateFilter: ( objectTypeId: number ) => void = ( objectTypeId: number ): void =>
		updateObjectTypeFilter( selectedObjectTypeId === objectTypeId ? null : objectTypeId );

	if ( isResolving || ! objectTypes || objectTypes.length === 0 ) {
		return null;
	}

	// Filter to only show known object types (those with config)
	const knownObjectTypes: Term[] = objectTypes.filter(
		( objectType: Term ): boolean => !! objectTypeConfig[ objectType.name ]
	);

	// Don't show the filter if there are no object types or only one type
	if ( knownObjectTypes.length <= 1 ) {
		return null;
	}

	// Sort by the order in objectTypeConfig object
	const configOrder: string[] = Object.keys( objectTypeConfig );
	const sortedObjectTypes: Term[] = [ ...knownObjectTypes ].sort( ( a: Term, b: Term ): number => {
		const indexA: number = configOrder.indexOf( a.name );
		const indexB: number = configOrder.indexOf( b.name );
		return indexA - indexB;
	} );

	return (
		<MenuGroup className="object-types-menu">
			{ sortedObjectTypes.map( ( objectType: Term ): ReactNode => {
				const config: ObjectTypeConfigItem = objectTypeConfig[ objectType.name ];
				return (
					<MenuItem
						key={ objectType.id }
						onClick={ (): void => updateFilter( objectType.id ) }
						className="menu-item"
						aria-pressed={ selectedObjectTypeId === objectType.id }
						aria-label={
							/* translators: %s: object type name */
							sprintf( __( 'Filter by type: %s', 'activitypub' ), config.label )
						}
					>
						<Icon icon={ config.icon } size={ 24 } />
						<span>{ config.label }</span>
					</MenuItem>
				);
			} ) }
		</MenuGroup>
	);
}
