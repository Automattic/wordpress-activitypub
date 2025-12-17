import { createRoot } from '@wordpress/element';
import StatsWidget from './components/stats-widget';
import type { Settings } from './types';
import './style.scss';

declare global {
	interface Window {
		activitypubDashboardStats: Settings;
		activitypub: {
			dashboardStats?: {
				initialize: ( id: string, settings: Settings ) => void;
			};
		};
	}
}

/**
 * Initialize the dashboard stats widget.
 */
export function initialize( id: string, settings: Settings ) {
	const container = document.getElementById( id );

	if ( ! container ) {
		return;
	}

	// Store settings globally for the widget.
	window.activitypubDashboardStats = settings;

	const root = createRoot( container );
	root.render( <StatsWidget /> );
}

// Export to window for inline script access.
window.activitypub = window.activitypub || {};
window.activitypub.dashboardStats = { initialize };
