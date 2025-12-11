/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import { CommandMenu } from '@wordpress/commands';
import { SnackbarList } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { Outlet } from '../../router';
import Sidebar from '../sidebar';
import './style.scss';

export function Layout(): ReactNode {
	// Get notices for the snackbar
	const notices = useSelect( ( select ) => {
		const { getNotices } = select( noticesStore );
		return getNotices().filter( ( notice ): boolean => notice.type === 'snackbar' );
	}, [] ) as Array< { id: string; content: string } >;
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<div className="app-layout">
			<CommandMenu />
			<div className="app-content">
				{ /* Sidebar - 240px fixed width (no Panel wrapper, stays dark) */ }
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
