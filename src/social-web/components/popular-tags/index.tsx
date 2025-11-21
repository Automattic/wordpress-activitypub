/**
 * Popular Tags Component
 *
 * Displays ap_tag taxonomy terms as a clickable list of popular tags
 */

import './style.scss';
import { useEntityRecords } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import clsx from 'clsx';

interface PopularTagsProps {
	onTagClick: ( tagId: number ) => void;
	selectedTagId?: number;
}

interface Tag {
	id: number;
	name: string;
	count: number;
}

export function PopularTags( { onTagClick, selectedTagId }: PopularTagsProps ) {
	const { records: tags, isResolving } = useEntityRecords( 'taxonomy', 'ap_tag', {
		per_page: 5,
		orderby: 'count',
		order: 'desc',
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

	return (
		<div className="popular-tags">
			<h3 className="popular-tags__title">{ __( 'Popular Tags', 'activitypub' ) }</h3>
			<ul className="popular-tags__list">
				{ typedTags.map( ( tag ) => (
					<li key={ tag.id } className="popular-tags__item">
						<a
							href="#"
							onClick={ ( e ) => {
								e.preventDefault();
								onTagClick( tag.id );
							} }
							className={ clsx( 'popular-tags__link', {
								'is-selected': selectedTagId === tag.id,
							} ) }
							aria-label={ __( 'Filter by tag: %s', 'activitypub' ).replace( '%s', tag.name ) }
							aria-current={ selectedTagId === tag.id ? 'true' : undefined }
						>
							#{ tag.name }
						</a>
					</li>
				) ) }
			</ul>
		</div>
	);
}
