/**
 * External dependencies
 */
import { createRoute } from '@tanstack/react-router';
import type { RootRoute } from '@tanstack/react-router';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { home, people, addCard, comment } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Dashboard from './dashboard';
import Followers from './followers';
import Following from './following';
import Interactions from './interactions';
import type { MenuItem } from '../../packages/boot/store/types';

export interface RouteDefinition {
	id: string;
	path: string;
	label: string;
	icon: any;
	component: React.ComponentType;
}

export const routeDefinitions: RouteDefinition[] = [
	{
		id: 'home',
		path: '/',
		label: __( 'Dashboard' ),
		icon: home,
		component: Dashboard,
	},
	{
		id: 'followers',
		path: '/followers',
		label: __( 'Followers' ),
		icon: people,
		component: Followers,
	},
	{
		id: 'following',
		path: '/following',
		label: __( 'Following' ),
		icon: addCard,
		component: Following,
	},
	{
		id: 'interactions',
		path: '/interactions',
		label: __( 'Interactions' ),
		icon: comment,
		component: Interactions,
	},
];

// Export menu items derived from route definitions
export const menuItems: MenuItem[] = routeDefinitions.map( ( route ) => ( {
	id: route.id,
	label: route.label,
	to: route.path,
	icon: route.icon,
} ) );

// Function to create TanStack routes from definitions
export function createRoutesFromDefinitions( rootRoute: RootRoute ) {
	return routeDefinitions.map( ( routeDef ) =>
		createRoute( {
			getParentRoute: () => rootRoute,
			path: routeDef.path,
			component: routeDef.component,
		} )
	);
}
