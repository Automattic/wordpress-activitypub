import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { useOptions } from '../shared/use-options';

function getPath( userId, per_page, order, page ) {
	const { namespace } = useOptions();
	const path = `/${ namespace }/actors/${ userId }/followers`;
	const args = {
		per_page,
		order,
		page,
		context: 'full',
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
	page: passedPage,
	setPage: passedSetPage,
	followLinks = true,
	followerData = false,
} ) {
	const userId = selectedUser === 'site' ? 0 : selectedUser;
	const [ followers, setFollowers ] = useState( [] );
	const [ pages, setPages ] = useState( 0 );
	const [ total, setTotal ] = useState( 0 );
	const [ localPage, setLocalPage ] = usePage();
	const page = passedPage || localPage;
	const setPage = passedSetPage || setLocalPage;

	const setData = ( followers, total ) => {
		setFollowers( followers );
		setTotal( total );
		setPages( Math.ceil( total / per_page ) );
	};

	useEffect( () => {
		if ( followerData && page === 1 ) {
			return setData( followerData.followers, followerData.total );
		}

		const path = getPath( userId, per_page, order, page );
		apiFetch( { path } )
			.then( ( data ) => setData( data.orderedItems, data.totalItems ) )
			.catch( () => {} );
	}, [ userId, per_page, order, page, followerData ] );

	// Create pagination navigation with buttons that match the frontend
	const renderPagination = () => {
		if ( pages <= 1 ) {
			return null;
		}

		const hidePrevButton = page <= 1;
		const hideNextButton = page >= pages;

		return (
			<nav className="followers-pagination" role="navigation">
				<h1 className="screen-reader-text">{ __( 'Follower navigation', 'activitypub' ) }</h1>
				<button
					className="pagination-prev wp-block-button__link wp-element-button"
					onClick={ () => setPage( page - 1 ) }
					disabled={ hidePrevButton }
					aria-label={ __( 'Previous page', 'activitypub' ) }
				>
					{ __( 'Previous', 'activitypub' ) }
				</button>

				<div className="pagination-info">{ `${ page } / ${ pages }` }</div>

				<button
					className="pagination-next wp-block-button__link wp-element-button"
					onClick={ () => setPage( page + 1 ) }
					disabled={ hideNextButton }
					aria-label={ __( 'Next page', 'activitypub' ) }
				>
					{ __( 'Next', 'activitypub' ) }
				</button>
			</nav>
		);
	};

	return (
		<div className="followers-container">
			<ul className="followers-list">
				{ followers &&
					followers.map( ( follower ) => (
						<li key={ follower.url } className="follower-item">
							<Follower { ...follower } followLinks={ followLinks } />
						</li>
					) ) }
			</ul>
			{ pages > 1 && renderPagination() }
		</div>
	);
}

function Follower( { name, icon, url, preferredUsername, followLinks = true } ) {
	const handle = `@${ preferredUsername }`;
	const extraProps = {};
	if ( ! followLinks ) {
		extraProps.onClick = ( event ) => event.preventDefault();
	}
	const { defaultAvatarUrl } = useOptions();
	const avatar = icon.url || defaultAvatarUrl;

	return (
		<a
			className="follower-link"
			href={ url }
			title={ handle }
			target="_blank"
			rel="external noreferrer noopener"
			{ ...extraProps }
		>
			<img
				width="48"
				height="48"
				src={ avatar }
				className="follower-avatar"
				alt={ name }
				onError={ ( e ) => {
					e.target.src = defaultAvatarUrl;
				} }
			/>
			<div className="follower-info">
				<span className="follower-name">{ name }</span>
				<span className="follower-username">{ handle }</span>
			</div>
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				width="24"
				height="24"
				className="external-link-icon"
				aria-hidden="true"
				focusable="false"
			>
				<path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
			</svg>
		</a>
	);
}
