/**
 * WordPress dependencies
 */
import React from 'react';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InterfaceSkeleton } from '@wordpress/interface';
import { NavigableRegion } from '@wordpress/admin-ui';
import { Button, __experimentalHStack as HStack, VisuallyHidden } from '@wordpress/components';
import { search } from '@wordpress/icons';
import { displayShortcut } from '@wordpress/keycodes';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import type { SocialWebSettings } from './types';
import type { Follower } from './types';
import { useRouter, getCurrentRoute } from './router';
import { NavigationProvider } from './components/navigation-context';
import { PanelProvider, usePanelContext } from './contexts/panel-context';
import PanelLayout from './components/panel-layout';
import ItemView from './components/item-view';
import { getFeature } from './features';
import SidebarNavigationScreenMain from './components/sidebar-navigation-screen-main';
import SidebarNavigationScreenFollowers from './components/sidebar-navigation-screen-followers';
import ContentPanelDashboard from './components/content-panel-dashboard';
import SiteIcon from './components/site-icon';

interface AppProps {
	settings: SocialWebSettings;
}

// Mock data for demonstration - will be replaced with WordPress Data API
const mockFollowers: Follower[] = [
	{
		id: '1',
		actor: 'https://mastodon.social/@user1',
		name: 'John Doe',
		username: 'user1@mastodon.social',
		avatar: 'https://via.placeholder.com/150',
		url: 'https://mastodon.social/@user1',
		created: '2024-01-15T10:00:00Z',
		modified: '2024-10-20T10:00:00Z',
		errors: 0,
		inbox: 'https://mastodon.social/@user1/inbox',
		shared_inbox: 'https://mastodon.social/inbox',
	},
	{
		id: '2',
		actor: 'https://pixelfed.social/@photographer',
		name: 'Jane Smith',
		username: 'photographer@pixelfed.social',
		avatar: 'https://via.placeholder.com/150',
		url: 'https://pixelfed.social/@photographer',
		created: '2024-02-20T10:00:00Z',
		modified: '2024-10-19T10:00:00Z',
		errors: 2,
		inbox: 'https://pixelfed.social/@photographer/inbox',
		shared_inbox: 'https://pixelfed.social/inbox',
	},
];

const mockStats = {
	followers: 42,
	following: 18,
	interactions: 156,
	posts: 234,
};

/**
 * Inner App component that has access to panel context
 */
