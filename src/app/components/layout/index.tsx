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

/**
 * WordPress dependencies
 */
import {
	SnackbarList,
	__unstableMotion as motion,
	__unstableAnimatePresence as AnimatePresence,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { useViewportMatch, useReducedMotion } from '@wordpress/compose';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Outlet } from '../../router';
import Sidebar from '../sidebar';
import { SiteHubMobile } from '../site-hub';
import './style.scss';

interface LayoutProps {
	children?: ReactNode;
}

export function Layout( { children }: LayoutProps ): ReactNode {
	const isMobileViewport: boolean = useViewportMatch( 'medium', '<' );
	const disableMotion: boolean = useReducedMotion();
	const [ isMobileSidebarOpen, setIsMobileSidebarOpen ] = useState( false );
	const content: ReactNode = children ?? <Outlet />;

	// Snackbar notices dispatched by route actions (e.g. follow/block) render here.
	const notices = useSelect( ( select ) => {
		const { getNotices } = select( noticesStore );
		return getNotices().filter( ( notice ): boolean => notice.type === 'snackbar' );
	}, [] ) as Array< { id: string; content: string } >;
	const { removeNotice } = useDispatch( noticesStore );

	// Auto-close sidebar on viewport change. Core boot owns route state.
	useEffect( (): void => {
		setIsMobileSidebarOpen( false );
	}, [ isMobileViewport ] );

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
						aria-label={ __( 'Close menu', 'activitypub' ) }
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
					{ content }
				</div>
			) }

			{ /* Mobile: Header + content */ }
			{ isMobileViewport && (
				<div className="app-content is-mobile">
					<SiteHubMobile
						title={ __( 'Feed', 'activitypub' ) }
						onMenuClick={ (): void => setIsMobileSidebarOpen( true ) }
					/>
					{ content }
				</div>
			) }

			<SnackbarList notices={ notices } onRemove={ removeNotice } />
		</div>
	);
}
