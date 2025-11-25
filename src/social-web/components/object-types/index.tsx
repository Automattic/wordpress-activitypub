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
import { postContent, audio, media, calendar, image, comment, page, pin, video, post } from '@wordpress/icons';

// Translations for object type names - matches object-type field definitions
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

// Icon mapping for object types
const icons: Record< string, any > = {
	Article: postContent,
	Audio: audio,
	Document: media,
	Event: calendar,
	Image: image,
	Note: comment,
	Page: page,
	Place: pin,
	Video: video,
};

// Default icon for unmapped types
const defaultIcon = post;

export function ObjectTypes() {
	const { records: objectTypes, isResolving } = useEntityRecords< Term >( 'taxonomy', 'ap_object_type', {
		per_page: -1,
		orderby: 'count',
		order: 'desc',
		hide_empty: true,
	} );

	const { selectedObjectTypeId, updateObjectTypeFilter } = useObjectTypeFilter();

	// Toggle: if clicking the same object type, clear the filter
	const updateFilter = ( objectTypeId: number ): void =>
		updateObjectTypeFilter( selectedObjectTypeId === objectTypeId ? null : objectTypeId );

	if ( isResolving || ! objectTypes || objectTypes.length === 0 ) {
		return null;
	}

	return (
		<MenuGroup className="object-types-menu">
			{ objectTypes.map( ( objectType: Term ) => {
				const translatedName = translations[ objectType.name ] || objectType.name;
				const icon = icons[ objectType.name ] || defaultIcon;
				const isSelected = selectedObjectTypeId === objectType.id;
				return (
					<MenuItem
						key={ objectType.id }
						isSelected={ isSelected }
						onClick={ () => updateFilter( objectType.id ) }
						className={ `menu-item${ isSelected ? ' is-selected' : '' }` }
						aria-label={
							/* translators: %s: object type name */
							sprintf( __( 'Filter by type: %s', 'activitypub' ) as string, translatedName as any )
						}
					>
						<Icon icon={ icon } size={ 20 } />
						<span>{ translatedName }</span>
					</MenuItem>
				);
			} ) }
		</MenuGroup>
	);
}
