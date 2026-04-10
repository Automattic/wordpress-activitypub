/**
 * Popular Tags Component
 *
 * Displays ap_tag taxonomy terms as a clickable list of popular tags
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
import { MenuItem, MenuGroup } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useTagFilter } from '../../hooks/use-tag-filter';
import './style.scss';

export function PopularTags(): ReactNode {
	const { records: tags, isResolving } = useEntityRecords< Term >( 'taxonomy', 'ap_tag', {
		per_page: 5,
		orderby: 'count',
		order: 'desc',
		hide_empty: true,
	} );

	const { selectedTagId, updateTagFilter } = useTagFilter();

	// Toggle: if clicking the same tag, clear the filter
	const updateFilter = ( tagId: number ): void => updateTagFilter( selectedTagId === tagId ? null : tagId );

	if ( isResolving || ! tags || tags.length === 0 ) {
		return null;
	}

	return (
		<div className="popular-tags">
			<h3 className="popular-tags__title">{ __( 'Popular Tags', 'activitypub' ) }</h3>
			<MenuGroup>
				{ tags.map(
					( tag: Term ): ReactNode => (
						<MenuItem
							key={ tag.id }
							onClick={ (): void => updateFilter( tag.id ) }
							className="menu-item"
							aria-pressed={ selectedTagId === tag.id }
							aria-label={
								/* translators: %s: tag name */
								sprintf( __( 'Filter by tag: %s', 'activitypub' ), tag.name )
							}
						>
							<span>#{ tag.name }</span>
						</MenuItem>
					)
				) }
			</MenuGroup>
		</div>
	);
}
