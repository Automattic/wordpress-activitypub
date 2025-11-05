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
// @ts-expect-error - ThemeProvider is not typed.
import { privateApis } from '../../../theme';
import Sidebar from '../sidebar';
import './style.scss';

const ThemeProvider = privateApis.ThemeProvider;

export default function Root() {
	return (
		<ThemeProvider isRoot color={ { bg: '#f8f8f8', primary: '#3858e9' } }>
			<ThemeProvider color={ { bg: '#1e1e1e', primary: '#3858e9' } }>
				<div className="boot-layout">
					<CommandMenu />
					<div className="boot-layout__content">
						<div className="boot-layout__sidebar-region">
							<div className="boot-layout__sidebar">
								<Sidebar />
							</div>
						</div>
						<ThemeProvider color={ { bg: '#ffffff', primary: '#3858e9' } }>
							<div className="boot-layout__stage">
								<Outlet />
							</div>
						</ThemeProvider>
					</div>
				</div>
			</ThemeProvider>
		</ThemeProvider>
	);
}
