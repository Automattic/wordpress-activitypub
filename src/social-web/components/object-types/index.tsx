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

// Translations for object type names - matches object-type field definitions
const translations: Record< string, string > = {
	// @see Base_Object::TYPES
	Article: __( 'Articles', 'activitypub' ),
	Note: __( 'Notes & Updates', 'activitypub' ),
	Image: __( 'Photos & Images', 'activitypub' ),
	Event: __( 'Events & Meetups', 'activitypub' ),
	Video: __( 'Videos', 'activitypub' ),
	Audio: __( 'Music & Podcasts', 'activitypub' ),
	Document: __( 'Documents & Files', 'activitypub' ),
	Page: __( 'Pages', 'activitypub' ),
	Place: __( 'Places & Locations', 'activitypub' ),
};

// Icon mapping for object types
const icons: Record< string, any > = {
	Article: postContent,
	Audio: audio,
	Document: file,
	Event: calendar,
	Image: image,
	Note: comment,
	Page: page,
	Place: pin,
	Video: video,
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

	// Filter to only show known object types (those with translations)
	const knownObjectTypes = objectTypes.filter( ( objectType: Term ) => translations[ objectType.name ] );

	if ( knownObjectTypes.length === 0 ) {
		return null;
	}

	// Sort by the order in translations object
	const translationOrder = Object.keys( translations );
	const sortedObjectTypes = knownObjectTypes.sort( ( a: Term, b: Term ) => {
		const indexA = translationOrder.indexOf( a.name );
		const indexB = translationOrder.indexOf( b.name );
		return indexA - indexB;
	} );

	return (
		<MenuGroup className="object-types-menu">
			{ sortedObjectTypes.map( ( objectType: Term ) => {
				const translatedName = translations[ objectType.name ];
				const icon = icons[ objectType.name ];
				return (
					<MenuItem
						key={ objectType.id }
						onClick={ () => updateFilter( objectType.id ) }
						className="menu-item"
						aria-pressed={ selectedObjectTypeId === objectType.id }
						aria-label={
							/* translators: %s: object type name */
							sprintf( __( 'Filter by type: %s', 'activitypub' ) as string, translatedName as any )
						}
					>
						<Icon icon={ icon } size={ 24 } />
						<span>{ translatedName }</span>
					</MenuItem>
				);
			} ) }
		</MenuGroup>
	);
}
