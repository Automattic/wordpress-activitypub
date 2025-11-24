/**
 * Popular Tags Component
 *
 * Displays ap_tag taxonomy terms as a clickable list of popular tags
 */

import './style.scss';
import { useEntityRecords } from '@wordpress/core-data';
import type { Term } from '@wordpress/core-data';
import { MenuItem, MenuGroup } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';
import { useTagFilter } from '../../hooks/use-tag-filter';

export function PopularTags() {
	const { records: tags, isResolving } = useEntityRecords< Term >( 'taxonomy', 'ap_tag', {
		per_page: 5,
		orderby: 'count',
		order: 'desc',
		hide_empty: true,
	} );

	const { selectedTagId, updateTagFilter } = useTagFilter();

	const updateFilter = ( tagId: number ): void => {
		// Toggle: if clicking the same tag, clear the filter
		const newTagId: number = selectedTagId === tagId ? null : tagId;
		updateTagFilter( newTagId );
	};

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

	return (
		<div className="popular-tags">
			<h3 className="popular-tags__title">{ __( 'Popular Tags', 'activitypub' ) }</h3>
			<MenuGroup>
				{ tags.map( ( tag: Term ) => (
					<MenuItem
						key={ tag.id }
						onClick={ () => updateFilter( tag.id ) }
						className="menu-item"
						aria-pressed={ selectedTagId === tag.id }
						aria-label={
							/* translators: %s: tag name */
							sprintf( __( 'Filter by tag: %s', 'activitypub' ) as string, tag.name as any )
						}
					>
						<span>#{ tag.name }</span>
					</MenuItem>
				) ) }
			</MenuGroup>
		</div>
	);
}
