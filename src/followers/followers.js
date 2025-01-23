import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Pagination } from './pagination';
import { ExternalLink } from '@wordpress/components';
import { useOptions } from '../shared/use-options';

function getPath( userId, per_page, order, page ) {
	const { namespace } = useOptions();
	const path = `/${ namespace }/actors/${ userId }/followers`;
	const args = {
		per_page,
		order,
		page,
		context: 'full'
	};
	return addQueryArgs( path, args );
}

function usePage() {
	const [ page, setPage ] = useState( 1 );
	return [ page, setPage ];
}

export function Followers( {
	selectedUser,
	per_page,
	order,
	title,
	page: passedPage,
	setPage: passedSetPage,
	className = '',
	followLinks = true,
	followerData = false
} ) {
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const [ followers, setFollowers ] = useState( [] );
	const [ pages, setPages ] = useState( 0 );
	const [ total, setTotal ] = useState( 0 );
	const [ localPage, setLocalPage ] = usePage();
	const page = passedPage || localPage;
	const setPage = passedSetPage || setLocalPage;
	const prevLabel = createInterpolateElement(
		/* translators: arrow for previous followers link */
		__( '<span>←</span> Less', 'activitypub' ),
		{
			span: <span className="wp-block-query-pagination-previous-arrow is-arrow-arrow" aria-hidden="true" />,
		}
	);
	const nextLabel = createInterpolateElement(
		/* translators: arrow for next followers link */
		__( 'More <span>→</span>', 'activitypub' ),
		{
			span: <span className="wp-block-query-pagination-next-arrow is-arrow-arrow" aria-hidden="true" />,
		}
	);

	const setData = ( followers, total ) => {
		setFollowers( followers );
		setTotal( total );
		setPages( Math.ceil( total / per_page ) );
	}

	useEffect( () => {
		if ( followerData && page === 1 ) {
			return setData( followerData.followers, followerData.total );
		}

		const path = getPath( userId, per_page, order, page );
		apiFetch( { path } )
			.then( ( data ) => setData( data.orderedItems, data.totalItems ) )
			.catch( () => {} );
	}, [ userId, per_page, order, page, followerData ] );
	return (
		<div className={ "activitypub-follower-block " + className }>
			<h3>{ title }</h3>
				<ul>
				{ followers && followers.map( ( follower ) => (
					<li key={ follower.url }>
						<Follower { ...follower } followLinks={ followLinks } />
					</li>
				) ) }
				</ul>
				{ pages > 1 && (
					<Pagination
						page={ page }
						perPage={ per_page }
						total={ total }
						pageClick={ setPage }
						nextLabel={ nextLabel }
						prevLabel={ prevLabel }
						compact={ className === 'is-style-compact' }
					/>
				) }
		</div>
	);
}

function Follower( { name, icon, url, preferredUsername, followLinks = true } ) {
	const handle = `@${ preferredUsername }`;
	const extraProps = {};
	if ( ! followLinks ) {
		extraProps.onClick = event => event.preventDefault();
	}
	return (
		<ExternalLink className="activitypub-link" href={ url } title={ handle } { ...extraProps }>
			<img
				width="40"
				height="40"
				src={ icon.url }
				className="avatar activitypub-avatar"
				alt={ name }
			/>
			<span className="activitypub-actor">
				<strong className="activitypub-name">{ name }</strong>
				<span className="sep">/</span>
				<span className="activitypub-handle">{ handle }</span>
			</span>
		</ExternalLink>
	)
}
