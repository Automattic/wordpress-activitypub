/**
 * External dependencies
 */
import {
	createRouter,
	createRootRoute,
	createRoute,
	RouterProvider,
	createBrowserHistory,
} from '@tanstack/react-router';
import { parseHref } from '@tanstack/history';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import Root from '../packages/boot/components/root';
import { STORE_NAME } from '../packages/boot/store';
import { createRoutesFromDefinitions, menuItems } from './routes';

// Register menu items with boot's store
menuItems.forEach( ( menuItem ) => {
	// @ts-ignore
	dispatch( STORE_NAME ).registerMenuItem( menuItem.id, menuItem );
} );

// Not found component
function NotFoundComponent() {
	return (
		<div className="boot-layout__stage" style={ { padding: '24px' } }>
			<h1>{ __( 'Route not found' ) }</h1>
		</div>
	);
}

// Create root route with boot's Root component
const rootRoute = createRootRoute( {
	component: Root,
} );

// Create routes from definitions
const routes = createRoutesFromDefinitions( rootRoute );

// Create route tree
const routeTree = rootRoute.addChildren( routes );

// Create custom history that parses ?p= query parameter
function createPathHistory() {
	return createBrowserHistory( {
		parseLocation: () => {
			const url = new URL( window.location.href );
			const path = url.searchParams.get( 'p' ) || '/';
			const pathHref = `${ path }${ url.hash }`;
			return parseHref( pathHref, window.history.state );
		},
		createHref: ( href ) => {
			const searchParams = new URLSearchParams( window.location.search );
			searchParams.set( 'p', href );
			return `${ window.location.pathname }?${ searchParams }`;
		},
	} );
}

const history = createPathHistory();

// Create router
const router = createRouter( {
	history,
	routeTree,
	defaultNotFoundComponent: NotFoundComponent,
} );

export default function Router() {
	return <RouterProvider router={ router } />;
}
