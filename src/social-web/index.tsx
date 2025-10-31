/**
 * WordPress dependencies
 */
import { createRoot, lazy } from '@wordpress/element';
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

// Lazy load route components for code splitting
// Dashboard is loaded in layout/index.tsx where it's used

const FollowersStage = lazy(
	() => import( /* webpackChunkName: "social-web/followers" */ './routes/followers/stage' )
);
const FollowingStage = lazy(
	() => import( /* webpackChunkName: "social-web/following" */ './routes/following/stage' )
);
const InteractionsStage = lazy(
	() => import( /* webpackChunkName: "social-web/interactions" */ './routes/interactions/stage' )
);

// Lazy load inspector components - using same chunk names to combine with stages
const FollowerInspector = lazy(
	() => import( /* webpackChunkName: "social-web/followers" */ './routes/followers/inspector' )
);
const FollowingInspector = lazy(
	() => import( /* webpackChunkName: "social-web/following" */ './routes/following/inspector' )
);
const InteractionInspector = lazy(
	() => import( /* webpackChunkName: "social-web/interactions" */ './routes/interactions/inspector' )
);

const { RouterProvider } = unlock( routerPrivateApis );

/*
 * Define routes for the router to match
 * Following Gutenberg's pattern where routes define their rendering areas
 * Note: Dashboard doesn't define areas in the route to avoid hooks issues with root path
 */
const routes = [
	{
		name: 'dashboard',
		path: '/',
		// Dashboard stage is rendered in layout component to avoid hooks issues
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
