/**
 * Object Types Component
 *
 * Displays ap_object_type taxonomy terms as a clickable list of object types
 */

import { useEntityRecords } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import { Icon, MenuItem, MenuGroup } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';
import { useObjectTypeFilter } from '../../hooks/use-object-type-filter';
import { postContent, audio, file, calendar, image, comment, page, pin, video } from '@wordpress/icons';

// Object type configuration with translations and icons - matches object-type field definitions
export const objectTypeConfig: Record< string, { label: string; icon: any } > = {
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

export function ObjectTypes() {
	const { records: objectTypes, isResolving } = useEntityRecords< Term >( 'taxonomy', 'ap_object_type', {
		per_page: -1,
	} );

	const { selectedObjectTypeId, updateObjectTypeFilter } = useObjectTypeFilter();

	// Toggle: if clicking the same object type, clear the filter
	const updateFilter = ( objectTypeId: number ): void =>
		updateObjectTypeFilter( selectedObjectTypeId === objectTypeId ? null : objectTypeId );

	if ( isResolving ) {
		return (
			<div className="object-types">
				<div className="object-types__loading">{ __( 'Loading…', 'activitypub' ) }</div>
			</div>
		);
	}

	if ( ! objectTypes || objectTypes.length === 0 ) {
		return null;
	}

	// Filter to only show known object types (those with config)
	const knownObjectTypes = objectTypes.filter( ( objectType: Term ) => objectTypeConfig[ objectType.name ] );

	if ( knownObjectTypes.length === 0 ) {
		return null;
	}

	// Sort by the order in objectTypeConfig object
	const configOrder = Object.keys( objectTypeConfig );
	const sortedObjectTypes = [ ...knownObjectTypes ].sort( ( a: Term, b: Term ) => {
		const indexA = configOrder.indexOf( a.name );
		const indexB = configOrder.indexOf( b.name );
		return indexA - indexB;
	} );

	return (
		<MenuGroup className="object-types-menu">
			{ sortedObjectTypes.map( ( objectType: Term ) => {
				const config = objectTypeConfig[ objectType.name ];
				return (
					<MenuItem
						key={ objectType.id }
						onClick={ () => updateFilter( objectType.id ) }
						className="menu-item"
						aria-pressed={ selectedObjectTypeId === objectType.id }
						aria-label={
							/* translators: %s: object type name */
							sprintf( __( 'Filter by type: %s', 'activitypub' ) as string, config.label as any )
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
