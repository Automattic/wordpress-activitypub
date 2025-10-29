/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { addQueryArgs, getQueryArgs, removeQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import type { Route, Location } from './types';

/**
 * Parse the current URL and return location data
 */
function getLocationFromURL(): Location {
	const args = getQueryArgs( window.location.href );
	const path = args.path || '/';
	const pathParts = path.split( '/' ).filter( Boolean );

	return {
		path,
		params: {
			section: pathParts[ 0 ] || 'dashboard',
			id: pathParts[ 1 ] || undefined,
		},
		query: args,
	};
}

/**
 * Update the URL without reloading the page
 */
function updateURL( path: string, query?: Record< string, string > ) {
	const currentArgs = getQueryArgs( window.location.href );
	let newUrl = window.location.pathname;

	// Build new query args
	const newArgs = {
		...currentArgs,
		...query,
		path,
		page: 'activitypub-social-web', // Always keep our page param
	};

	// Remove undefined values
	Object.keys( newArgs ).forEach( ( key ) => {
		if ( newArgs[ key ] === undefined ) {
			delete newArgs[ key ];
		}
	} );

	newUrl = addQueryArgs( newUrl, newArgs );

	window.history.pushState( { path }, '', newUrl );
}

/**
 * Router hook for navigation
 */
export function useRouter() {
	const [ location, setLocation ] = useState< Location >( getLocationFromURL() );

	useEffect( () => {
		// Handle browser back/forward
		const handlePopState = () => {
			setLocation( getLocationFromURL() );
		};

		window.addEventListener( 'popstate', handlePopState );
		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [] );

	const navigate = ( path: string, query?: Record< string, string > ) => {
		updateURL( path, query );
		setLocation( getLocationFromURL() );
	};

	const goBack = () => {
		window.history.back();
	};

	return {
		location,
		navigate,
		goBack,
	};
}

/**
 * Define routes for the application
 */
export const routes: Route[] = [
	{
		name: 'dashboard',
		path: '/',
		label: 'Dashboard',
	},
	{
		name: 'followers',
		path: '/followers',
		label: 'Followers',
	},
	{
		name: 'follower-details',
		path: '/followers/:id',
		label: 'Follower Details',
		parent: 'followers',
	},
	{
		name: 'following',
		path: '/following',
		label: 'Following',
	},
	{
		name: 'following-details',
		path: '/following/:id',
		label: 'Following Details',
		parent: 'following',
	},
	{
		name: 'interactions',
		path: '/interactions',
		label: 'Interactions',
	},
	{
		name: 'interaction-details',
		path: '/interactions/:id',
		label: 'Interaction Details',
		parent: 'interactions',
	},
];

/**
 * Get the current route based on location
 */
export function getCurrentRoute( location: Location ): Route | undefined {
	const { path } = location;

	// Find exact match first
	let route = routes.find( ( r ) => r.path === path );

	// If no exact match, try to match with params
	if ( ! route ) {
		route = routes.find( ( r ) => {
			const pattern = r.path.replace( /:[\w]+/g, '[^/]+' );
			const regex = new RegExp( `^${ pattern }$` );
			return regex.test( path );
		} );
	}

	return route;
}
