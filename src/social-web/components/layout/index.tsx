/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 *
 * On mobile, only shows SiteHub header + active region (stage or inspector).
 * Follows WordPress Site Editor mobile navigation pattern.
 */

import { CommandMenu } from '@wordpress/commands';
import { SnackbarList } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { Outlet, useLocation, useNavigate } from '../../router';
import { useMobileViewport } from '../../hooks/use-mobile-viewport';
import Sidebar from '../sidebar';
import SiteHub from '../site-hub';
import './style.scss';

export function Layout() {
	// Detect mobile viewport using WordPress standard breakpoint
	const isMobileViewport = useMobileViewport();

	// Get current location from router - includes search params
	const location = useLocation();
	const navigate = useNavigate();

	// TanStack Router's useLocation includes search as a property
	const search = ( location.search || {} ) as Record< string, unknown >;
	const hasInspector = 'postId' in search && search.postId;

	// Get notices for the snackbar
	const notices = useSelect( ( select ) => {
		const store = select( noticesStore ) as any;
		return store.getNotices().filter( ( notice: any ) => notice.type === 'snackbar' );
	}, [] );
	const { removeNotice } = useDispatch( noticesStore ) as any;

	// Mobile back navigation - uses router navigate for predictable behavior
	const navigateBack = () => {
		if ( hasInspector ) {
			// From inspector → stage: remove postId from search params
			navigate( {
				search: ( prev: Record< string, unknown > ) => {
					const { postId, ...rest } = prev;
					return rest;
				},
			} );
		}
		// From stage: SiteHub icon links to /wp-admin/ (handled in SiteHub)
	};

	// Desktop layout: Sidebar + Stage + Inspector
	if ( ! isMobileViewport ) {
		return (
			<div className="app-layout">
				<CommandMenu />
				<div className="app-content">
					{ /* Sidebar with SiteHub - 300px fixed width */ }
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

	// Mobile layout: SiteHub header + active region only
	return (
		<div className="app-layout app-layout--mobile">
			<CommandMenu />

			{ /* Mobile SiteHub header with back navigation */ }
			<SiteHub onNavigateBack={ navigateBack } selectedItemId={ hasInspector ? search.postId : null } />

			<div className="app-content app-content--mobile">
				{ /* Route content (stage or inspector) rendered via Outlet */ }
				<Outlet />
			</div>

			<SnackbarList notices={ notices } onRemove={ removeNotice } />
		</div>
	);
}
