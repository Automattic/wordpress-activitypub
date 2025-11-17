/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 */

import { useState, useEffect, lazy, Suspense } from '@wordpress/element';
import { CommandMenu } from '@wordpress/commands';
import { SnackbarList, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import Sidebar from '../sidebar';
import Panel from '../panel';
import './style.scss';

// Lazy load route stages for better performance
// Use magic comments to give chunks proper names
const FeedStage = lazy( () => import( /* webpackChunkName: "feed-stage" */ '../../routes/feed/stage' ) );
const FollowingStage = lazy( () => import( /* webpackChunkName: "following-stage" */ '../../routes/following/stage' ) );
const InteractionsStage = lazy(
	() => import( /* webpackChunkName: "interactions-stage" */ '../../routes/interactions/stage' )
);

// Lazy load inspector components
const FeedInspector = lazy( () => import( /* webpackChunkName: "feed-inspector" */ '../../routes/feed/inspector' ) );
const FollowingInspector = lazy(
	() => import( /* webpackChunkName: "following-inspector" */ '../../routes/following/inspector' )
);
const InteractionInspector = lazy(
	() => import( /* webpackChunkName: "interaction-inspector" */ '../../routes/interactions/inspector' )
);

/**
 * Parse the URL hash to extract section and item ID
 * Format: #/section or #/section/itemId
 */
function parseHash(): { section: string; itemId: string | number | null } {
	const hash = window.location.hash.slice( 1 ); // Remove #
	if ( ! hash || hash === '/' ) {
		return { section: 'feed', itemId: null };
	}

	const parts = hash.split( '/' ).filter( Boolean );
	const section = parts[ 0 ] || 'feed';
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
	const [ activeSection, setActiveSection ] = useState( 'feed' );
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

	// Render main content (stage) with Suspense for lazy loading
	const renderStage = () => {
		const props = { onSelectItem: handleSelectItem };

		let StageComponent;
		switch ( activeSection ) {
			case 'feed':
				StageComponent = FeedStage;
				break;
			case 'following':
				StageComponent = FollowingStage;
				break;
			case 'interactions':
				StageComponent = InteractionsStage;
				break;
			default:
				StageComponent = FeedStage;
		}

		return (
			<Suspense
				fallback={
					<div style={ { padding: '20px', textAlign: 'center' } }>
						<Spinner />
					</div>
				}
			>
				<StageComponent { ...props } />
			</Suspense>
		);
	};

	// Render detail panel (inspector) with Suspense for lazy loading
	const renderInspector = () => {
		if ( ! selectedItemId ) {
			return null;
		}

		const props = { id: selectedItemId, onClose: handleCloseInspector };

		let InspectorComponent;
		switch ( activeSection ) {
			case 'feed':
				InspectorComponent = FeedInspector;
				break;
			case 'following':
				InspectorComponent = FollowingInspector;
				break;
			case 'interactions':
				InspectorComponent = InteractionInspector;
				break;
			default:
				return null;
		}

		return (
			<Suspense
				fallback={
					<div style={ { padding: '20px', textAlign: 'center' } }>
						<Spinner />
					</div>
				}
			>
				<InspectorComponent { ...props } />
			</Suspense>
		);
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
