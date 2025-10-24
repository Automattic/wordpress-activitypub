/**
 * Admin Followers List.
 */

import React from 'react';
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { FollowersDataViews } from './components/FollowersDataViews';
import './style.scss';

/**
 * Initialize the Followers DataViews component.
 */
function initFollowersDataViews() {
	const rootElement = document.getElementById( 'activitypub-followers-root' );

	if ( ! rootElement ) {
		return;
	}

	// Create React root and render the component
	createRoot( rootElement ).render( <FollowersDataViews userId={ window.activityPubAdmin?.userId || 0 } /> );
}

// Initialize when DOM is ready
domReady( initFollowersDataViews );
