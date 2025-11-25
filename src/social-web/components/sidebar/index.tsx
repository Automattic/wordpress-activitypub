/**
 * Sidebar Component
 *
 * Navigation sidebar with menu items for different sections
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
import { useSettings } from '../../contexts/settings-context';
import SiteHub from '../site-hub';
import ActorSwitcher from '../actor-switcher';
import { ObjectTypes } from '../object-types';
import './style.scss';

const menuItems = [ { id: 'feed', label: __( 'Feed', 'activitypub' ), icon: postList } ];

interface SidebarProps {
	activeSection: string;
	onNavigate: ( section: string ) => void;
}

export default function Sidebar( { activeSection, onNavigate }: SidebarProps ) {
	const { adminUrl } = useSettings();

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
					<MenuGroup>
						{ menuItems.map( ( item ) => (
							<MenuItem
								key={ item.id }
								isSelected={ activeSection === item.id }
								onClick={ () => onNavigate( item.id ) }
								className="menu-item"
							>
								{ item.icon && <Icon icon={ item.icon } size={ 20 } /> }
								<span>{ item.label }</span>
							</MenuItem>
						) ) }
					</MenuGroup>
				</NavigableMenu>

				{ /* Object Types Filter - shown only on Feed */ }
				{ activeSection === 'feed' && <ObjectTypes /> }
			</nav>

			{ /* Footer */ }
			<div className="footer">
				<HStack justify="space-between" alignment="center">
					<ActorSwitcher />
					<Button
						icon={ cog }
						iconSize={ 20 }
						size="compact"
						href={ `${ adminUrl }admin.php?page=activitypub` }
						target="_blank"
						label={ __( 'Settings', 'activitypub' ) }
						className="footer-settings-button"
					/>
				</HStack>
			</div>
		</div>
	);
}
