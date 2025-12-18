import { createRoot } from '@wordpress/element';
import StatsWidget from './components/stats-widget';
import './style.scss';

declare global {
	interface Window {
		activitypub: {
			dashboardStats?: {
				initialize: ( id: string ) => void;
			};
		};
	}
}

/**
 * Initialize the dashboard stats widget.
 */
export function initialize( id: string ) {
	const container = document.getElementById( id );

	if ( ! container ) {
		return;
	}

	const root = createRoot( container );
	root.render( <StatsWidget /> );
}

// Export to window for inline script access.
window.activitypub = window.activitypub || {};
window.activitypub.dashboardStats = { initialize };
