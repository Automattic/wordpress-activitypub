/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 */

import { useState, useEffect, useRef, lazy, Suspense } from '@wordpress/element';
import { SnackbarList, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import Sidebar from '../sidebar';
import Panel from '../panel';
import SiteHub from '../site-hub';
import { STORE_NAME } from '../../store';
import type { SocialWebSelectors } from '../../store';
import { isAtRoot as checkIsAtRoot } from '../../utils';
import './style.scss';

// Lazy load route stages for better performance
// Use magic comments to give chunks proper names
const FeedStage = lazy( () => import( /* webpackChunkName: "social-web/feed-stage" */ '../../routes/feed/stage' ) );

// Lazy load inspector components
const FeedInspector = lazy(
	() => import( /* webpackChunkName: "social-web/feed-inspector" */ '../../routes/feed/inspector' )
);

/**
 * Parse the URL hash to extract section and item ID
 * Format: #/ (root/sidebar), #/section or #/section/itemId
 */
function parseHash(): { section: string; itemId: string | number | null } {
	const hash = window.location.hash.slice( 1 ); // Remove #

	// Empty hash or just "/" means we're at the root (sidebar view on mobile)
	// Default to 'feed' section but distinguish from #/feed for mobile navigation
	if ( ! hash || hash === '/' ) {
		return { section: '', itemId: null };
	}

	const parts = hash.split( '/' ).filter( Boolean );
	const section = parts[ 0 ] || '';
	const itemId = parts[ 1 ] || null;

	// Convert itemId to number for feed
	if ( section === 'feed' && itemId ) {
		return { section, itemId: parseInt( itemId, 10 ) };
	}

	return { section, itemId };
}

/**
 * Update the URL hash and trigger hashchange event
 *
 * @param {string}              section Section name
 * @param {string|number|null} itemId  Optional item ID
 */
function updateHash( section: string, itemId?: string | number | null ) {
	const hash = itemId ? `#/${ section }/${ itemId }` : `#/${ section }`;
	// Use location.hash assignment to trigger hashchange event for mobile view updates
	window.location.hash = hash;
}

export function Layout() {
	// Parse initial hash on mount
	const initialHash = parseHash();
	const [ activeSection, setActiveSection ] = useState( initialHash.section );
	const [ selectedItemId, setSelectedItemId ] = useState< string | number | null >( initialHash.itemId );

	// Get active actor ID
	const activeActorId = useSelect(
		( select ) => ( select( STORE_NAME ) as SocialWebSelectors ).getActiveActorId(),
		[]
	);

	// Track previous actor ID to detect changes
	const prevActiveActorId = useRef( activeActorId );

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

	// Close inspector when actor changes
	useEffect( () => {
		if ( prevActiveActorId.current !== activeActorId && selectedItemId ) {
			setSelectedItemId( null );
			updateHash( activeSection );
		}
		prevActiveActorId.current = activeActorId;
	}, [ activeActorId, selectedItemId, activeSection ] );

	// Listen for hash changes (back/forward navigation).
	useEffect( () => {
		const syncUrlToState = () => {
			const { section, itemId } = parseHash();
			// Keep section empty when at root - mobile view relies on this!
			setActiveSection( section );
			setSelectedItemId( itemId );
		};

		window.addEventListener( 'hashchange', syncUrlToState );
		return () => {
			window.removeEventListener( 'hashchange', syncUrlToState );
		};
	}, [] );

	const selectItem = ( id: string | number ) => {
		setSelectedItemId( id );
		updateHash( activeSection, id );
	};

	const closeInspector = () => {
		setSelectedItemId( null );
		updateHash( activeSection );
	};

	const navigate = ( section: string ) => {
		setActiveSection( section );
		setSelectedItemId( null );
		updateHash( section );
	};

	// Mobile-specific back navigation
	// Inspector → Stage → Sidebar
	const navigateBack = () => {
		if ( selectedItemId ) {
			// If inspector is open, close it and return to stage
			closeInspector();
		} else if ( ! checkIsAtRoot() ) {
			// If stage is showing, return to sidebar view (root)
			window.location.hash = '#/';
		}
	};

	// Render main content (stage) with Suspense for lazy loading
	const renderStage = () => {
		const props = {
			onSelectItem: selectItem,
		};

		let StageComponent;
		switch ( activeSection ) {
			case 'feed':
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

		let InspectorComponent;
		let props;

		switch ( activeSection ) {
			case 'feed':
			default:
				// Feed inspector expects number type
				if ( typeof selectedItemId !== 'number' ) {
					return null;
				}
				InspectorComponent = FeedInspector;
				props = {
					id: selectedItemId,
					onClose: closeInspector,
				};
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

	// Determine mobile view state for CSS classes
	// Root: showing sidebar only (no section navigated to yet)
	// Stage: showing stage (section selected, no item)
	// Inspector: showing inspector (item selected)
	const isAtRoot = checkIsAtRoot();
	const mobileView = showInspector ? 'inspector' : ! isAtRoot ? 'stage' : 'sidebar';

	return (
		<div className="app-layout" data-section={ activeSection } data-mobile-view={ mobileView }>
			<div className="app-content">
				{ /* Sidebar - 240px fixed width (no Panel wrapper, stays dark) */ }
				<div className="sidebar-region">
					<Sidebar
						activeSection={ activeSection }
						onNavigate={ navigate }
						onNavigateBack={ navigateBack }
						selectedItemId={ selectedItemId }
					/>
				</div>

				{ /* Stage - main content area */ }
				<div className="stage-region">
					{ /* Mobile header - only shown on mobile, hidden on desktop */ }
					<SiteHub onNavigateBack={ navigateBack } selectedItemId={ selectedItemId } />
					<Panel>{ renderStage() }</Panel>
				</div>

				{ /* Inspector - optional 380px side panel */ }
				{ showInspector && (
					<div className="inspector-region">
						{ /* Mobile header - only shown on mobile, hidden on desktop */ }
						<SiteHub onNavigateBack={ navigateBack } selectedItemId={ selectedItemId } />
						<Panel>{ renderInspector() }</Panel>
					</div>
				) }
			</div>

			<SnackbarList notices={ notices } onRemove={ removeNotice } />
		</div>
	);
}
