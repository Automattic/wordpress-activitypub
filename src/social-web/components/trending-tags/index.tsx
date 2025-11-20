/**
 * Trending Tags Component
 *
 * Displays ap_tag taxonomy terms as a clickable list of trending tags
 */

import './style.scss';
import { useEntityRecords } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';

interface TrendingTagsProps {
	onTagClick: ( tagId: number ) => void;
	selectedTagId?: number;
}

interface Tag {
	id: number;
	name: string;
	count: number;
}

export function TrendingTags( { onTagClick, selectedTagId }: TrendingTagsProps ) {
	const { records: tags, isResolving } = useEntityRecords( 'taxonomy', 'ap_tag', {
		per_page: 5,
		orderby: 'count',
		order: 'desc',
	} );

	if ( isResolving ) {
		return (
			<div className="trending-tags">
				<h3 className="trending-tags__title">{ __( 'Trending Tags', 'activitypub' ) }</h3>
				<div className="trending-tags__loading">{ __( 'Loading tags…', 'activitypub' ) }</div>
			</div>
		);
	}

	if ( ! tags || tags.length === 0 ) {
		return null;
	}

	const typedTags = tags as Tag[];

	return (
		<div className="trending-tags">
			<h3 className="trending-tags__title">{ __( 'Trending Tags', 'activitypub' ) }</h3>
			<ul className="trending-tags__list">
				{ typedTags.map( ( tag ) => (
					<li key={ tag.id } className="trending-tags__item">
						<button
							onClick={ () => onTagClick( tag.id ) }
							className={ `trending-tags__tag${ selectedTagId === tag.id ? ' is-selected' : '' }` }
						>
							#{ tag.name }
						</button>
					</li>
				) ) }
			</ul>
		</div>
	);
}
