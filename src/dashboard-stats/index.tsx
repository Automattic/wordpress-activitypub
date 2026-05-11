/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
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
 *
 * @param {string} id The container element ID.
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
