/**
 * WordPress dependencies
 */
import React, { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	TabPanel,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalHeading as Heading,
} from '@wordpress/components';
import { close } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { usePanelContext } from '../contexts/panel-context';

export interface FeatureTab {
	name: string;
	title: string;
	icon?: any;
	component: React.ComponentType< any >;
	enabled?: boolean;
}

interface ItemViewProps {
	title: string;
	subtitle?: string;
	tabs: FeatureTab[];
	onClose?: () => void;
	headerActions?: React.ReactNode;
	className?: string;
}

/**
 * Reusable ItemView component for displaying tabbed content panels
 * Similar to wp-calypso's hosting-dashboard ItemView
 */
export default function ItemView( { title, subtitle, tabs, onClose, headerActions, className = '' }: ItemViewProps ) {
	const { activeFeature, setActiveFeature, selectedItem, clearSelection } = usePanelContext();

	const handleClose = () => {
		if ( onClose ) {
			onClose();
		} else {
			clearSelection();
		}
	};

	// Filter enabled tabs
	const enabledTabs = useMemo( () => tabs.filter( ( tab ) => tab.enabled !== false ), [ tabs ] );

	// Convert to TabPanel format
	const tabPanelTabs = useMemo(
		() =>
			enabledTabs.map( ( tab ) => ( {
				name: tab.name,
				title: tab.title,
				className: `activitypub-tab-${ tab.name }`,
			} ) ),
		[ enabledTabs ]
	);

	// Find active tab component
	const activeTab = enabledTabs.find( ( tab ) => tab.name === activeFeature );

	if ( ! selectedItem ) {
		return (
			<div className={ `activitypub-item-view-empty ${ className }` }>
				<VStack spacing={ 3 } alignment="center">
					<Heading level={ 3 }>{ __( 'Select an item to view details', 'activitypub' ) }</Heading>
				</VStack>
			</div>
		);
	}

	return (
		<div className={ `activitypub-item-view ${ className }` }>
			<div className="activitypub-item-view__header">
				<HStack alignment="top">
					<VStack spacing={ 1 } className="activitypub-item-view__header-text">
						<Heading level={ 2 } size={ 24 }>
							{ title }
						</Heading>
						{ subtitle && <span className="activitypub-item-view__subtitle">{ subtitle }</span> }
					</VStack>
					<HStack spacing={ 2 } alignment="center" className="activitypub-item-view__header-actions">
						{ headerActions }
						<Button
							icon={ close }
							label={ __( 'Close', 'activitypub' ) }
							onClick={ handleClose }
							className="activitypub-item-view__close"
						/>
					</HStack>
				</HStack>
			</div>

			<div className="activitypub-item-view__content">
				{ enabledTabs.length > 1 ? (
					<TabPanel
						tabs={ tabPanelTabs }
						onSelect={ ( tabName: string ) => setActiveFeature( tabName ) }
						initialTabName={ activeFeature }
					>
						{ () => {
							const ActiveComponent = activeTab?.component;
							return ActiveComponent ? <ActiveComponent item={ selectedItem } /> : null;
						} }
					</TabPanel>
				) : (
					// Single tab - render directly without TabPanel
					enabledTabs[ 0 ] &&
					React.createElement( enabledTabs[ 0 ].component, {
						item: selectedItem,
					} )
				) }
			</div>
		</div>
	);
}

/**
 * Factory function to create feature preview components
 * Similar to wp-calypso's createFeaturePreview
 */
export function createFeaturePreview( featureName: string, Component: React.ComponentType< any > ) {
	return function FeaturePreview( props: any ) {
		const { selectedItem } = usePanelContext();

		if ( ! selectedItem ) {
			return null;
		}

		return <Component { ...props } item={ selectedItem } />;
	};
}
