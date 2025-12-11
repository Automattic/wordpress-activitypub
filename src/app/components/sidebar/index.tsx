/**
 * Sidebar Component
 *
 * Navigation sidebar with menu items for different sections
 */

/**
 * External dependencies
 */
import type { ComponentType, ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import {
	Button,
	Icon,
	MenuGroup,
	MenuItem,
	NavigableMenu,
	__experimentalHStack as HStack,
	__experimentalHeading as Heading,
} from '@wordpress/components';
import { chevronRight, chevronLeft, cog, postList } from '@wordpress/icons';
import { __, isRTL } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { useFeedFilters } from '../../hooks/use-feed-filters';
import { useLocation, useNavigate } from '../../router';
import SiteHub from '../site-hub';
import ActorSwitcher from '../actor-switcher';
import { ObjectTypes } from '../object-types';
import { PopularTags } from '../popular-tags';
import FeedDescription from './feed-description';
import MenuDescription from './menu-description';
import './style.scss';

/**
 * Menu item configuration for sidebar navigation.
 * Each item maps to a route path.
 */
export interface MenuItemConfig {
	id: string;
	path: string;
	label: string;
	icon: typeof postList;
	description?: string | ( () => ReactNode ) | ComponentType;
}

export const menuItems: MenuItemConfig[] = [
	{
		id: 'feed',
		path: '/',
		label: __( 'Feed', 'activitypub' ),
		icon: postList,
		description: FeedDescription,
	},
];

export default function Sidebar(): ReactNode {
	const location = useLocation();
	const navigate = useNavigate();
	const { hasActiveFilters, clearAllFilters } = useFeedFilters();

	// Check if a route is currently active
	const isRouteActive = ( path: string ): boolean => location.pathname === path;

	// For feed route, also consider filters for "selected" state
	const isFeedFullySelected: boolean = isRouteActive( '/' ) && ! hasActiveFilters;

	// Handle menu item click - navigate and clear filters if going to feed
	const handleMenuItemClick = ( path: string ): void => {
		if ( path === '/' ) {
			clearAllFilters();
		}
		void navigate( { to: path } );
	};

	const activeItem: MenuItemConfig = menuItems.find( ( item: MenuItemConfig ): boolean =>
		isRouteActive( item.path )
	);

	return (
		<div className="sidebar">
			<SiteHub />

			{ /* Navigation */ }
			<nav className="nav">
				<HStack spacing={ 3 } alignment="flex-start" className="sidebar-navigation__icon-title">
					<Button
						className="sidebar-navigation__button"
						size="compact"
						icon={ isRTL() ? chevronRight : chevronLeft }
						href="/wp-admin/"
						label={ __( 'Go to the Dashboard', 'activitypub' ) }
					/>
					<Heading className="sidebar-navigation__title" level={ 1 } size={ 20 }>
						{ __( 'Social Web', 'activitypub' ) }
					</Heading>
				</HStack>
				<NavigableMenu>
					<MenuDescription menuItem={ activeItem } />

					<MenuGroup>
						{ menuItems.map(
							( item: MenuItemConfig ): ReactNode => (
								<MenuItem
									key={ item.id }
									isSelected={ item.path === '/' ? isFeedFullySelected : isRouteActive( item.path ) }
									onClick={ () => handleMenuItemClick( item.path ) }
									className="menu-item"
								>
									{ item.icon && <Icon icon={ item.icon } size={ 24 } /> }
									<span>{ item.label }</span>
								</MenuItem>
							)
						) }
					</MenuGroup>
				</NavigableMenu>

				{ /* Route-specific sidebar content */ }
				{ isRouteActive( '/' ) && <ObjectTypes /> }
			</nav>

			{ /* Route-specific sidebar content */ }
			{ isRouteActive( '/' ) && <PopularTags /> }

			{ /* Footer */ }
			<div className="footer">
				<HStack justify="space-between" alignment="center">
					<ActorSwitcher />
					<Button
						icon={ cog }
						iconSize={ 20 }
						size="compact"
						href={ addQueryArgs( 'admin.php', { page: 'activitypub' } ) }
						target="_blank"
						label={ __( 'Settings', 'activitypub' ) }
						className="footer-settings-button"
					/>
				</HStack>
			</div>
		</div>
	);
}
