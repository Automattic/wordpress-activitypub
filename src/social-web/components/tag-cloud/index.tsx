/**
 * Tag Cloud Component
 *
 * Displays ap_tag taxonomy terms as a clickable tag cloud
 */

import './style.scss';
import { useEntityRecords } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';

interface TagCloudProps {
	onTagClick: ( tagId: number ) => void;
	selectedTagId?: number;
}

interface Tag {
	id: number;
	name: string;
	count: number;
}

export function TagCloud( { onTagClick, selectedTagId }: TagCloudProps ) {
	const { records: tags, isResolving } = useEntityRecords( 'taxonomy', 'ap_tag', {
		per_page: 5,
		orderby: 'count',
		order: 'desc',
	} );

	if ( isResolving ) {
		return (
			<div className="tag-cloud">
				<h3 className="tag-cloud__title">{ __( 'Trending Tags', 'activitypub' ) }</h3>
				<div className="tag-cloud__loading">{ __( 'Loading tags…', 'activitypub' ) }</div>
			</div>
		);
	}

	if ( ! tags || tags.length === 0 ) {
		return null;
	}

	const typedTags = tags as Tag[];

	return (
		<div className="tag-cloud">
			<h3 className="tag-cloud__title">{ __( 'Trending Tags', 'activitypub' ) }</h3>
			<ul className="tag-cloud__list">
				{ typedTags.map( ( tag ) => (
					<li key={ tag.id } className="tag-cloud__item">
						<button
							onClick={ () => onTagClick( tag.id ) }
							className={ `tag-cloud__tag${ selectedTagId === tag.id ? ' is-selected' : '' }` }
						>
							#{ tag.name }
						</button>
					</li>
				) ) }
			</ul>
		</div>
	);
}
