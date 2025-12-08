/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 */

import { CommandMenu } from '@wordpress/commands';
import { SnackbarList } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { Outlet, useLocation } from '../../router';
import Sidebar from '../sidebar';
import SiteHub from '../site-hub';
import './style.scss';

/**
 * Determine mobile view state based on router location.
 * - 'sidebar': At root path with no inspector open
 * - 'stage': Viewing content list (no item selected)
 * - 'inspector': Viewing item detail (postId in search params)
 *
 * @param {string}                  pathname Current route pathname.
 * @param {Record<string, unknown>} search   Current route search params.
 * @return {'sidebar' | 'stage' | 'inspector'} The mobile view state.
 */
function getMobileView( pathname: string, search: Record< string, unknown > ): 'sidebar' | 'stage' | 'inspector' {
	const hasInspector = 'postId' in search && search.postId;

	if ( hasInspector ) {
		return 'inspector';
	}

	// If we're at root without inspector, show stage (feed list)
	// The sidebar is always accessible via back navigation
	if ( pathname === '/' ) {
		return 'stage';
	}

	return 'stage';
}

export function Layout() {
	// Get current location from router - includes search params
	const location = useLocation();
	// TanStack Router's useLocation includes search as a property
	const search = ( location.search || {} ) as Record< string, unknown >;

	// Get notices for the snackbar
	const notices = useSelect( ( select ) => {
		const store = select( noticesStore ) as any;
		return store.getNotices().filter( ( notice: any ) => notice.type === 'snackbar' );
	}, [] );
	const { removeNotice } = useDispatch( noticesStore ) as any;

	// Determine mobile view state
	const mobileView = getMobileView( location.pathname, search );
	const hasInspector = mobileView === 'inspector';

	// Mobile-specific back navigation handler
	const navigateBack = () => {
		// Use browser history to navigate back
		window.history.back();
	};

	return (
		<div className="app-layout" data-mobile-view={ mobileView }>
			<CommandMenu />

			{ /* Single SiteHub instance - positioned via CSS based on mobile view */ }
			<SiteHub onNavigateBack={ navigateBack } selectedItemId={ hasInspector ? search.postId : null } />

			<div className="app-content">
				{ /* Sidebar - 300px fixed width (no Panel wrapper, stays dark) */ }
				<div className="sidebar-region">
					<Sidebar />
				</div>

				{ /* Route content (stage + inspector) rendered via Outlet */ }
				<Outlet />
			</div>

			<SnackbarList notices={ notices } onRemove={ removeNotice } />
		</div>
	);
}
