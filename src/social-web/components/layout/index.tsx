/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 */

import { useEffect, useCallback, Suspense } from '@wordpress/element';
import { privateApis as routerPrivateApis } from '@wordpress/router';
import { addQueryArgs } from '@wordpress/url';
import { CommandMenu } from '@wordpress/commands';
import { Spinner } from '@wordpress/components';
import { unlock } from '../../lock-unlock';
import Sidebar from '../sidebar';
import Panel from '../panel';
import './style.scss';

// Import dashboard separately since it's not in route areas
import DashboardStage from '../../routes/dashboard/stage';

const { useLocation, useHistory } = unlock( routerPrivateApis );

export function Layout() {
	// Following Gutenberg's pattern: destructure what we need from location
	const { query, name: activeSection = 'dashboard', areas = {} } = useLocation();
	const history = useHistory();

	// Get itemId from query params
	const selectedItemId = ( query?.itemId as string ) || null;

	// Add fullscreen mode class to body
	useEffect( () => {
		document.body.classList.add( 'is-fullscreen-mode' );
		return () => {
			document.body.classList.remove( 'is-fullscreen-mode' );
		};
	}, [] );

	const handleSelectItem = useCallback(
		( id: string ) => {
			// Navigate with itemId in query params
			const queryString = addQueryArgs( '', {
				...query,
				itemId: id,
			} );
			// Map section names to paths (dashboard is '/', others are '/{section}')
			const path = activeSection === 'dashboard' ? '/' : `/${ activeSection }`;
			history.navigate( `${ path }${ queryString }` );
		},
		[ query, activeSection, history ]
	);

	const handleCloseInspector = useCallback( () => {
		// Remove itemId from query
		const { itemId, ...restQuery } = query || {};
		const queryString = Object.keys( restQuery ).length > 0 ? addQueryArgs( '', restQuery ) : '';
		// Map section names to paths (dashboard is '/', others are '/{section}')
		const path = activeSection === 'dashboard' ? '/' : `/${ activeSection }`;
		history.navigate( `${ path }${ queryString }` );
	}, [ query, activeSection, history ] );

	const handleNavigate = useCallback(
		( section: string ) => {
			// Navigate to new section, preserve non-itemId query params
			const { itemId, ...restQuery } = query || {};
			const queryString = Object.keys( restQuery ).length > 0 ? addQueryArgs( '', restQuery ) : '';

			// Map section names to paths (dashboard is '/', others are '/{section}')
			const path = section === 'dashboard' ? '/' : `/${ section }`;
			history.navigate( `${ path }${ queryString }` );
		},
		[ query, history ]
	);

	// Render stage component from route areas or use DashboardStage for dashboard
	const StageComponent = areas?.stage;
	let stageElement;
	if ( StageComponent ) {
		stageElement = (
			<Suspense fallback={ <Spinner /> }>
				<StageComponent onSelectItem={ handleSelectItem } />
			</Suspense>
		);
	} else if ( activeSection === 'dashboard' ) {
		stageElement = <DashboardStage />;
	} else {
		stageElement = null;
	}

	// Render inspector component from route areas
	const InspectorComponent = areas?.inspector;
	let inspectorElement = null;
	if ( selectedItemId && InspectorComponent ) {
		inspectorElement = (
			<Suspense fallback={ <Spinner /> }>
				<InspectorComponent id={ selectedItemId } onClose={ handleCloseInspector } />
			</Suspense>
		);
	}

	const showInspector = !! inspectorElement;

	return (
		<div className="app-layout" data-section={ activeSection }>
			<CommandMenu />
			<div className="app-content">
				{ /* Sidebar - 240px fixed width (no Panel wrapper, stays dark) */ }
				<div className="sidebar-region">
					<Sidebar activeSection={ activeSection } onNavigate={ handleNavigate } />
				</div>

				{ /* Stage - main content area */ }
				<div className="stage-region">
					<Panel>{ stageElement }</Panel>
				</div>

				{ /* Inspector - optional 380px side panel */ }
				{ showInspector && (
					<div className="inspector-region">
						<Panel>{ inspectorElement }</Panel>
					</div>
				) }
			</div>
		</div>
	);
}
