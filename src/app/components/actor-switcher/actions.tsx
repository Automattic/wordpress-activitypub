/**
 * Actions Section Component
 */

import { Icon } from '@wordpress/components';
import { people } from '@wordpress/icons';
import { Path, SVG } from '@wordpress/primitives';
import { __ } from '@wordpress/i18n';

// Logout icon (arrow pointing right, out of the box)
const logout = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		<Path d="M7.2 5h6c1.1 0 2 .9 2 2v1.5h-1.5V7c0-.3-.2-.5-.5-.5h-6c-.3 0-.5.2-.5.5v10c0 .3.2.5.5.5h6c.3 0 .5-.2.5-.5v-1.5h1.5V17c0 1.1-.9 2-2 2h-6c-1.1 0-2-.9-2-2V7c0-1.1.9-2 2-2z" />
		<Path d="M10 11.25h8.2l-1.7-1.7 1.1-1.1 3.5 3.5-3.5 3.5-1.1-1.1 1.7-1.7H10z" />
	</SVG>
);

type ActionsProps = {
	profileHref: string;
};

export default function Actions( { profileHref }: ActionsProps ) {
	return (
		<div className="actor-switcher__section actor-switcher__actions">
			<a href={ profileHref } className="actor-switcher__menu-item">
				<Icon icon={ people } className="actor-switcher__menu-icon" />
				<span className="actor-switcher__menu-label">{ __( 'Manage Profile', 'activitypub' ) }</span>
			</a>
			<a
				href="/wp-login.php?action=logout"
				className="actor-switcher__menu-item actor-switcher__menu-item--danger"
			>
				<Icon icon={ logout } className="actor-switcher__menu-icon" />
				<span className="actor-switcher__menu-label">{ __( 'Log out', 'activitypub' ) }</span>
			</a>
		</div>
	);
}
