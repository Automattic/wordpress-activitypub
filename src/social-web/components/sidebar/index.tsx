/**
 * Sidebar Component
 *
 * Navigation sidebar with menu items for different sections
 */

import { NavigableMenu, MenuItem, MenuGroup, Icon } from '@wordpress/components';
import { home, people, addCard, comment, postList } from '@wordpress/icons';
import './style.scss';
import { __ } from '@wordpress/i18n';

const menuItems = [
	{ id: 'dashboard', label: __( 'Dashboard' ), icon: home },
	{ id: 'feed', label: __( 'Feed' ), icon: postList },
	{ id: 'followers', label: __( 'Followers' ), icon: people },
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
			{ /* Header */ }
			<div className="header">
				<h1 className="title">{ __( 'Social Web' ) }</h1>
			</div>

			{ /* Navigation */ }
			<nav className="nav">
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
