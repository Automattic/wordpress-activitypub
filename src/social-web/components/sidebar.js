/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { dashboard, people, commentAuthorAvatar } from '@wordpress/icons';

/**
 * Sidebar component for Social Web editor.
 *
 * @param {Object}   props              Component props.
 * @param {string}   props.activeView   The currently active view.
 * @param {Function} props.onNavigate   Callback when navigation changes.
 * @return {JSX.Element} The Sidebar component.
 */
export default function Sidebar( { activeView, onNavigate } ) {
	const navItems = [
		{
			id: 'dashboard',
			label: __( 'Dashboard', 'activitypub' ),
			icon: dashboard,
		},
		{
			id: 'followers',
			label: __( 'Followers', 'activitypub' ),
			icon: people,
		},
		{
			id: 'interactions',
			label: __( 'Interactions', 'activitypub' ),
			icon: commentAuthorAvatar,
		},
	];

	return (
		<div className="activitypub-social-web-sidebar">
			<nav className="activitypub-social-web-sidebar__nav">
				{ navItems.map( ( item ) => (
					<Button
						key={ item.id }
						icon={ item.icon }
						onClick={ () => onNavigate( item.id ) }
						className={ `activitypub-social-web-sidebar__item ${
							activeView === item.id ? 'is-active' : ''
						}` }
					>
						{ item.label }
					</Button>
				) ) }
			</nav>
		</div>
	);
}
