import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

function getPath( userId, per_page, order, page ) {
	const path = `/activitypub/1.0/users/${ userId }/followers`;
	const args = {
		per_page,
		order,
		page,
		context: 'view'
	};
	return addQueryArgs( path, args );
}

export function Followers( { selectedUser, per_page, order, title } ) {
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const [ followers, setFollowers ] = useState( [] );
	const [ pages, setPages ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ total, setTotal ] = useState( 0 );
	useEffect( () => {
		const path = getPath( userId, per_page, order, page );
		apiFetch( { path } )
			.then( ( data ) => {
				setPages( data.total_pages );
				setTotal( data.total );
				setFollowers( data.followers );
			} )
			.catch( ( error ) => console.error( error ) );
	}, [ userId, per_page, order, page ] );
	return (
		<div className="activitypub-follower-block">
			<h3>{ title }</h3>
				<ul>
				{ followers && followers.map( ( follower ) => (
					<li key={ follower.url }>
						<Follower { ...follower } />
					</li>
				) ) }
				</ul>
				<Pagination { ...{ pages, page, setPage, total } } />
		</div>
	);
}

function Pagination( { pages, page, setPage, total } ) {

	const canPage = pages > 1;
	const canNextPage = page < pages;
	const canPrevPage = page > 1;
	if ( ! canPage ) {
		return null;
	}
	return (
		<>
			{ canPrevPage && <button onClick={ () => setPage( page - 1 ) }>🔙Prev</button> }
			{ canNextPage && <button onClick={ () => setPage( page + 1 ) }>Next➡️</button> }
		</>
	)

}

function Follower( { name, avatar, url, handle } ) {
	return (
		<a href={ url } title={ handle } onClick={ event => event.preventDefault() }>
			<img width="40" height="40" src={ avatar } class="avatar activitypub-avatar" />
			<span class="activitypub-actor"><strong>{ name }</strong><span class="sep">/</span>{ handle }</span>
		</a>
	)
}