function AppContent( { settings }: AppProps ) {
	const { location, navigate } = useRouter();
	const { selectedItem, setSelectedItem, setActiveFeature } = usePanelContext();

	const currentRoute = getCurrentRoute( location );
	const currentSection = location.params.section || 'dashboard';
	const featureConfig = getFeature( currentSection );

	// Sync router state with panel state
	useEffect( () => {
		// If we have an ID in the route, find and select the item
		if ( location.params.id && currentSection === 'followers' ) {
			const follower = mockFollowers.find( ( f ) => f.id === location.params.id );
			if ( follower ) {
				setSelectedItem( follower );
			}
		} else if ( ! location.params.id ) {
			setSelectedItem( null );
		}
	}, [ location.params.id, currentSection, setSelectedItem ] );

	// Handle navigation
	const handleNavigate = ( path: string ) => {
		navigate( path );
	};

	// Handle follower selection
	const handleSelectFollower = ( id: string ) => {
		const follower = mockFollowers.find( ( f ) => f.id === id );
		if ( follower ) {
			setSelectedItem( follower );
			navigate( `/followers/${ id }` );
			// Set default tab when selecting
			if ( featureConfig?.defaultTab ) {
				setActiveFeature( featureConfig.defaultTab );
			}
		}
	};

	// Render sidebar based on current route
	const renderSidebar = () => {
		switch ( currentSection ) {
			case 'followers':
				return (
					<SidebarNavigationScreenFollowers
						followers={ mockFollowers }
						selectedId={ selectedItem?.id }
						onSelectFollower={ handleSelectFollower }
					/>
				);
			case 'following':
			case 'interactions':
				// These would have their own sidebar screens
				return <SidebarNavigationScreenMain />;
			default:
				return <SidebarNavigationScreenMain />;
		}
	};

	// Render list/content panel
	const renderContent = () => {
		if ( currentSection === 'dashboard' ) {
			return null; // Dashboard uses canvas area only
		}

		// For list views when no item is selected
		if ( ! selectedItem ) {
			return (
				<div className="activitypub-content-panel">
					<h2>{ currentRoute?.label || __( 'Social Web', 'activitypub' ) }</h2>
					<p>{ __( 'Pick an item from the list to view details.', 'activitypub' ) }</p>
				</div>
			);
		}

		return null; // When item is selected, detail panel takes over
	};

	// Render detail panel with tabs
	const renderDetail = () => {
		if ( ! selectedItem || ! featureConfig ) {
			return null;
		}

		const title = selectedItem.name || selectedItem.title || 'Details';
		const subtitle = selectedItem.username || selectedItem.description || '';

		return (
			<ItemView
				title={ title }
				subtitle={ subtitle }
				tabs={ featureConfig.tabs }
				className="activitypub-detail-panel"
			/>
		);
	};

	// Render canvas/preview area
	const renderCanvas = () => {
		// Dashboard gets full canvas
		if ( currentSection === 'dashboard' ) {
			return <ContentPanelDashboard stats={ mockStats } onNavigate={ handleNavigate } />;
		}

		// Preview for selected items
		if ( selectedItem && selectedItem.url ) {
			return (
				<div className="activitypub-preview-panel">
					<iframe
						src={ selectedItem.url }
						title={ __( 'Profile Preview', 'activitypub' ) }
						className="activitypub-preview-iframe"
					/>
				</div>
			);
		}

		return (
			<div className="activitypub-preview-panel">
				<div className="activitypub-preview-placeholder">{ __( 'Preview area', 'activitypub' ) }</div>
			</div>
		);
	};

	return (
		<NavigationProvider onNavigate={ handleNavigate }>
			<div className="edit-site-layout__content">
				{ /* Sidebar Region */ }
				<NavigableRegion
					ariaLabel={ __( 'Navigation', 'activitypub' ) }
					className="edit-site-layout__sidebar-region"
				>
					<div className="edit-site-layout__sidebar">
						{ /* Site Hub - Always visible at top */ }
						<div className="edit-site-site-hub">
							<HStack justify="space-between" spacing="0">
								<HStack justify="flex-start" spacing="0">
									<div className="edit-site-site-hub__view-mode-toggle-container">
										<Button
											__next40pxDefaultSize
											href={ settings.adminUrl }
											label={ __( 'Go to the Dashboard', 'activitypub' ) }
											className="edit-site-layout__view-mode-toggle"
											style={ {
												transform: 'scale(0.5)',
												borderRadius: '4px',
											} }
										>
											<SiteIcon className="edit-site-layout__view-mode-toggle-icon" />
										</Button>
									</div>
									<div className="edit-site-site-hub__title">
										<Button
											__next40pxDefaultSize
											variant="link"
											href={ settings.siteUrl }
											target="_blank"
										>
											{ settings.siteTitle }
											<VisuallyHidden as="span">
												{
													/* translators: accessibility text */
													__( '(opens in a new tab)', 'activitypub' )
												}
											</VisuallyHidden>
										</Button>
									</div>
								</HStack>
								<HStack spacing={ 0 } expanded={ false } className="edit-site-site-hub__actions">
									<Button
										size="compact"
										className="edit-site-site-hub__toggle-command-center"
										icon={ search }
										onClick={ () => {
											// TODO: Open command palette when available
										} }
										label={ __( 'Open command palette', 'activitypub' ) }
										shortcut={ displayShortcut.primary( 'k' ) }
									/>
								</HStack>
							</HStack>
						</div>

						<div className="edit-site-sidebar__content">
							<div className="edit-site-sidebar__screen-wrapper">{ renderSidebar() }</div>
						</div>
					</div>
				</NavigableRegion>

				{ /* Main Content Area - Uses PanelLayout for smart panel management */ }
				<div className="edit-site-layout__canvas-container">
					{ currentSection === 'dashboard' ? (
						// Dashboard uses simple canvas layout
						<div className="edit-site-layout__canvas">
							<div className="edit-site-resizable-frame__inner-content">{ renderCanvas() }</div>
						</div>
					) : (
						// Other sections use PanelLayout for split view
						<PanelLayout
							sidebar={ null } // Already rendered above
							content={ renderContent() }
							detail={ renderDetail() }
							canvas={ renderCanvas() }
							className={ classnames( 'activitypub-panel-layout', {
								[ `section-${ currentSection }` ]: true,
							} ) }
						/>
					) }
				</div>
			</div>
		</NavigationProvider>
	);
}

/**
 * Main App component for Social Web.
 */
export default function App( { settings }: AppProps ): React.ReactElement {
	// Initialize with split view for non-dashboard sections
	const initialViewMode = 'split';

	const content = (
		<PanelProvider
			initialState={ {
				viewMode: initialViewMode,
				activeFeature: 'overview',
			} }
		>
			<AppContent settings={ settings } />
		</PanelProvider>
	);

	return <InterfaceSkeleton content={ content } className="edit-site-layout" />;
}
