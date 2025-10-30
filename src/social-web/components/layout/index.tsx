/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 */

import { useState, useEffect } from '@wordpress/element';
import Sidebar from '../sidebar';
import Panel from '../panel';
import './style.scss';

// Import stage components.
import DashboardStage from '../../routes/dashboard/stage';
import FollowersStage from '../../routes/followers/stage';
import FollowingStage from '../../routes/following/stage';
import InteractionsStage from '../../routes/interactions/stage';

// Import inspector components.
import FollowerInspector from '../../routes/followers/inspector';
import FollowingInspector from '../../routes/following/inspector';
import InteractionInspector from '../../routes/interactions/inspector';

export function Layout() {
	const [ activeSection, setActiveSection ] = useState( 'dashboard' );
	const [ selectedItemId, setSelectedItemId ] = useState< string | null >( null );

	// Add fullscreen mode class to body
	useEffect( () => {
		document.body.classList.add( 'is-fullscreen-mode' );
		return () => {
			document.body.classList.remove( 'is-fullscreen-mode' );
		};
	}, [] );

	const handleSelectItem = ( id: string ) => {
		setSelectedItemId( id );
	};

	const handleCloseInspector = () => {
		setSelectedItemId( null );
	};

	// Render main content (stage)
	const renderStage = () => {
		const props = { onSelectItem: handleSelectItem };

		switch ( activeSection ) {
			case 'dashboard':
				return <DashboardStage />;
			case 'followers':
				return <FollowersStage { ...props } />;
			case 'following':
				return <FollowingStage { ...props } />;
			case 'interactions':
				return <InteractionsStage { ...props } />;
			default:
				return <DashboardStage />;
		}
	};

	// Render detail panel (inspector)
	const renderInspector = () => {
		if ( ! selectedItemId ) return null;

		const props = { id: selectedItemId, onClose: handleCloseInspector };

		switch ( activeSection ) {
			case 'followers':
				return <FollowerInspector { ...props } />;
			case 'following':
				return <FollowingInspector { ...props } />;
			case 'interactions':
				return <InteractionInspector { ...props } />;
			default:
				return null;
		}
	};

	const showInspector = !! selectedItemId;

	return (
		<div className="app-layout">
			<div className="app-content">
				{ /* Sidebar - 240px fixed width (no Panel wrapper, stays dark) */ }
				<div className="sidebar-region">
					<Sidebar activeSection={ activeSection } onNavigate={ setActiveSection } />
				</div>

				{ /* Stage - main content area */ }
				<div className="stage-region">
					<Panel>{ renderStage() }</Panel>
				</div>

				{ /* Inspector - optional 380px side panel */ }
				{ showInspector && (
					<div className="inspector-region">
						<Panel>{ renderInspector() }</Panel>
					</div>
				) }
			</div>
		</div>
	);
}
