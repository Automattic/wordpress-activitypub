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
import { Layout } from './components/layout';
import type { SocialWebSettings } from './types';
import './store'; // Import to register the store

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
				<Layout />
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
console.log( 'ActivityPub Social Web: Script loaded' );
window.wp = window.wp || {};
window.wp.activitypubSocialWeb = { initialize };
console.log( 'ActivityPub Social Web: window.wp.activitypubSocialWeb set', window.wp.activitypubSocialWeb );

export type { SocialWebSettings };
