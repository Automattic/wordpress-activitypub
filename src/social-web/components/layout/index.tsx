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
import { useState, useEffect, useCallback } from '@wordpress/element';
import { store as noticesStore } from '@wordpress/notices';
import { Outlet, useLocation, useNavigate } from '../../router';
import { useMobileViewport } from '../../hooks/use-mobile-viewport';
import Sidebar from '../sidebar';
import SiteHub from '../site-hub';
import './style.scss';

// Session storage key for mobile sidebar state
const MOBILE_SIDEBAR_KEY = 'activitypub-mobile-sidebar';

export function Layout() {
	// Detect mobile viewport using WordPress standard breakpoint
	const isMobileViewport = useMobileViewport();

	// Get current location from router - includes search params
	const location = useLocation();
	const navigate = useNavigate();

	// TanStack Router's useLocation includes search as a property
	const search = ( location.search || {} ) as Record< string, unknown >;
	const hasInspector = 'postId' in search && search.postId;

	// Mobile sidebar state - persisted in sessionStorage
	// Default to true (show menu) on first visit
	const [ isMobileSidebarOpen, setIsMobileSidebarOpen ] = useState( () => {
		if ( typeof window === 'undefined' ) {
			return true;
		}
		const stored = sessionStorage.getItem( MOBILE_SIDEBAR_KEY );
		return stored === null ? true : stored === 'true';
	} );

	// Sync state to sessionStorage
	useEffect( () => {
		sessionStorage.setItem( MOBILE_SIDEBAR_KEY, String( isMobileSidebarOpen ) );
	}, [ isMobileSidebarOpen ] );

	// Close sidebar when inspector opens
	useEffect( () => {
		if ( hasInspector ) {
			setIsMobileSidebarOpen( false );
		}
	}, [ hasInspector ] );

	// Get notices for the snackbar
	const notices = useSelect( ( select ) => {
		const store = select( noticesStore ) as any;
		return store.getNotices().filter( ( notice: any ) => notice.type === 'snackbar' );
	}, [] );
	const { removeNotice } = useDispatch( noticesStore ) as any;

	// Mobile back navigation
	const navigateBack = useCallback( () => {
		if ( hasInspector ) {
			// From inspector → stage: remove postId from search params
			navigate( {
				search: ( prev: Record< string, unknown > ) => {
					const { postId, ...rest } = prev;
					return rest;
				},
			} );
		} else if ( isMobileSidebarOpen ) {
			// From sidebar → stage: close sidebar
			setIsMobileSidebarOpen( false );
		} else {
			// From stage → sidebar: open sidebar
			setIsMobileSidebarOpen( true );
		}
	}, [ hasInspector, isMobileSidebarOpen, navigate ] );

	// Close mobile sidebar (used by menu items)
	const closeMobileSidebar = useCallback( () => {
		setIsMobileSidebarOpen( false );
	}, [] );

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

	// Determine mobile view state for back button behavior
	let mobileViewState = 'stage';
	if ( hasInspector ) {
		mobileViewState = 'inspector';
	} else if ( isMobileSidebarOpen ) {
		mobileViewState = 'sidebar';
	}

	// Mobile layout: SiteHub header + active region only
	return (
		<div className="app-layout app-layout--mobile" data-mobile-view={ mobileViewState }>
			<CommandMenu />

			{ /* Mobile SiteHub header with back navigation */ }
			<SiteHub onNavigateBack={ navigateBack } showBackButton={ hasInspector || isMobileSidebarOpen } />

			<div className="app-content app-content--mobile">
				{ /* Show sidebar when open on mobile */ }
				{ isMobileSidebarOpen && ! hasInspector && (
					<div className="sidebar-region sidebar-region--mobile">
						<Sidebar onNavigate={ closeMobileSidebar } />
					</div>
				) }

				{ /* Route content (stage or inspector) - hidden when sidebar is open */ }
				{ ! isMobileSidebarOpen && <Outlet /> }
			</div>

			<SnackbarList notices={ notices } onRemove={ removeNotice } />
		</div>
	);
}
