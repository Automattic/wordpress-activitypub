import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';

export function Followers( { selectedUser, followersToShow, title } ) {
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const [ followers, setFollowers ] = useState( [] );
	useEffect( () => {
		apiFetch( { path: `/activitypub/1.0/users/${ userId }/followers?context=view` } )
			.then( ( followers ) => setFollowers( followers ) )
			.catch( ( error ) => console.error( error ) );
	}, [ userId ] );
	return (
		<div className="activitypub-follower-block">
			<h3>{ title }</h3>
				<ul>
				{ followers && followers.map( ( follower ) => (
					<li key={ follower.id }>
						<Follower { ...follower } />
					</li>
				) ) }
				</ul>
		</div>
	);
}

function Follower( { name, avatar, url, handle } ) {
	return (
		<a href={ url } title={ handle } onClick={ event => event.preventDefault() }>
			<img width="40" height="40" src={ avatar } class="avatar activitypub-avatar" />
			<span class="activitypub-actor"><strong>{ name }</strong><span class="sep">/</span>{ handle }</span>
		</a>
	)
}