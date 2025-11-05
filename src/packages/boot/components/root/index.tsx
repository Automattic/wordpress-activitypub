/**
 * External dependencies
 */
import { Outlet } from '@tanstack/react-router';

/**
 * WordPress dependencies
 */
// @ts-expect-error Commands is not typed properly.
import { CommandMenu } from '@wordpress/commands';

/**
 * Internal dependencies
 */
import Sidebar from '../sidebar';
import './style.scss';

export default function Root() {
	return (
		<div className="boot-layout">
			<CommandMenu />
			<div className="boot-layout__content">
				<div className="boot-layout__sidebar-region">
					<div className="boot-layout__sidebar">
						<Sidebar />
					</div>
				</div>
				<div className="boot-layout__stage">
					<Outlet />
				</div>
			</div>
		</div>
	);
}
