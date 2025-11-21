/**
 * Popular Tags Component
 *
 * Displays ap_tag taxonomy terms as a clickable list of popular tags
 */

import './style.scss';
import { useEntityRecords } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { useView } from '@wordpress/views';
import { MenuItem, MenuGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../../store';

interface PopularTagsProps {}

interface Tag {
	id: number;
	name: string;
	count: number;
}

export function PopularTags( {}: PopularTagsProps ) {
	const { records: tags, isResolving } = useEntityRecords( 'taxonomy', 'ap_tag', {
		per_page: 5,
		orderby: 'count',
		order: 'desc',
	} );

	const selectedTagId = useSelect( ( select ) => select( STORE_NAME ).getSelectedTagId(), [] );
	const { setSelectedTag } = useDispatch( STORE_NAME );

	// Get the view to update filters
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
	} );

	if ( isResolving ) {
		return (
			<div className="popular-tags">
				<h3 className="popular-tags__title">{ __( 'Popular Tags', 'activitypub' ) }</h3>
				<div className="popular-tags__loading">{ __( 'Loading tags…', 'activitypub' ) }</div>
			</div>
		);
	}

	if ( ! tags || tags.length === 0 ) {
		return null;
	}

	const typedTags = tags as Tag[];

	const handleTagClick = ( tagId: number ) => {
		const currentFilters = view.filters || [];
		const tagFilterIndex = currentFilters.findIndex( ( f ) => f.field === 'ap_tag' );

		let newFilters;
		let shouldOpenFilters = false;

		if ( tagFilterIndex !== -1 ) {
			// Tag filter exists - toggle it
			const currentValue = currentFilters[ tagFilterIndex ].value as number[];
			if ( currentValue.includes( tagId ) ) {
				// Remove the tag filter if it's the same tag
				newFilters = currentFilters.filter( ( f ) => f.field !== 'ap_tag' );
			} else {
				// Replace with new tag
				newFilters = [
					...currentFilters.slice( 0, tagFilterIndex ),
					{ field: 'ap_tag', operator: 'isAny', value: [ tagId ] },
					...currentFilters.slice( tagFilterIndex + 1 ),
				];
				shouldOpenFilters = true;
			}
		} else {
			// No tag filter exists - add one
			newFilters = [ ...currentFilters, { field: 'ap_tag', operator: 'isAny', value: [ tagId ] } ];
			shouldOpenFilters = true;
		}

		// Update the view with new filters
		updateView( {
			...view,
			filters: newFilters,
			page: 1, // Reset to first page
			openFilters: shouldOpenFilters ? true : view.openFilters,
		} );

		// Also update the store for inspector synchronization
		setSelectedTag( selectedTagId === tagId ? null : tagId );
	};

	return (
		<div className="popular-tags">
			<h3 className="popular-tags__title">{ __( 'Popular Tags', 'activitypub' ) }</h3>
			<MenuGroup>
				{ typedTags.map( ( tag ) => (
					<MenuItem
						key={ tag.id }
						onClick={ () => handleTagClick( tag.id ) }
						className="menu-item"
						isSelected={ selectedTagId === tag.id }
						aria-label={ __( 'Filter by tag: %s', 'activitypub' ).replace( '%s', tag.name ) }
					>
						<span>#{ tag.name }</span>
					</MenuItem>
				) ) }
			</MenuGroup>
		</div>
	);
}
