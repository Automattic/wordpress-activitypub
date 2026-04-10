/**
 * Navigation Widget Component
 *
 * Quick access links to common actions and settings
 */

import { __ } from '@wordpress/i18n';
import { Icon, MenuGroup, MenuItem } from '@wordpress/components';
import { cog, people, plus } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';

export default function NavigationWidget() {
	const navigationItems = [
		{
			id: 'new-post',
			label: __( 'New Post', 'activitypub' ),
			icon: plus,
			href: 'post-new.php',
		},
		{
			id: 'followers',
			label: __( 'Followers', 'activitypub' ),
			icon: people,
			href: addQueryArgs( 'users.php', { page: 'activitypub-followers-list' } ),
		},
		{
			id: 'following',
			label: __( 'Following', 'activitypub' ),
			icon: people,
			href: addQueryArgs( 'users.php', { page: 'activitypub-following-list' } ),
		},
		{
			id: 'settings',
			label: __( 'Settings', 'activitypub' ),
			icon: cog,
			href: addQueryArgs( 'admin.php', { page: 'activitypub' } ),
		},
	];

	return (
		<div className="inspector-widget navigation-widget">
			<h2 className="inspector-widget__title">{ __( 'Quick Actions', 'activitypub' ) }</h2>
			<div className="inspector-widget__content">
				<MenuGroup>
					{ navigationItems.map( ( item ) => (
						<MenuItem key={ item.id } href={ item.href } className="menu-item">
							<Icon icon={ item.icon } size={ 20 } />
							<span>{ item.label }</span>
						</MenuItem>
					) ) }
				</MenuGroup>
			</div>
		</div>
	);
}
