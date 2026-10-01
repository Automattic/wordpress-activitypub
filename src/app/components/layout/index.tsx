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
import clsx from 'clsx';

/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { useViewportMatch } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Sidebar from '../sidebar';
import { SiteHubMobile } from '../site-hub';
import './style.scss';

interface LayoutProps {
	children?: ReactNode;
}

export function Layout( { children }: LayoutProps ): ReactNode {
	const isMobileViewport: boolean = useViewportMatch( 'medium', '<' );
	const [ isMobileSidebarOpen, setIsMobileSidebarOpen ] = useState( false );
	const content: ReactNode = children;

	// Auto-close sidebar on viewport change. Core boot owns route state.
	useEffect( (): void => {
		setIsMobileSidebarOpen( false );
	}, [ isMobileViewport ] );

	return (
		<div className="app-layout">
			{ /* Mobile: backdrop and sidebar drawer, slid in and out with CSS transitions */ }
			{ isMobileViewport && (
				<>
					<div
						className={ clsx( 'sidebar-backdrop', { 'is-open': isMobileSidebarOpen } ) }
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
					<div className={ clsx( 'sidebar-region is-mobile', { 'is-open': isMobileSidebarOpen } ) }>
						<Sidebar />
					</div>
				</>
			) }

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
		</div>
	);
}
