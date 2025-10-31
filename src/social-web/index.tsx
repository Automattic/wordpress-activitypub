/**
 * WordPress dependencies
 */
import React from 'react';
import { createRoot } from '@wordpress/element';
import { SlotFillProvider } from '@wordpress/components';
import { ShortcutProvider } from '@wordpress/keyboard-shortcuts';
import { privateApis as routerPrivateApis } from '@wordpress/router';

/**
 * Internal dependencies
 */
import { Layout } from './components/layout';
import type { SocialWebSettings } from './types';
import { unlock } from './lock-unlock';
import './store'; // Import to register the store
import './style.scss'; // Import all styles

// Import route components
import DashboardStage from './routes/dashboard/stage';
import FollowersStage from './routes/followers/stage';
import FollowingStage from './routes/following/stage';
import InteractionsStage from './routes/interactions/stage';
import FollowerInspector from './routes/followers/inspector';
import FollowingInspector from './routes/following/inspector';
import InteractionInspector from './routes/interactions/inspector';

const { RouterProvider } = unlock( routerPrivateApis );

// Define routes for the router to match
// Following Gutenberg's pattern where routes define their rendering areas
// Note: Dashboard doesn't define areas in the route to avoid hooks issues with root path
const routes = [
	{
		name: 'dashboard',
		path: '/',
	},
	{
		name: 'followers',
		path: '/followers',
		areas: {
			stage: FollowersStage,
			inspector: FollowerInspector,
		},
	},
	{
		name: 'following',
		path: '/following',
		areas: {
			stage: FollowingStage,
			inspector: FollowingInspector,
		},
	},
	{
		name: 'interactions',
		path: '/interactions',
		areas: {
			stage: InteractionsStage,
			inspector: InteractionInspector,
		},
	},
];

/**
 * Initialize the Social Web application.
 *
 * @param id       The ID of the root element.
 * @param settings The editor settings.
 */
export function initialize( id: string, settings: SocialWebSettings ): void {
	const target = document.getElementById( id );
	if ( ! target ) {
		return;
	}

	const root = createRoot( target );
	root.render(
		<ShortcutProvider>
			<SlotFillProvider>
				<RouterProvider
					routes={ routes }
					pathArg="p"
					beforeNavigate={ ( { path, query } ) => ( { path, query } ) }
					matchResolverArgs={ {} }
				>
					<Layout />
				</RouterProvider>
			</SlotFillProvider>
		</ShortcutProvider>
	);
}

// Extend Window interface for type safety.
declare global {
	interface Window {
		wp: {
			activitypubSocialWeb?: {
				initialize: typeof initialize;
			};
		};
	}
}

// Export to window for inline script access.
window.wp = window.wp || {};
window.wp.activitypubSocialWeb = { initialize };

export type { SocialWebSettings };
