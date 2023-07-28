import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Pagination } from './pagination';
import { ExternalLink } from '@wordpress/components';

const { namespace } = window._activityPubOptions;

function getPath( userId, per_page, order, page ) {
	const path = `/${ namespace }/users/${ userId }/followers`;
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
	followLinks = true
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
			span: <span class="wp-block-query-pagination-previous-arrow is-arrow-arrow" aria-hidden="true" />,
		}
	);
	const nextLabel = createInterpolateElement(
		/* translators: arrow for next followers link */
		__( 'More <span>→</span>', 'activitypub' ),
		{
			span: <span class="wp-block-query-pagination-next-arrow is-arrow-arrow" aria-hidden="true" />,
		}
	);

	useEffect( () => {
		const path = getPath( userId, per_page, order, page );
		apiFetch( { path } )
			.then( ( data ) => {
				setPages( Math.ceil( data.totalItems / per_page ) );
				setTotal( data.totalItems );
				setFollowers( data.orderedItems );
			} )
			.catch( ( error ) => console.error( error ) );
	}, [ userId, per_page, order, page ] );
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
			<img width="40" height="40" src={ icon.url } class="avatar activitypub-avatar" />
			<span class="activitypub-actor">
				<strong className="activitypub-name">{ name }</strong>
				<span class="sep">/</span>
				<span class="activitypub-handle">{ handle }</span>
			</span>
		</ExternalLink>
	)
}
