/**
 * WordPress dependencies
 */
import React from 'react';
import { createRoot } from '@wordpress/element';
import { SlotFillProvider } from '@wordpress/components';
import { ShortcutProvider } from '@wordpress/keyboard-shortcuts';

/**
 * Internal dependencies
 */
import Router from './router';
import { Layout } from './components/layout';
import { SettingsProvider } from './contexts/settings-context';
import { ObjectTypeProvider } from './contexts/object-type-context';
import type { AppSettings } from './types';
import type { Route } from './router/types';
import './store'; // Import to register the store
import './style.scss'; // Import all styles

/**
 * Route definitions for the App application.
 */
const routes: Route[] = [
	{
		path: '/',
		contentLoader: () => import( /* webpackChunkName: "app/feed-content" */ './routes/feed/content' ),
		routeLoader: () => import( /* webpackChunkName: "app/feed-route" */ './routes/feed/route' ),
	},
];

/**
 * Initialize the App application.
 *
 * @param id       The ID of the root element.
 * @param settings The editor settings.
 */
export function initialize( id: string, settings: AppSettings ): void {
	const target = document.getElementById( id );
	if ( ! target ) {
		return;
	}

	const root = createRoot( target );
	root.render(
		<SettingsProvider settings={ settings }>
			<ObjectTypeProvider>
				<ShortcutProvider>
					<SlotFillProvider>
						<Router routes={ routes } rootComponent={ Layout } />
					</SlotFillProvider>
				</ShortcutProvider>
			</ObjectTypeProvider>
		</SettingsProvider>
	);
}

// Extend Window interface for type safety.
declare global {
	interface Window {
		wp: {
			activitypubApp?: {
				initialize: typeof initialize;
			};
		};
	}
}

// Export to window for inline script access.
window.wp = window.wp || {};
window.wp.activitypubApp = { initialize };

export type { AppSettings };
