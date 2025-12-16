/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 *
 * On mobile (<782px), shows:
 * - SiteHubMobile header with back button and menu toggle
 * - Animated sidebar drawer (slides in from left)
 * - Full-screen content area (stage or mobile component)
 *
 * Follows @wordpress/boot architecture patterns for future compatibility.
 */

/**
 * External dependencies
 */
import type { KeyboardEvent, ReactNode } from 'react';
import { ParsedLocation } from '@tanstack/react-router';

/**
 * WordPress dependencies
 */
import {
	SnackbarList,
	__unstableMotion as motion,
	__unstableAnimatePresence as AnimatePresence,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { store as noticesStore } from '@wordpress/notices';
import { useViewportMatch, useReducedMotion } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Outlet, useLocation } from '../../router';
import Sidebar, { MenuItemConfig, menuItems } from '../sidebar';
import { SiteHubMobile } from '../site-hub';
import './style.scss';

export function Layout(): ReactNode {
	const isMobileViewport: boolean = useViewportMatch( 'medium', '<' );
	const location: ParsedLocation< any > = useLocation();
	const disableMotion: boolean = useReducedMotion();
	const [ isMobileSidebarOpen, setIsMobileSidebarOpen ] = useState( false );

	// Get the current page title from menu items based on route
	const currentTitle: string = useMemo( (): string => {
		const menuItem: MenuItemConfig = menuItems.find(
			( item: MenuItemConfig ): boolean => item.path === location.pathname
		);
		return menuItem?.label || __( 'Social Web', 'activitypub' );
	}, [ location.pathname ] );

	// Auto-close sidebar on navigation or viewport change
	useEffect( (): void => {
		setIsMobileSidebarOpen( false );
	}, [ location.pathname, isMobileViewport ] );

	// Get notices for the snackbar
	const notices = useSelect( ( select ) => {
		const { getNotices } = select( noticesStore );
		return getNotices().filter( ( notice ): boolean => notice.type === 'snackbar' );
	}, [] ) as Array< { id: string; content: string } >;
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<div className="app-layout">
			{ /* Mobile: Backdrop for sidebar drawer */ }
			<AnimatePresence>
				{ isMobileViewport && isMobileSidebarOpen && (
					<motion.div
						className="sidebar-backdrop"
						initial={ { opacity: 0 } }
						animate={ { opacity: 1 } }
						exit={ { opacity: 0 } }
						transition={ {
							type: 'tween',
							duration: disableMotion ? 0 : 0.2,
							ease: 'easeOut',
						} }
						onClick={ (): void => setIsMobileSidebarOpen( false ) }
						onKeyDown={ ( event: KeyboardEvent< HTMLDivElement > ): void => {
							if ( event.key === 'Escape' ) {
								setIsMobileSidebarOpen( false );
							}
						} }
						role="button"
						tabIndex={ -1 }
						aria-label="Close menu"
					/>
				) }
			</AnimatePresence>

			{ /* Mobile: Animated sidebar drawer */ }
			<AnimatePresence>
				{ isMobileViewport && isMobileSidebarOpen && (
					<motion.div
						className="sidebar-region is-mobile"
						initial={ { x: '-100%' } }
						animate={ { x: 0 } }
						exit={ { x: '-100%' } }
						transition={ {
							type: 'tween',
							duration: disableMotion ? 0 : 0.2,
							ease: 'easeOut',
						} }
					>
						<Sidebar />
					</motion.div>
				) }
			</AnimatePresence>

			{ /* Desktop: Static sidebar + content */ }
			{ ! isMobileViewport && (
				<div className="app-content">
					<div className="sidebar-region">
						<Sidebar />
					</div>
					<Outlet />
				</div>
			) }

			{ /* Mobile: Header + content */ }
			{ isMobileViewport && (
				<div className="app-content is-mobile">
					<SiteHubMobile title={ currentTitle } onMenuClick={ (): void => setIsMobileSidebarOpen( true ) } />
					<Outlet />
				</div>
			) }

			<SnackbarList notices={ notices } onRemove={ removeNotice } />
		</div>
	);
}
