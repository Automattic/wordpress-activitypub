/**
 * WordPress dependencies
 */
import { createRoot, StrictMode } from '@wordpress/element';
import { SlotFillProvider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Router from './router';
import type { SocialWebSettings } from './types';
import './store'; // Import to register the store
import './style.scss'; // Import all styles

/**
 * Initialize the Social Web application.
 *
 * @param id The ID of the root element.
 */
export function initialize( id: string ): void {
	const target = document.getElementById( id );
	if ( ! target ) {
		return;
	}

	const root = createRoot( target );
	root.render(
		<StrictMode>
			<SlotFillProvider>
				<Router />
			</SlotFillProvider>
		</StrictMode>
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
