/**
 * Navigation Widget Component
 *
 * Quick access links to common actions and settings
 */

import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { cog, people, addCard, plus } from '@wordpress/icons';
import { useSettings } from '../../../contexts/settings-context';
import './navigation-widget.scss';

export default function NavigationWidget() {
	const { adminUrl } = useSettings();

	const navigationItems = [
		{
			id: 'new-post',
			label: __( 'New Post', 'activitypub' ),
			icon: plus,
			href: `${ adminUrl }post-new.php`,
		},
		{
			id: 'followers',
			label: __( 'Followers', 'activitypub' ),
			icon: people,
			href: '#/followers',
		},
		{
			id: 'following',
			label: __( 'Following', 'activitypub' ),
			icon: addCard,
			href: `${ adminUrl }admin.php?page=activitypub&tab=followers`,
		},
		{
			id: 'settings',
			label: __( 'Settings', 'activitypub' ),
			icon: cog,
			href: `${ adminUrl }admin.php?page=activitypub`,
		},
	];

	return (
		<div className="inspector-widget navigation-widget">
			<h2 className="inspector-widget__title">{ __( 'Quick Actions', 'activitypub' ) }</h2>
			<div className="inspector-widget__content">
				<div className="navigation-widget__actions">
					{ navigationItems.map( ( item ) => (
						<Button
							key={ item.id }
							href={ item.href }
							icon={ item.icon }
							iconSize={ 20 }
							className="navigation-widget__action"
							variant="secondary"
						>
							{ item.label }
						</Button>
					) ) }
				</div>
			</div>
		</div>
	);
}
