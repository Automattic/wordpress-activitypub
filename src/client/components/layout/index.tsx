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
import { Outlet } from '../../router';
import Sidebar from '../sidebar';
import './style.scss';

export function Layout() {
	// Get notices for the snackbar
	const notices = useSelect( ( select ) => {
		const store = select( noticesStore ) as any;
		return store.getNotices().filter( ( notice: any ) => notice.type === 'snackbar' );
	}, [] );
	const { removeNotice } = useDispatch( noticesStore ) as any;

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
