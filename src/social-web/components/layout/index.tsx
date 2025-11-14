/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 */

import { useState, useEffect } from '@wordpress/element';
import { CommandMenu } from '@wordpress/commands';
import { SnackbarList } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import Sidebar from '../sidebar';
import Panel from '../panel';
import './style.scss';

// Import dashboard directly since it's the default route
import DashboardStage from '../../routes/dashboard/stage';
import FeedStage from '../../routes/feed/stage';
import FollowingStage from '../../routes/following/stage';
import InteractionsStage from '../../routes/interactions/stage';

// Import inspector components.
import FeedInspector from '../../routes/feed/inspector';
import FollowingInspector from '../../routes/following/inspector';
import InteractionInspector from '../../routes/interactions/inspector';

/**
 * Parse the URL hash to extract section and item ID
 * Format: #/section or #/section/itemId
 */
function parseHash(): { section: string; itemId: string | number | null } {
	const hash = window.location.hash.slice( 1 ); // Remove #
	if ( ! hash || hash === '/' ) {
		return { section: 'dashboard', itemId: null };
	}

	const parts = hash.split( '/' ).filter( Boolean );
	const section = parts[ 0 ] || 'dashboard';
	const itemId = parts[ 1 ] || null;

	// Convert itemId to number for feed
	if ( section === 'feed' && itemId ) {
		return { section, itemId: parseInt( itemId, 10 ) };
	}

	return { section, itemId };
}

/**
 * Update the URL hash without triggering a page reload
 *
 * @param {string}              section Section name
 * @param {string|number|null} itemId  Optional item ID
 */
function updateHash( section: string, itemId?: string | number | null ) {
	const hash = itemId ? `#/${ section }/${ itemId }` : `#/${ section }`;
	window.history.pushState( null, '', hash );
}

export function Layout() {
	const [ activeSection, setActiveSection ] = useState( 'dashboard' );
	const [ selectedItemId, setSelectedItemId ] = useState< string | number | null >( null );

	// Get notices for the snackbar
	const notices = useSelect( ( select ) => {
		const store = select( noticesStore ) as any;
		return store.getNotices().filter( ( notice: any ) => notice.type === 'snackbar' );
	}, [] );
	const { removeNotice } = useDispatch( noticesStore ) as any;

	// Initialize from URL hash on mount
	useEffect( () => {
		const { section, itemId } = parseHash();
		setActiveSection( section );
		setSelectedItemId( itemId );
	}, [] );

	// Listen for hash changes (back/forward navigation)
	useEffect( () => {
		const handleHashChange = () => {
			const { section, itemId } = parseHash();
			setActiveSection( section );
			setSelectedItemId( itemId );
		};

		window.addEventListener( 'hashchange', handleHashChange );
		return () => {
			window.removeEventListener( 'hashchange', handleHashChange );
		};
	}, [] );

	// Add fullscreen mode class to body
	useEffect( () => {
		document.body.classList.add( 'is-fullscreen-mode' );
		return () => {
			document.body.classList.remove( 'is-fullscreen-mode' );
		};
	}, [] );

	const handleSelectItem = ( id: string | number ) => {
		setSelectedItemId( id );
		updateHash( activeSection, id );
	};

	const handleCloseInspector = () => {
		setSelectedItemId( null );
		updateHash( activeSection );
	};

	const handleNavigate = ( section: string ) => {
		setActiveSection( section );
		setSelectedItemId( null );
		updateHash( section );
	};

	// Render main content (stage)
	const renderStage = () => {
		const props = { onSelectItem: handleSelectItem };

		switch ( activeSection ) {
			case 'dashboard':
				return <DashboardStage />;
			case 'feed':
				return <FeedStage { ...props } />;
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
		if ( ! selectedItemId ) {
			return null;
		}

		const props = { id: selectedItemId, onClose: handleCloseInspector };

		switch ( activeSection ) {
			case 'feed':
				return <FeedInspector { ...props } />;
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
		<div className="app-layout" data-section={ activeSection }>
			<CommandMenu />
			<div className="app-content">
				{ /* Sidebar - 240px fixed width (no Panel wrapper, stays dark) */ }
				<div className="sidebar-region">
					<Sidebar activeSection={ activeSection } onNavigate={ handleNavigate } />
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

			<SnackbarList notices={ notices } onRemove={ removeNotice } />
		</div>
	);
}
