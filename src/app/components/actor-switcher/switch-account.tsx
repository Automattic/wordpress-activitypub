/**
 * Switch Account Section Component
 */

import { __ } from '@wordpress/i18n';
import SiteIcon from '../site-icon';

type SwitchAccountProps = {
	isSiteActor: boolean;
	userAvatarUrl: string;
	userName: string;
	siteTitle: string;
	userRole: string;
	onSwitch: () => void;
};

export default function SwitchAccount( {
	isSiteActor,
	userAvatarUrl,
	userName,
	siteTitle,
	userRole,
	onSwitch,
}: SwitchAccountProps ) {
	return (
		<div className="actor-switcher__section">
			<span className="actor-switcher__section-header">{ __( 'Switch account', 'activitypub' ) }</span>
			<button type="button" className="actor-switcher__menu-item" onClick={ onSwitch }>
				{ isSiteActor ? (
					<img src={ userAvatarUrl } alt={ userName } className="actor-switcher__menu-avatar" />
				) : (
					<SiteIcon className="actor-switcher__menu-avatar" />
				) }
				<span className="actor-switcher__menu-label">{ isSiteActor ? userName : siteTitle }</span>
				<span className="actor-switcher__menu-badge">
					{ isSiteActor ? userRole : __( 'Blog', 'activitypub' ) }
				</span>
			</button>
		</div>
	);
}
