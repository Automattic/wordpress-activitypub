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
import { addCard, comment, chevronRight, chevronLeft, postList } from '@wordpress/icons';
import SiteHub from '../site-hub';
import { __, isRTL } from '@wordpress/i18n';
import './style.scss';

const menuItems = [
	{ id: 'feed', label: __( 'Feed' ), icon: postList },
	{ id: 'following', label: __( 'Following' ), icon: addCard },
	{ id: 'interactions', label: __( 'Interactions' ), icon: comment },
];

interface SidebarProps {
	activeSection: string;
	onNavigate: ( section: string ) => void;
}

export default function Sidebar( { activeSection, onNavigate }: SidebarProps ) {
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
			</nav>

			{ /* Footer */ }
			<div className="footer">
				<MenuGroup>
					<MenuItem onClick={ () => window.open( '/docs', '_blank' ) }>Documentation</MenuItem>
					<MenuItem onClick={ () => onNavigate( 'settings' ) }>Settings</MenuItem>
				</MenuGroup>
			</div>
		</div>
	);
}
