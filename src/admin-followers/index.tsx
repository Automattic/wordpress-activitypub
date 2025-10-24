/**
 * Admin Followers List.
 */

import React from 'react';
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { FollowersDataViews } from './components/FollowersDataViews';
import './style.scss';

/**
 * FollowersList component.
 */
function FollowersList() {
	const notices = useSelect( ( select ) => {
		const store = select( noticesStore ) as any;
		return store.getNotices().filter( ( notice: any ) => notice.type === 'snackbar' );
	}, [] );
	const { removeNotice } = useDispatch( noticesStore ) as any;

	return (
		<>
			<FollowersDataViews userId={ window.activityPubAdmin?.userId || 0 } />
			<SnackbarList notices={ notices } onRemove={ removeNotice } />
		</>
	);
}

domReady( () => {
	createRoot( document.getElementById( 'activitypub-followers-root' ) ).render( <FollowersList /> );
} );
